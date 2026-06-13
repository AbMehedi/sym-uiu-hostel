<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\Complaint;
use App\Entity\ComplaintUpdate;
use App\Entity\RepairCost;
use App\Entity\Room;
use App\Entity\RoomAssignment;
use App\Entity\Supervisor;
use App\Entity\SupervisorTask;
use App\Entity\User;
use App\Enum\Gender;
use App\Enum\AdmissionStatus;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintCategory;
use App\Enum\ComplaintStatus;
use App\Enum\RequestStatus;
use App\Enum\Role;
use App\Enum\RoomStatus;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\AdmissionRequestRepository;
use App\Repository\ComplaintRepository;
use App\Repository\RepairCostRepository;
use App\Repository\RoomChangeRequestRepository;
use App\Repository\RoomRepository;
use App\Repository\StudentRepository;
use App\Repository\SupervisorRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    // ─── Dashboard ────────────────────────────────────────────────────────────

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(
        RoomRepository $roomRepo,
        StudentRepository $studentRepo,
        ComplaintRepository $complaintRepo,
        AdmissionRequestRepository $admissionRepo,
        RepairCostRepository $repairCostRepo,
    ): Response {
        $rooms = $roomRepo->findAll();

        // B-15: distinguish "full rooms" (all beds taken) from "rooms with any occupant"
        $fullRooms     = 0;
        $occupiedRooms = 0; // at least one resident
        $vacantRooms   = 0; // zero residents
        foreach ($rooms as $room) {
            $occ = $room->getActualOccupancy();
            if ($occ === 0) {
                $vacantRooms++;
            } else {
                $occupiedRooms++;
                if ($room->isFull()) {
                    $fullRooms++;
                }
            }
        }

        $totalRepairCost = $repairCostRepo->findGrandTotal();
        $pendingRequests = $admissionRepo->findPending();
        $totalComplaints = count($complaintRepo->findAll());
        $pendingComplaints = count($complaintRepo->findBy(['status' => \App\Enum\ComplaintStatus::Pending]));
        $inProgressComplaints = count($complaintRepo->findBy(['status' => \App\Enum\ComplaintStatus::InProgress]));
        $resolvedComplaints = count($complaintRepo->findBy(['status' => \App\Enum\ComplaintStatus::Resolved]));
        $occupancyRate = count($rooms) > 0 ? round(($occupiedRooms / count($rooms)) * 100, 1) : 0.0;

        return $this->render('admin/dashboard.html.twig', [
            'totalRooms'        => count($rooms),
            'occupiedRooms'     => $occupiedRooms,   // rooms with ≥1 resident
            'fullRooms'         => $fullRooms,        // rooms at full capacity
            'vacantRooms'       => $vacantRooms,
            'occupancyRate'     => $occupancyRate,
            'totalStudents'     => count($studentRepo->findAll()),
            'totalComplaints'   => $totalComplaints,
            'pendingComplaints' => $pendingComplaints,
            'inProgressComplaints' => $inProgressComplaints,
            'resolvedComplaints' => $resolvedComplaints,
            'pendingAdmissions' => count($pendingRequests),
            'pendingRequests'   => $pendingRequests,
            'recentComplaints'  => $complaintRepo->findRecent(5),
            'totalRepairCost'   => $totalRepairCost,
        ]);
    }

    // ─── Students ─────────────────────────────────────────────────────────────

    #[Route('/students', name: 'admin_students')]
    public function students(
        StudentRepository $studentRepository,
        SupervisorRepository $supervisorRepository,
        RoomRepository $roomRepository,
        EntityManagerInterface $em,
    ): Response {
        $students    = $studentRepository->findBy(['admissionStatus' => AdmissionStatus::Approved], ['id' => 'DESC']);
        $supervisors = $supervisorRepository->findBy([], ['hostelAssigned' => 'ASC', 'id' => 'ASC']);
        $allRooms    = $roomRepository->findBy([], ['hostel' => 'ASC', 'roomNumber' => 'ASC']);

        $availableRooms = array_values(array_filter($allRooms, fn($r) => !$r->isFull()));

        $unassignedStudents = array_values(array_filter(
            $students,
            fn($s) => $s->getRoom() === null
        ));

        $activeAssignments = $em->getRepository(RoomAssignment::class)
            ->findBy(['status' => AssignmentStatus::Active], ['assignedDate' => 'DESC']);
        $allAssignments = $em->getRepository(RoomAssignment::class)
            ->findBy([], ['id' => 'DESC']);

        $totalBeds     = array_sum(array_map(fn($r) => $r->getCapacity(), $allRooms));
        $occupiedBeds  = count($activeAssignments);
        $availableBeds = $totalBeds - $occupiedBeds;

        return $this->render('admin/students.html.twig', [
            'students'           => $students,
            'supervisors'        => $supervisors,
            'hostels'            => $roomRepository->findDistinctHostelNames(),
            'availableRooms'     => $availableRooms,
            'allRooms'           => $allRooms,
            'unassignedStudents' => $unassignedStudents,
            'activeAssignments'  => $activeAssignments,
            'allAssignments'     => $allAssignments,
            'totalBeds'          => $totalBeds,
            'occupiedBeds'       => $occupiedBeds,
            'availableBeds'      => $availableBeds,
        ]);
    }

    #[Route('/students/{id}/change-supervisor', name: 'admin_student_change_supervisor', methods: ['POST'])]
    public function studentChangeSupervisor(
        int $id,
        Request $request,
        StudentRepository $studentRepo,
        SupervisorRepository $supervisorRepo,
        EntityManagerInterface $em,
    ): Response {
        $student = $studentRepo->find($id);
        if (!$student) {
            $this->addFlash('error', 'Student not found.');
            return $this->redirectToRoute('admin_students');
        }

        $supervisorId = (int) $request->request->get('supervisorId', 0);

        if ($supervisorId === 0) {
            // Remove supervisor assignment
            $student->setSupervisor(null);
            $this->addFlash('success', 'Supervisor removed from ' . $student->getUser()->getName() . '.');
        } else {
            $supervisor = $supervisorRepo->find($supervisorId);
            if (!$supervisor) {
                $this->addFlash('error', 'Supervisor not found.');
                return $this->redirectToRoute('admin_students');
            }
            $student->setSupervisor($supervisor);
            $this->addFlash('success',
                $supervisor->getUser()->getName() . ' is now the supervisor of ' . $student->getUser()->getName() . '.');
        }

        $em->flush();
        $this->logAudit($em, 'student.change_supervisor', [
            'studentId'    => $student->getId(),
            'studentName'  => $student->getUser()->getName(),
            'supervisorId' => $supervisorId,
        ]);

        return $this->redirectToRoute('admin_students');
    }

    // ─── Rooms ────────────────────────────────────────────────────────────────

    #[Route('/rooms', name: 'admin_rooms')]
    public function rooms(RoomRepository $roomRepository): Response
    {
        $rooms = $roomRepository->findBy([], ['hostel' => 'ASC', 'roomNumber' => 'ASC']);
        return $this->render('admin/rooms.html.twig', [
            'rooms'   => $rooms,
            'hostels' => $roomRepository->findDistinctHostelNames(),
        ]);
    }

    #[Route('/rooms/new', name: 'admin_rooms_new', methods: ['POST'])]
    public function roomsNew(Request $request, EntityManagerInterface $em): Response
    {
        $room = new Room();

        $hostel = trim((string) $request->request->get('hostel', ''));
        if ($hostel === '') {
            $this->addFlash('error', 'Hostel is required.');
            return $this->redirectToRoute('admin_rooms');
        }

        $rawRoomNumber = strtoupper(trim((string) $request->request->get('roomNumber')));
        if ($rawRoomNumber === '') {
            $this->addFlash('error', 'Room number is required.');
            return $this->redirectToRoute('admin_rooms');
        }

        // B-05: check for duplicate room number before persisting
        $existing = $em->getRepository(Room::class)->findOneBy(['roomNumber' => $rawRoomNumber]);
        if ($existing) {
            $this->addFlash('error', 'Room number "' . $rawRoomNumber . '" already exists. Please use a different number.');
            return $this->redirectToRoute('admin_rooms');
        }

        // B-06: server-side validation for capacity, floor, and initial occupancy
        $capacityRaw   = $request->request->get('capacity', 2);
        $floorRaw      = $request->request->get('floor', 1);
        $occupancyRaw  = $request->request->get('currentOccupancy', 0);

        if (!is_numeric($capacityRaw) || (int)$capacityRaw < 1) {
            $this->addFlash('error', 'Capacity must be a positive integer (minimum 1).');
            return $this->redirectToRoute('admin_rooms');
        }
        if (!is_numeric($floorRaw) || (int)$floorRaw < 0) {
            $this->addFlash('error', 'Floor must be a non-negative integer (ground floor = 0).');
            return $this->redirectToRoute('admin_rooms');
        }
        if (!is_numeric($occupancyRaw) || (int)$occupancyRaw < 0) {
            $this->addFlash('error', 'Initial occupancy cannot be negative.');
            return $this->redirectToRoute('admin_rooms');
        }

        $capacity  = (int) $capacityRaw;
        $occupancy = (int) $occupancyRaw;

        if ($occupancy > $capacity) {
            $this->addFlash('error',
                "Initial occupancy ($occupancy) cannot exceed capacity ($capacity). A room cannot have more beds occupied than it has available.");
            return $this->redirectToRoute('admin_rooms');
        }

        $room->setRoomNumber($rawRoomNumber);
        $room->setHostel($hostel);
        $room->setFloor((int) $floorRaw);
        $room->setCapacity($capacity);
        $room->setCurrentOccupancy($occupancy);
        $room->setRoomType($request->request->get('roomType') ?: 'Standard');
        $room->setStatus(RoomStatus::Available);
        $room->syncStatus(); // auto-set Full if occupancy already at capacity


        $em->persist($room);
        $em->flush();

        // B-20: audit log
        $this->logAudit($em, 'room.create', ['roomNumber' => $rawRoomNumber, 'hostel' => $hostel, 'capacity' => (int)$capacityRaw]);

        $this->addFlash('success', 'Room ' . $room->getRoomNumber() . ' created successfully!');
        return $this->redirectToRoute('admin_rooms');
    }

    #[Route('/rooms/{id}/delete', name: 'admin_rooms_delete', methods: ['POST'])]
    public function roomsDelete(int $id, EntityManagerInterface $em, RoomRepository $repo): Response
    {
        $room = $repo->find($id);
        if ($room && $room->getActualOccupancy() === 0) {
            $roomNumber = $room->getRoomNumber();
            $em->remove($room);
            $em->flush();
            // B-20: audit log
            $this->logAudit($em, 'room.delete', ['roomNumber' => $roomNumber]);
            $this->addFlash('success', 'Room deleted.');
        } else {
            $this->addFlash('error', 'Cannot delete an occupied room.');
        }
        return $this->redirectToRoute('admin_rooms');
    }

    // B-16: Toggle room maintenance status
    #[Route('/rooms/{id}/toggle-maintenance', name: 'admin_room_toggle_maintenance', methods: ['POST'])]
    public function roomToggleMaintenance(int $id, RoomRepository $roomRepo, EntityManagerInterface $em): Response
    {
        $room = $roomRepo->find($id);
        if (!$room) {
            $this->addFlash('error', 'Room not found.');
            return $this->redirectToRoute('admin_rooms');
        }

        if ($room->getStatus() === RoomStatus::UnderMaintenance) {
            // Take out of maintenance — recalculate actual status
            $room->recalculateOccupancy();
            $this->addFlash('success', 'Room ' . $room->getRoomNumber() . ' is now back in service.');
        } else {
            $room->setStatus(RoomStatus::UnderMaintenance);
            $this->addFlash('success', 'Room ' . $room->getRoomNumber() . ' set to Under Maintenance.');
        }

        $em->flush();
        return $this->redirectToRoute('admin_rooms');
    }

    #[Route('/rooms/{id}/photo', name: 'admin_room_photo', methods: ['POST'])]
    public function roomPhoto(
        int $id,
        Request $request,
        RoomRepository $roomRepo,
        EntityManagerInterface $em,
    ): Response {
        $room = $roomRepo->find($id);
        if (!$room) {
            $this->addFlash('error', 'Room not found.');
            return $this->redirectToRoute('admin_rooms');
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $file */
        $file = $request->files->get('photo');
        if (!$file) {
            $this->addFlash('error', 'No photo uploaded.');
            return $this->redirectToRoute('admin_rooms');
        }

        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/rooms';
        $filename   = 'room-' . $room->getId() . '-' . bin2hex(random_bytes(4)) . '.' . $file->guessExtension();
        $file->move($uploadsDir, $filename);

        $room->setPhotoPath('/uploads/rooms/' . $filename);
        $em->flush();

        $this->addFlash('success', 'Room photo updated.');
        return $this->redirectToRoute('admin_rooms');
    }

    // ─── Room Assignment ──────────────────────────────────────────────────────

    // ─── Room Assignment ──────────────────────────────────────────────────────

    #[Route('/room-assign', name: 'admin_room_assign')]
    public function roomAssign(): Response
    {
        return $this->redirectToRoute('admin_students');
    }

    #[Route('/room-assign/new', name: 'admin_room_assign_new', methods: ['POST'])]
    public function roomAssignNew(
        Request $request,
        StudentRepository $studentRepo,
        RoomRepository $roomRepo,
        SupervisorRepository $supervisorRepo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        $studentId = (int) $request->request->get('studentId');
        $roomId    = (int) $request->request->get('roomId');
        $supervisorId = (int) $request->request->get('supervisorId');
        $hostel = trim((string) $request->request->get('hostel'));

        $student = $studentRepo->find($studentId);
        $room    = $roomRepo->find($roomId);
        $supervisor = $supervisorRepo->find($supervisorId);

        if (!$student || !$room || !$supervisor || $hostel === '') {
            $this->addFlash('error', 'Student, hostel, supervisor, and room are required.');
            return $this->redirectToRoute('admin_students');
        }

        if ($supervisor->getHostelAssigned() !== $hostel) {
            $this->addFlash('error', 'Selected supervisor is not assigned to the selected hostel.');
            return $this->redirectToRoute('admin_students');
        }

        if ($room->getHostel() !== $hostel) {
            $this->addFlash('error', 'Selected room does not belong to the selected hostel.');
            return $this->redirectToRoute('admin_students');
        }

        if ($room->isFull()) {
            $this->addFlash('error', 'Selected room is already full.');
            return $this->redirectToRoute('admin_students');
        }

        // Deactivate any existing assignment for this student
        foreach ($student->getRoomAssignments() as $existing) {
            if ($existing->getStatus() === AssignmentStatus::Active) {
                $existing->setStatus(AssignmentStatus::Vacated);
                $existing->setVacatedDate(new DateTimeImmutable());
                $em->flush();
                $existing->getRoom()->recalculateOccupancy();
            }
        }

        $assignment = new RoomAssignment();
        $assignment->setStudent($student);
        $assignment->setRoom($room);
        $assignment->setAssignedDate(new DateTimeImmutable());
        $assignment->setStatus(AssignmentStatus::Active);

        // Persist explicit Student↔Supervisor relation as well.
        $student->setSupervisor($supervisor);

        // Keep Room↔Supervisor consistent (rooms in a hostel should point to the hostel supervisor).
        if ($room->getSupervisor() === null || $room->getSupervisor()?->getId() !== $supervisor->getId()) {
            $room->setSupervisor($supervisor);
        }

        $em->persist($assignment);
        $em->flush();

        $room->recalculateOccupancy();
        $em->flush();

        // B-18: notify student by email
        $this->sendNotification(
            $mailer,
            $student->getUser()->getEmail(),
            'Room Assignment — UIU Hostel',
            sprintf(
                "Dear %s,\n\nYou have been assigned to Room %s (%s).\n\nPlease contact the hostel office if you have any questions.\n\nUIU Hostel Administration",
                $student->getUser()->getName(),
                $room->getRoomNumber(),
                $room->getHostel(),
            )
        );

        // B-20: audit log
        $this->logAudit($em, 'room.assign', [
            'studentId'   => $student->getId(),
            'studentName' => $student->getUser()->getName(),
            'hostel'      => $hostel,
            'supervisorId' => $supervisor->getId(),
            'supervisorName' => $supervisor->getUser()->getName(),
            'roomNumber'  => $room->getRoomNumber(),
        ]);

        $this->addFlash('success', $student->getUser()->getName() . ' assigned to ' . $hostel . ', Room ' . $room->getRoomNumber() . ', under supervisor ' . $supervisor->getUser()->getName() . '.');
        return $this->redirectToRoute('admin_students');
    }

    #[Route('/room-assign/{id}/revoke', name: 'admin_room_assign_revoke', methods: ['POST'])]
    public function roomAssignRevoke(int $id, EntityManagerInterface $em): Response
    {
        $assignment = $em->getRepository(RoomAssignment::class)->find($id);
        if ($assignment && $assignment->getStatus() === AssignmentStatus::Active) {
            $assignment->setStatus(AssignmentStatus::Vacated);
            $assignment->setVacatedDate(new DateTimeImmutable());
            $assignment->getStudent()?->setSupervisor(null);
            $em->flush();
            $assignment->getRoom()->recalculateOccupancy();
            $em->flush();
            $this->addFlash('success', 'Room assignment revoked.');
        }
        return $this->redirectToRoute('admin_students');
    }

    // ─── Supervisors ──────────────────────────────────────────────────────────

    #[Route('/supervisors', name: 'admin_supervisors')]
    public function supervisors(
        SupervisorRepository $supervisorRepository,
        RoomRepository $roomRepository,
    ): Response
    {
        $supervisors = $supervisorRepository->findBy([], ['hostelAssigned' => 'ASC', 'id' => 'ASC']);
        return $this->render('admin/supervisors.html.twig', [
            'supervisors' => $supervisors,
            'hostels'     => $roomRepository->findDistinctHostelNames(),
        ]);
    }

    #[Route('/supervisors/new', name: 'admin_supervisors_new', methods: ['POST'])]
    public function supervisorsNew(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        MailerInterface $mailer,
    ): Response {
        $name  = trim((string) $request->request->get('name'));
        $email = trim((string) $request->request->get('email'));
        $phone = trim((string) $request->request->get('phone'));
        $hostel = trim((string) $request->request->get('hostel', (string) $request->request->get('block')));
        $nidNumber = trim((string) $request->request->get('nidNumber', (string) $request->request->get('nid')));
        $genderRaw = trim((string) $request->request->get('gender'));

        if (!$name || !$email || !$hostel || !$nidNumber) {
            $this->addFlash('error', 'Name, email, hostel, and NID are required.');
            return $this->redirectToRoute('admin_supervisors');
        }

        // B-07: use provided password or generate a secure random one
        $providedPass = (string) $request->request->get('password', '');
        $plainPassword = ($providedPass !== '') ? $providedPass : bin2hex(random_bytes(8));

        // Check duplicate email
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $this->addFlash('error', 'Email already in use.');
            return $this->redirectToRoute('admin_supervisors');
        }

        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setRole(Role::Supervisor);
        $user->setPasswordHash($hasher->hashPassword($user, $plainPassword));

        $supervisor = new Supervisor();
        $supervisor->setUser($user);
        $supervisor->setPhone($phone ?: null);
        $supervisor->setHostelAssigned($hostel);
        $supervisor->setNidNumber($nidNumber);

        $gender = match (strtolower($genderRaw)) {
            Gender::Male->value => Gender::Male,
            Gender::Female->value => Gender::Female,
            Gender::Other->value => Gender::Other,
            default => null,
        };
        $supervisor->setGender($gender);

        $em->persist($user);
        $em->persist($supervisor);
        $em->flush();

        // Keep Room↔Supervisor relation consistent: tag all rooms in this hostel to this supervisor.
        $roomsInHostel = $em->getRepository(Room::class)->findBy(['hostel' => $hostel]);
        foreach ($roomsInHostel as $room) {
            $room->setSupervisor($supervisor);
        }
        $em->flush();

        // B-20: audit log
        $this->logAudit($em, 'supervisor.create', ['name' => $name, 'email' => $email, 'hostel' => $hostel]);

        // Supervisor identity docs (optional uploads)
        $baseUploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/supervisors';
        if (!is_dir($baseUploadsDir)) {
            @mkdir($baseUploadsDir, 0775, true);
        }
        $folder = 'sup-' . $supervisor->getId() . '-' . bin2hex(random_bytes(4));
        $uploadsDir = $baseUploadsDir . '/' . $folder;
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0775, true);
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $nidDoc */
        $nidDoc = $request->files->get('nidDocument');
        if ($nidDoc) {
            try {
                $ext = $nidDoc->guessExtension() ?: 'bin';
                $filename = 'nid-' . bin2hex(random_bytes(6)) . '.' . $ext;
                $nidDoc->move($uploadsDir, $filename);
                $supervisor->setNidDocumentPath('/uploads/supervisors/' . $folder . '/' . $filename);
            } catch (FileException) {
                $this->addFlash('error', 'Failed to upload NID document.');
            }
        }

        $additionalDocPaths = [];
        /** @var array<int, \Symfony\Component\HttpFoundation\File\UploadedFile|null> $additionalDocs */
        $additionalDocs = $request->files->get('additionalDocs') ?? [];
        if (is_array($additionalDocs)) {
            foreach ($additionalDocs as $doc) {
                if (!$doc) {
                    continue;
                }
                try {
                    $ext = $doc->guessExtension() ?: 'bin';
                    $filename = 'doc-' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $doc->move($uploadsDir, $filename);
                    $additionalDocPaths[] = '/uploads/supervisors/' . $folder . '/' . $filename;
                } catch (FileException) {
                    $this->addFlash('error', 'Failed to upload one of the additional documents.');
                }
            }
        }
        if ($additionalDocPaths !== []) {
            $supervisor->setAdditionalDocs($additionalDocPaths);
        }

        $em->flush();

        // B-18: email supervisor their credentials
        $this->sendNotification(
            $mailer,
            $email,
            'Your Supervisor Account — UIU Hostel',
            sprintf(
                "Dear %s,\n\nYour supervisor account has been created.\n\nLogin Email: %s\nTemporary Password: %s\n\nPlease log in and change your password immediately.\n\nUIU Hostel Administration",
                $name, $email, $plainPassword
            )
        );

        $this->addFlash('success', "Supervisor {$name} created. Credentials sent to {$email}.");
        return $this->redirectToRoute('admin_supervisors');
    }

    #[Route('/supervisors/{id}/delete', name: 'admin_supervisors_delete', methods: ['POST'])]
    public function supervisorsDelete(int $id, SupervisorRepository $repo, EntityManagerInterface $em): Response
    {
        $supervisor = $repo->find($id);
        if (!$supervisor) {
            $this->addFlash('error', 'Supervisor not found.');
            return $this->redirectToRoute('admin_supervisors');
        }

        // B-04: block deletion if supervisor has active tasks or assigned complaints
        $activeTasks      = $supervisor->getTasks()->filter(fn($t) => $t->getStatus() !== \App\Enum\TaskStatus::Done);
        $activeComplaints = $supervisor->getComplaints()->filter(fn($c) => $c->getStatusEnum() !== ComplaintStatus::Resolved);

        if ($activeTasks->count() > 0 || $activeComplaints->count() > 0) {
            $this->addFlash('error', sprintf(
                'Cannot delete supervisor "%s": they have %d active task(s) and %d unresolved complaint(s). Resolve or reassign them first.',
                $supervisor->getUser()->getName(),
                $activeTasks->count(),
                $activeComplaints->count()
            ));
            return $this->redirectToRoute('admin_supervisors');
        }

        $supervisorName = $supervisor->getUser()->getName();
        $user = $supervisor->getUser();
        $em->remove($user); // CASCADE removes supervisor
        $em->flush();

        // B-20: audit log
        $this->logAudit($em, 'supervisor.delete', ['name' => $supervisorName]);

        $this->addFlash('success', "Supervisor {$supervisorName} removed.");
        return $this->redirectToRoute('admin_supervisors');
    }


    #[Route('/tasks', name: 'admin_tasks')]
    public function tasks(SupervisorRepository $supervisorRepo, EntityManagerInterface $em): Response
    {
        $supervisors = $supervisorRepo->findBy([], ['id' => 'ASC']);
        $tasks       = $em->getRepository(SupervisorTask::class)->findBy([], ['id' => 'DESC']);

        return $this->render('admin/tasks.html.twig', [
            'supervisors' => $supervisors,
            'tasks'       => $tasks,
        ]);
    }

    #[Route('/tasks/new', name: 'admin_tasks_new', methods: ['POST'])]
    public function tasksNew(
        Request $request,
        SupervisorRepository $supRepo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        $supervisorId = (int) $request->request->get('supervisorId');
        $title        = trim((string) $request->request->get('title'));
        $description  = trim((string) $request->request->get('description', ''));
        $dueDateStr   = $request->request->get('dueDate');
        $priorityStr  = $request->request->get('priority', 'normal');

        $supervisor = $supRepo->find($supervisorId);
        if (!$supervisor || !$title) {
            $this->addFlash('error', 'Supervisor and task title are required.');
            return $this->redirectToRoute('admin_tasks');
        }

        // B-17: resolve priority enum
        try {
            $priority = TaskPriority::from(strtolower($priorityStr));
        } catch (\ValueError) {
            $priority = TaskPriority::Normal;
        }

        $task = new SupervisorTask();
        $task->setSupervisor($supervisor);
        $task->setTitle($title);
        $task->setDescription($description ?: null);
        $task->setStatus(TaskStatus::Pending);
        $task->setPriority($priority);
        $task->setAssignedBy($this->getUser());
        if ($dueDateStr) {
            $task->setDueDate(new DateTimeImmutable($dueDateStr));
        }

        $em->persist($task);
        $em->flush();

        // B-18: notify supervisor
        $this->sendNotification(
            $mailer,
            $supervisor->getUser()->getEmail(),
            'New Task Assigned — UIU Hostel',
            sprintf(
                "Dear %s,\n\nA new task has been assigned to you:\n\nTitle: %s\nPriority: %s\nDue: %s\nDescription: %s\n\nPlease log in to view and manage your tasks.\n\nUIU Hostel Administration",
                $supervisor->getUser()->getName(),
                $title,
                $priority->value,
                $dueDateStr ?: 'No deadline',
                $description ?: 'N/A',
            )
        );

        // B-20: audit log
        $this->logAudit($em, 'task.create', [
            'taskTitle'      => $title,
            'supervisorName' => $supervisor->getUser()->getName(),
            'priority'       => $priority->value,
        ]);

        $this->addFlash('success', "Task \"{$title}\" assigned to " . $supervisor->getUser()->getName() . '.');
        return $this->redirectToRoute('admin_tasks');
    }

    #[Route('/tasks/{id}/delete', name: 'admin_tasks_delete', methods: ['POST'])]
    public function tasksDelete(int $id, EntityManagerInterface $em): Response
    {
        $task = $em->getRepository(SupervisorTask::class)->find($id);
        if ($task) {
            $taskTitle = $task->getTitle();
            $em->remove($task);
            $em->flush();
            $this->logAudit($em, 'task.delete', ['taskTitle' => $taskTitle]);
            $this->addFlash('success', 'Task removed.');
        }
        return $this->redirectToRoute('admin_tasks');
    }

    // ─── Admission Requests ───────────────────────────────────────────────────

    #[Route('/admission-requests', name: 'admin_admission_requests')]
    public function admissionRequests(): Response
    {
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admission-requests/{id}/approve', name: 'admin_admission_approve', methods: ['POST'])]
    public function admissionApprove(
        int $id,
        Request $request,
        AdmissionRequestRepository $repo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        // B-09: validate CSRF token
        if (!$this->isCsrfTokenValid('admission_approve_' . $id, $request->request->get('_csrf_token', $request->request->get('_token')))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $admRequest = $repo->find($id);
        if (!$admRequest) {
            $this->addFlash('error', 'Request not found.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $admRequest->setStatus(RequestStatus::Approved);
        $admRequest->setReviewedBy($this->getUser());
        $admRequest->setReviewedAt(new DateTimeImmutable());
        $admRequest->setAdminNotes($this->getAdminNote($this->getUser()));

        $student = $admRequest->getStudent();
        $student->setAdmissionStatus(AdmissionStatus::Approved);
        $student->setAdmissionDate(new DateTimeImmutable());

        $em->flush();

        // B-18: notify student
        $this->sendNotification(
            $mailer,
            $student->getUser()->getEmail(),
            'Admission Approved — UIU Hostel',
            sprintf(
                "Dear %s,\n\nCongratulations! Your hostel admission application has been APPROVED.\n\nPlease log in to your account to complete the room assignment process.\n\nUIU Hostel Administration",
                $student->getUser()->getName()
            )
        );

        // B-20: audit log
        $this->logAudit($em, 'admission.approve', [
            'studentName' => $student->getUser()->getName(),
            'requestId'   => $admRequest->getId(),
        ]);

        $this->addFlash('success', $student->getUser()->getName() . '\'s admission has been approved!');
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admission-requests/{id}/reject', name: 'admin_admission_reject', methods: ['POST'])]
    public function admissionReject(
        int $id,
        Request $request,
        AdmissionRequestRepository $repo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        $admRequest = $repo->find($id);
        if (!$admRequest) {
            $this->addFlash('error', 'Request not found.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $notes = $request->request->get('notes') ?: null;

        $admRequest->setStatus(RequestStatus::Rejected);
        $admRequest->setReviewedBy($this->getUser());
        $admRequest->setReviewedAt(new DateTimeImmutable());
        $admRequest->setAdminNotes($notes);

        $student = $admRequest->getStudent();
        $student->setAdmissionStatus(AdmissionStatus::Rejected);

        $em->flush();

        // B-18: notify student
        $this->sendNotification(
            $mailer,
            $student->getUser()->getEmail(),
            'Admission Decision — UIU Hostel',
            sprintf(
                "Dear %s,\n\nWe regret to inform you that your hostel admission application has been REJECTED.\n\n%s\n\nIf you have questions, please contact the hostel office.\n\nUIU Hostel Administration",
                $student->getUser()->getName(),
                $notes ? "Reason: {$notes}" : ''
            )
        );

        // B-20: audit log
        $this->logAudit($em, 'admission.reject', [
            'studentName' => $student->getUser()->getName(),
            'requestId'   => $admRequest->getId(),
            'notes'       => $notes,
        ]);

        $this->addFlash('success', $student->getUser()->getName() . '\'s admission has been rejected.');
        return $this->redirectToRoute('admin_dashboard');
    }

    // ─── Complaints ───────────────────────────────────────────────────────────

    #[Route('/complaints', name: 'admin_complaints')]
    public function complaints(
        Request $request,
        ComplaintRepository $complaintRepository,
        RepairCostRepository $repairCostRepository,
    ): Response {
        return $this->redirectToRoute('admin_reports', $request->query->all());
    }

    // B-03: New complaint update endpoint — persists status + logs repair cost
    #[Route('/complaints/{id}/update', name: 'admin_complaint_update', methods: ['POST'])]
    public function complaintUpdate(
        int $id,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->addFlash('error', 'Admins can only track complaints and costs. Supervisor updates are required.');
        return $this->redirectToRoute('admin_complaints');

        /*
        // CSRF validation
        if (!$this->isCsrfTokenValid('complaint_update_' . $id, $request->request->get('_csrf_token', $request->request->get('_token')))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_complaints');
        }

        $complaint = $em->getRepository(Complaint::class)->find($id);
        if (!$complaint) {
            $this->addFlash('error', 'Complaint not found.');
            return $this->redirectToRoute('admin_complaints');
        }

        // Update status
        $newStatusStr = $request->request->get('status', '');
        $statusMap = [
            'Pending'     => ComplaintStatus::Pending,
            'In Progress' => ComplaintStatus::InProgress,
            'Resolved'    => ComplaintStatus::Resolved,
        ];

        if (isset($statusMap[$newStatusStr])) {
            $oldStatus = $complaint->getStatusEnum();
            $newStatus = $statusMap[$newStatusStr];
            if ($oldStatus !== $newStatus) {
                $complaint->setStatus($newStatus);
                if ($newStatus === ComplaintStatus::Resolved) {
                    $complaint->setResolvedAt(new DateTimeImmutable());
                } else {
                    $complaint->setResolvedAt(null);
                }
                // Create a ComplaintUpdate record
                $update = new ComplaintUpdate();
                $update->setComplaint($complaint);
                $update->setStatus($newStatus);
                $update->setUpdatedBy($this->getUser());
                $notes = trim((string) $request->request->get('notes', ''));
                $update->setNote($notes ?: null);
                $em->persist($update);
            }
        }

        // B-12: log repair cost if provided and valid
        $amountRaw = $request->request->get('amount', '');
        if ($amountRaw !== '' && $amountRaw !== null) {
            if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
                $this->addFlash('error', 'Repair cost amount must be a positive number.');
                return $this->redirectToRoute('admin_complaints');
            }
            $repairCost = new RepairCost();
            $repairCost->setComplaint($complaint);
            $repairCost->setAmount((string) $amountRaw);
            $repairCost->setDescription(trim((string) $request->request->get('notes', '')) ?: null);
            $repairCost->setCostDate(new DateTimeImmutable());
            $repairCost->setRecordedBy($this->getUser());
            $em->persist($repairCost);
        }

        $em->flush();

        // B-20: audit log
        $this->logAudit($em, 'complaint.update', [
            'complaintId' => $id,
            'newStatus'   => $newStatusStr,
            'amount'      => $amountRaw ?: null,
        ]);

        $this->addFlash('success', 'Complaint #CMP-' . $id . ' updated successfully.');
        return $this->redirectToRoute('admin_complaints');
        */
    }

    // ─── Reports ──────────────────────────────────────────────────────────────

    #[Route('/reports', name: 'admin_reports')]
    public function reports(
        Request $request,
        ComplaintRepository $complaintRepo,
        RepairCostRepository $repairCostRepo,
        RoomRepository $roomRepo,
    ): Response {
        $countByCategory        = $complaintRepo->findCountByCategory();
        $countByCategoryStatus  = $complaintRepo->findCountByCategoryAndStatus();
        $costByCategory         = $repairCostRepo->findTotalByCategory();
        $grandTotal             = $repairCostRepo->findGrandTotal();
        $rooms                  = $roomRepo->findAll();

        $occupiedRooms = 0;
        $fullRooms = 0;
        foreach ($rooms as $room) {
            if ($room->getActualOccupancy() > 0) {
                $occupiedRooms++;
                if ($room->isFull()) {
                    $fullRooms++;
                }
            }
        }
        $vacantRooms = count($rooms) - $occupiedRooms;
        $occupancyRate = count($rooms) > 0 ? round(($occupiedRooms / count($rooms)) * 100, 1) : 0.0;

        $complaintCounts = [
            'total' => count($complaintRepo->findAll()),
            'pending' => $complaintRepo->countByStatus(ComplaintStatus::Pending),
            'inProgress' => $complaintRepo->countByStatus(ComplaintStatus::InProgress),
            'resolved' => $complaintRepo->countByStatus(ComplaintStatus::Resolved),
        ];

        $categoryStats = [];
        foreach (ComplaintCategory::cases() as $cat) {
            $key = $cat->value;
            $categoryStats[$key] = [
                'label'    => ucfirst($key),
                'category' => $cat,
                'total'    => $countByCategory[$key] ?? 0,
                'resolved' => 0,
                'pending'  => 0,
                'cost'     => $costByCategory[$key] ?? 0.0,
            ];
        }
        foreach ($countByCategoryStatus as $row) {
            $key    = $row['category'];
            $status = $row['status'];
            $total  = (int) $row['total'];
            if (!isset($categoryStats[$key])) {
                continue;
            }
            if ($status === 'resolved') {
                $categoryStats[$key]['resolved'] += $total;
            } else {
                $categoryStats[$key]['pending'] += $total;
            }
        }

        $monthLabel = (new DateTimeImmutable())->format('F Y');

        // Complaint tracking filters are now integrated into this single module.
        $activeType   = (string) $request->query->get('type', '');
        $activeStatus = (string) $request->query->get('status', '');
        $activeFrom   = (string) $request->query->get('from', '');
        $activeTo     = (string) $request->query->get('to', '');

        $fromDate = $activeFrom ? new DateTimeImmutable($activeFrom . ' 00:00:00') : null;
        $toDate   = $activeTo   ? new DateTimeImmutable($activeTo   . ' 23:59:59') : null;

        $complaints = $complaintRepo->findFiltered(
            $activeType ?: null,
            $activeStatus ?: null,
            $fromDate,
            $toDate
        );

        $startOfMonth     = new DateTimeImmutable('first day of this month midnight');
        $startOfNextMonth = $startOfMonth->modify('first day of next month midnight');
        $resolvedThisMonth   = $complaintRepo->countResolvedBetween($startOfMonth, $startOfNextMonth);
        $totalSpentThisMonth = $repairCostRepo->findTotalBetween($startOfMonth, $startOfNextMonth);

        return $this->render('admin/reports.html.twig', [
            'categoryStats' => $categoryStats,
            'grandTotal'    => $grandTotal,
            'monthLabel'    => $monthLabel,
            'roomStats'     => [
                'total' => count($rooms),
                'occupied' => $occupiedRooms,
                'vacant' => $vacantRooms,
                'full' => $fullRooms,
                'occupancyRate' => $occupancyRate,
            ],
            'complaintCounts' => $complaintCounts,
            'complaints'          => $complaints,
            'resolvedThisMonth'   => $resolvedThisMonth,
            'totalSpentThisMonth' => $totalSpentThisMonth,
            'activeType'          => $activeType,
            'activeStatus'        => $activeStatus,
            'activeFrom'          => $activeFrom,
            'activeTo'            => $activeTo,
            'complaintCategories' => ComplaintCategory::cases(),
        ]);
    }

    // ─── Repair Costs ─────────────────────────────────────────────────────────

    #[Route('/repair-costs/new', name: 'admin_repair_cost_new', methods: ['POST'])]
    public function repairCostNew(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $this->addFlash('error', 'Admins can only track costs. Supervisors must record repair costs.');
        return $this->redirectToRoute('admin_complaints');

        /*
        $complaintId  = (int) $request->request->get('complaintId');
        $amount       = $request->request->get('amount');
        $description  = trim((string) $request->request->get('description', ''));
        $costDateStr  = $request->request->get('costDate', date('Y-m-d'));

        // B-12: validate amount is numeric and positive
        if (!is_numeric($amount) || (float)$amount <= 0) {
            $this->addFlash('error', 'Amount must be a positive number.');
            return $this->redirectToRoute('admin_complaints');
        }

        $complaint = $em->getRepository(Complaint::class)->find($complaintId);
        if (!$complaint) {
            $this->addFlash('error', 'Invalid complaint.');
            return $this->redirectToRoute('admin_complaints');
        }

        $repairCost = new RepairCost();
        $repairCost->setComplaint($complaint);
        $repairCost->setAmount((string) $amount);
        $repairCost->setDescription($description ?: null);
        $repairCost->setCostDate(new DateTimeImmutable($costDateStr));
        $repairCost->setRecordedBy($this->getUser());

        $em->persist($repairCost);
        $em->flush();

        // B-20: audit log
        $this->logAudit($em, 'repair_cost.create', [
            'complaintId' => $complaintId,
            'amount'      => $amount,
        ]);

        $this->addFlash('success', 'Repair cost of ৳' . number_format((float)$amount, 2) . ' recorded.');
        return $this->redirectToRoute('admin_complaints');
        */
    }

    #[Route('/repair-costs/{id}/delete', name: 'admin_repair_cost_delete', methods: ['POST'])]
    public function repairCostDelete(int $id, EntityManagerInterface $em): Response
    {
        $this->addFlash('error', 'Admins can only track costs. Supervisors must manage repair cost entries.');
        return $this->redirectToRoute('admin_complaints');

        /*
        $rc = $em->getRepository(RepairCost::class)->find($id);
        if ($rc) {
            $amount = $rc->getAmount();
            $em->remove($rc);
            $em->flush();
            // B-20: audit log
            $this->logAudit($em, 'repair_cost.delete', ['id' => $id, 'amount' => $amount]);
            $this->addFlash('success', 'Repair cost entry removed.');
        }
        return $this->redirectToRoute('admin_complaints');
        */
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getAdminNote(?User $admin): ?string
    {
        return $admin ? 'Approved by ' . $admin->getName() : null;
    }

    /**
     * B-20: Persist an AuditLog entry for any critical admin action.
     */
    private function logAudit(EntityManagerInterface $em, string $action, ?array $context = null): void
    {
        /** @var User|null $admin */
        $admin = $this->getUser();
        $log   = new AuditLog($action, $admin instanceof User ? $admin : null, $context);
        $em->persist($log);
        $em->flush();
    }

    /**
     * B-18: Send a plain-text notification email.
     * Failures are silently swallowed so a mailer misconfiguration
     * never blocks an admin action.
     */
    private function sendNotification(MailerInterface $mailer, string $to, string $subject, string $body): void
    {
        try {
            $email = (new Email())
                ->from('noreply@uiu-hostel.edu')
                ->to($to)
                ->subject($subject)
                ->text($body);
            $mailer->send($email);
        } catch (\Throwable) {
            // Silently ignore mailer failures — do NOT block admin operations
        }
    }
}
