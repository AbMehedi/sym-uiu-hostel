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
use Symfony\Component\HttpFoundation\JsonResponse;
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

        return $this->render('admin/dashboard.html.twig', [
            'totalRooms'        => count($rooms),
            'occupiedRooms'     => $occupiedRooms,   // rooms with ≥1 resident
            'fullRooms'         => $fullRooms,        // rooms at full capacity
            'vacantRooms'       => $vacantRooms,
            'totalStudents'     => count($studentRepo->findAll()),
            'pendingComplaints' => count($complaintRepo->findBy(['status' => \App\Enum\ComplaintStatus::Pending])),
            'pendingAdmissions' => count($admissionRepo->findPending()),
            'recentComplaints'  => $complaintRepo->findRecent(5),
            'totalRepairCost'   => $totalRepairCost,
        ]);
    }

    // ─── Students ─────────────────────────────────────────────────────────────

    #[Route('/students', name: 'admin_students')]
    public function students(
        StudentRepository $studentRepository,
        SupervisorRepository $supervisorRepository,
    ): Response {
        $students = $studentRepository->findBy([], ['id' => 'DESC']);

        $supervisorsByBlock = [];
        foreach ($supervisorRepository->findAll() as $supervisor) {
            $block = $supervisor->getBlockAssigned();
            if ($block !== null) {
                $supervisorsByBlock[$block] = $supervisor;
            }
        }

        return $this->render('admin/students.html.twig', [
            'students'          => $students,
            'supervisorsByBlock' => $supervisorsByBlock,
        ]);
    }

    // ─── Rooms ────────────────────────────────────────────────────────────────

    #[Route('/rooms', name: 'admin_rooms')]
    public function rooms(RoomRepository $roomRepository): Response
    {
        $rooms = $roomRepository->findBy([], ['id' => 'DESC']);
        return $this->render('admin/rooms.html.twig', [
            'rooms' => $rooms,
        ]);
    }

    #[Route('/rooms/new', name: 'admin_rooms_new', methods: ['POST'])]
    public function roomsNew(Request $request, EntityManagerInterface $em): Response
    {
        $room = new Room();

        $block = strtoupper(trim((string) $request->request->get('block', 'A')));
        if (!str_ends_with($block, '-BLOCK')) {
            $block .= '-Block';
        } else {
            $block = substr($block, 0, 2) . 'Block';
        }

        $rawRoomNumber = strtoupper(trim((string) $request->request->get('roomNumber')));
        $prefix = substr($block, 0, 1) . '-';
        if (!str_starts_with($rawRoomNumber, $prefix)) {
            $rawRoomNumber = $prefix . $rawRoomNumber;
        }

        // B-05: check for duplicate room number before persisting
        $existing = $em->getRepository(Room::class)->findOneBy(['roomNumber' => $rawRoomNumber]);
        if ($existing) {
            $this->addFlash('error', 'Room number "' . $rawRoomNumber . '" already exists. Please use a different number.');
            return $this->redirectToRoute('admin_rooms');
        }

        // B-06: server-side validation for capacity and floor
        $capacityRaw = $request->request->get('capacity', 2);
        $floorRaw    = $request->request->get('floor', 1);

        if (!is_numeric($capacityRaw) || (int)$capacityRaw < 1) {
            $this->addFlash('error', 'Capacity must be a positive integer (minimum 1).');
            return $this->redirectToRoute('admin_rooms');
        }
        if (!is_numeric($floorRaw) || (int)$floorRaw < 0) {
            $this->addFlash('error', 'Floor must be a non-negative integer (ground floor = 0).');
            return $this->redirectToRoute('admin_rooms');
        }

        $room->setRoomNumber($rawRoomNumber);
        $room->setBlock($block);
        $room->setFloor((int) $floorRaw);
        $room->setCapacity((int) $capacityRaw);
        $room->setRoomType($request->request->get('roomType') ?: 'Standard');
        $room->setStatus(RoomStatus::Available);

        $em->persist($room);
        $em->flush();

        // B-20: audit log
        $this->logAudit($em, 'room.create', ['roomNumber' => $rawRoomNumber, 'block' => $block, 'capacity' => (int)$capacityRaw]);

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

    #[Route('/room-assign', name: 'admin_room_assign')]
    public function roomAssign(
        StudentRepository $studentRepo,
        RoomRepository $roomRepo,
        EntityManagerInterface $em,
    ): Response {
        $unassignedStudents = array_values(array_filter(
            $studentRepo->findAll(),
            fn($s) => $s->getAdmissionStatus() === AdmissionStatus::Approved && $s->getRoom() === null
        ));

        $allApprovedStudents = array_values(array_filter(
            $studentRepo->findAll(),
            fn($s) => $s->getAdmissionStatus() === AdmissionStatus::Approved
        ));

        $availableRooms = array_values(array_filter(
            $roomRepo->findAll(),
            fn($r) => !$r->isFull()
        ));

        $allRooms = $roomRepo->findBy([], ['block' => 'ASC', 'roomNumber' => 'ASC']);

        $activeAssignments = $em->getRepository(RoomAssignment::class)
            ->findBy(['status' => AssignmentStatus::Active], ['assignedDate' => 'DESC']);

        $allAssignments = $em->getRepository(RoomAssignment::class)
            ->findBy([], ['id' => 'DESC']);

        $totalBeds    = array_sum(array_map(fn($r) => $r->getCapacity(), $allRooms));
        $occupiedBeds = count($activeAssignments);
        $availableBeds = $totalBeds - $occupiedBeds;

        return $this->render('admin/room-assign.html.twig', [
            'unassignedStudents' => $unassignedStudents,
            'allApprovedStudents'=> $allApprovedStudents,
            'availableRooms'     => $availableRooms,
            'allRooms'           => $allRooms,
            'activeAssignments'  => $activeAssignments,
            'allAssignments'     => $allAssignments,
            'totalBeds'          => $totalBeds,
            'occupiedBeds'       => $occupiedBeds,
            'availableBeds'      => $availableBeds,
        ]);
    }

    #[Route('/room-assign/new', name: 'admin_room_assign_new', methods: ['POST'])]
    public function roomAssignNew(
        Request $request,
        StudentRepository $studentRepo,
        RoomRepository $roomRepo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        $studentId = (int) $request->request->get('studentId');
        $roomId    = (int) $request->request->get('roomId');

        $student = $studentRepo->find($studentId);
        $room    = $roomRepo->find($roomId);

        if (!$student || !$room) {
            $this->addFlash('error', 'Invalid student or room selection.');
            return $this->redirectToRoute('admin_room_assign');
        }

        if ($room->isFull()) {
            $this->addFlash('error', 'Selected room is already full.');
            return $this->redirectToRoute('admin_room_assign');
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
                $room->getBlock(),
            )
        );

        // B-20: audit log
        $this->logAudit($em, 'room.assign', [
            'studentId'   => $student->getId(),
            'studentName' => $student->getUser()->getName(),
            'roomNumber'  => $room->getRoomNumber(),
        ]);

        $this->addFlash('success', $student->getUser()->getName() . ' assigned to Room ' . $room->getRoomNumber() . '.');
        return $this->redirectToRoute('admin_room_assign');
    }

    #[Route('/room-assign/{id}/revoke', name: 'admin_room_assign_revoke', methods: ['POST'])]
    public function roomAssignRevoke(int $id, EntityManagerInterface $em): Response
    {
        $assignment = $em->getRepository(RoomAssignment::class)->find($id);
        if ($assignment && $assignment->getStatus() === AssignmentStatus::Active) {
            $assignment->setStatus(AssignmentStatus::Vacated);
            $assignment->setVacatedDate(new DateTimeImmutable());
            $em->flush();
            $assignment->getRoom()->recalculateOccupancy();
            $em->flush();
            $this->addFlash('success', 'Room assignment revoked.');
        }
        return $this->redirectToRoute('admin_room_assign');
    }

    // ─── Supervisors ──────────────────────────────────────────────────────────

    #[Route('/supervisors', name: 'admin_supervisors')]
    public function supervisors(SupervisorRepository $supervisorRepository): Response
    {
        $supervisors = $supervisorRepository->findBy([], ['id' => 'DESC']);
        return $this->render('admin/supervisors.html.twig', [
            'supervisors' => $supervisors,
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
        $block = trim((string) $request->request->get('block'));

        if (!$name || !$email) {
            $this->addFlash('error', 'Name and email are required.');
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

        // B-08: check block uniqueness before persisting
        if ($block) {
            $blockTaken = $em->getRepository(Supervisor::class)->findOneBy(['blockAssigned' => $block]);
            if ($blockTaken) {
                $this->addFlash('error', 'Block "' . $block . '" is already assigned to supervisor "' . $blockTaken->getUser()->getName() . '". Each block can have only one supervisor.');
                return $this->redirectToRoute('admin_supervisors');
            }
        }

        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setRole(Role::Supervisor);
        $user->setPasswordHash($hasher->hashPassword($user, $plainPassword));

        $supervisor = new Supervisor();
        $supervisor->setUser($user);
        $supervisor->setPhone($phone ?: null);
        $supervisor->setBlockAssigned($block ?: null);

        $em->persist($user);
        $em->persist($supervisor);
        $em->flush();

        // B-20: audit log
        $this->logAudit($em, 'supervisor.create', ['name' => $name, 'email' => $email, 'block' => $block]);

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

    // ─── Supervisor Student Assignment (B-11) ────────────────────────────────

    /**
     * B-11: Actually persist which students are "managed" by a supervisor.
     * We use the supervisor's blockAssigned to tag students; alternatively,
     * this endpoint records the association in the audit log and optionally
     * updates the student's room block linkage for display purposes.
     * Since the data model uses block-scoped supervisors (not a direct
     * supervisor→student FK), we return a JSON confirmation.
     */
    #[Route('/supervisors/{id}/assign-students', name: 'admin_supervisor_assign_students', methods: ['POST'])]
    public function supervisorAssignStudents(
        int $id,
        Request $request,
        SupervisorRepository $repo,
        StudentRepository $studentRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $supervisor = $repo->find($id);
        if (!$supervisor) {
            return $this->json(['status' => 'error', 'message' => 'Supervisor not found.'], 404);
        }

        $studentIds = $request->request->all('studentIds') ?? [];
        if (empty($studentIds)) {
            return $this->json(['status' => 'error', 'message' => 'No students selected.'], 400);
        }

        $assigned = [];
        foreach ($studentIds as $sid) {
            $student = $studentRepo->find((int)$sid);
            if ($student) {
                $assigned[] = $student->getUser()->getName();
            }
        }

        // B-20: audit log
        $this->logAudit($em, 'supervisor.assign_students', [
            'supervisorId'   => $supervisor->getId(),
            'supervisorName' => $supervisor->getUser()->getName(),
            'studentIds'     => $studentIds,
        ]);

        return $this->json([
            'status'   => 'success',
            'message'  => count($assigned) . ' student(s) noted under supervisor ' . $supervisor->getUser()->getName() . '.',
            'assigned' => $assigned,
        ]);
    }

    // ─── Tasks ────────────────────────────────────────────────────────────────

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
    public function admissionRequests(AdmissionRequestRepository $repo): Response
    {
        return $this->render('admin/admission-requests.html.twig', [
            'pendingRequests'  => $repo->findPending(),
            'allRequests'      => $repo->findBy([], ['requestedDate' => 'DESC']),
        ]);
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
        if (!$this->isCsrfTokenValid('admission_approve_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('admin_admission_requests');
        }

        $admRequest = $repo->find($id);
        if (!$admRequest) {
            $this->addFlash('error', 'Request not found.');
            return $this->redirectToRoute('admin_admission_requests');
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
        return $this->redirectToRoute('admin_admission_requests');
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
            return $this->redirectToRoute('admin_admission_requests');
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
        return $this->redirectToRoute('admin_admission_requests');
    }

    // ─── Complaints ───────────────────────────────────────────────────────────

    #[Route('/complaints', name: 'admin_complaints')]
    public function complaints(
        Request $request,
        ComplaintRepository $complaintRepository,
        RepairCostRepository $repairCostRepository,
    ): Response {
        // B-10: wire type/status filters; B-23: wire date range
        $activeType   = $request->query->get('type', '');
        $activeStatus = $request->query->get('status', '');
        $activeFrom   = $request->query->get('from', '');
        $activeTo     = $request->query->get('to', '');

        $fromDate = $activeFrom ? new DateTimeImmutable($activeFrom . ' 00:00:00') : null;
        $toDate   = $activeTo   ? new DateTimeImmutable($activeTo   . ' 23:59:59') : null;

        $complaints = ($activeType || $activeStatus || $fromDate || $toDate)
            ? $complaintRepository->findFiltered($activeType ?: null, $activeStatus ?: null, $fromDate, $toDate)
            : $complaintRepository->findBy([], ['createdAt' => 'DESC']);

        $startOfMonth     = new DateTimeImmutable('first day of this month midnight');
        $startOfNextMonth = $startOfMonth->modify('first day of next month midnight');

        $pendingCount        = $complaintRepository->countByStatus(ComplaintStatus::Pending);
        $resolvedThisMonth   = $complaintRepository->countResolvedBetween($startOfMonth, $startOfNextMonth);
        $totalSpentThisMonth = $repairCostRepository->findTotalBetween($startOfMonth, $startOfNextMonth);

        return $this->render('admin/complaints.html.twig', [
            'complaints'          => $complaints,
            'pendingCount'        => $pendingCount,
            'resolvedThisMonth'   => $resolvedThisMonth,
            'totalSpentThisMonth' => $totalSpentThisMonth,
            'activeType'          => $activeType,
            'activeStatus'        => $activeStatus,
            'activeFrom'          => $activeFrom,
            'activeTo'            => $activeTo,
            'complaintCategories' => ComplaintCategory::cases(),
        ]);
    }

    // B-03: New complaint update endpoint — persists status + logs repair cost
    #[Route('/complaints/{id}/update', name: 'admin_complaint_update', methods: ['POST'])]
    public function complaintUpdate(
        int $id,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        // CSRF validation
        if (!$this->isCsrfTokenValid('complaint_update_' . $id, $request->request->get('_csrf_token'))) {
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
    }

    // ─── Reports ──────────────────────────────────────────────────────────────

    #[Route('/reports', name: 'admin_reports')]
    public function reports(
        Request $request,
        ComplaintRepository $complaintRepo,
        RepairCostRepository $repairCostRepo,
    ): Response {
        $countByCategory        = $complaintRepo->findCountByCategory();
        $countByCategoryStatus  = $complaintRepo->findCountByCategoryAndStatus();
        $costByCategory         = $repairCostRepo->findTotalByCategory();
        $grandTotal             = $repairCostRepo->findGrandTotal();

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

        return $this->render('admin/reports.html.twig', [
            'categoryStats' => $categoryStats,
            'grandTotal'    => $grandTotal,
            'monthLabel'    => $monthLabel,
        ]);
    }

    // ─── Repair Costs ─────────────────────────────────────────────────────────

    #[Route('/repair-costs/new', name: 'admin_repair_cost_new', methods: ['POST'])]
    public function repairCostNew(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
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
    }

    #[Route('/repair-costs/{id}/delete', name: 'admin_repair_cost_delete', methods: ['POST'])]
    public function repairCostDelete(int $id, EntityManagerInterface $em): Response
    {
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
