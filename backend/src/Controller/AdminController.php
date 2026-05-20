<?php

namespace App\Controller;

use App\Entity\Room;
use App\Entity\RoomAssignment;
use App\Entity\Supervisor;
use App\Entity\SupervisorTask;
use App\Entity\User;
use App\Enum\AdmissionStatus;
use App\Enum\AssignmentStatus;
use App\Enum\RequestStatus;
use App\Enum\Role;
use App\Enum\RoomStatus;
use App\Enum\TaskStatus;
use App\Repository\AdmissionRequestRepository;
use App\Repository\ComplaintRepository;
use App\Repository\ReportRepository;
use App\Repository\RoomChangeRequestRepository;
use App\Repository\RoomRepository;
use App\Repository\StudentRepository;
use App\Repository\SupervisorRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    ): Response {
        $rooms = $roomRepo->findAll();
        $occupied = 0;
        $vacant   = 0;
        foreach ($rooms as $room) {
            if ($room->getCurrentOccupancy() >= $room->getCapacity()) {
                $occupied++;
            } else {
                $vacant++;
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'totalRooms'       => count($rooms),
            'occupiedRooms'    => $occupied,
            'vacantRooms'      => $vacant,
            'totalStudents'    => count($studentRepo->findAll()),
            'pendingComplaints'=> count($complaintRepo->findBy(['status' => \App\Enum\ComplaintStatus::Pending])),
            'pendingAdmissions'=> count($admissionRepo->findPending()),
        ]);
    }

    // ─── Students ─────────────────────────────────────────────────────────────

    #[Route('/students', name: 'admin_students')]
    public function students(StudentRepository $repo): Response
    {
        return $this->render('admin/students.html.twig', [
            'students' => $repo->findAll(),
        ]);
    }

    // ─── Rooms ────────────────────────────────────────────────────────────────

    #[Route('/rooms', name: 'admin_rooms')]
    public function rooms(RoomRepository $repo): Response
    {
        return $this->render('admin/rooms.html.twig', [
            'rooms' => $repo->findAll(),
        ]);
    }

    #[Route('/rooms/new', name: 'admin_rooms_new', methods: ['POST'])]
    public function roomsNew(Request $request, EntityManagerInterface $em): Response
    {
        $room = new Room();
        $room->setRoomNumber((string) $request->request->get('roomNumber'));
        $room->setBlock((string) $request->request->get('block', 'A'));
        $room->setFloor((int) $request->request->get('floor', 1));
        $room->setCapacity((int) $request->request->get('capacity', 2));
        $room->setRoomType($request->request->get('roomType') ?: null);
        $room->setStatus(RoomStatus::Available);

        $em->persist($room);
        $em->flush();

        $this->addFlash('success', 'Room ' . $room->getRoomNumber() . ' created successfully!');
        return $this->redirectToRoute('admin_rooms');
    }

    #[Route('/rooms/{id}/delete', name: 'admin_rooms_delete', methods: ['POST'])]
    public function roomsDelete(int $id, EntityManagerInterface $em, RoomRepository $repo): Response
    {
        $room = $repo->find($id);
        if ($room && $room->getCurrentOccupancy() === 0) {
            $em->remove($room);
            $em->flush();
            $this->addFlash('success', 'Room deleted.');
        } else {
            $this->addFlash('error', 'Cannot delete an occupied room.');
        }
        return $this->redirectToRoute('admin_rooms');
    }

    // ─── Room Assignment ──────────────────────────────────────────────────────

    #[Route('/room-assign', name: 'admin_room_assign')]
    public function roomAssign(
        StudentRepository $studentRepo,
        RoomRepository $roomRepo,
        EntityManagerInterface $em,
    ): Response {
        // Students who are approved but have no active room assignment
        $approvedStudents = array_filter($studentRepo->findAll(), fn($s) => $s->getAdmissionStatus() === AdmissionStatus::Approved && $s->getRoom() === null);
        $availableRooms   = array_filter($roomRepo->findAll(), fn($r) => $r->getCurrentOccupancy() < $r->getCapacity());

        // All active assignments for the table
        $allAssignments = $em->getRepository(RoomAssignment::class)->findBy(['status' => AssignmentStatus::Active]);

        return $this->render('admin/room-assign.html.twig', [
            'approvedStudents' => array_values($approvedStudents),
            'availableRooms'   => array_values($availableRooms),
            'assignments'      => $allAssignments,
        ]);
    }

    #[Route('/room-assign/new', name: 'admin_room_assign_new', methods: ['POST'])]
    public function roomAssignNew(
        Request $request,
        StudentRepository $studentRepo,
        RoomRepository $roomRepo,
        EntityManagerInterface $em,
    ): Response {
        $studentId = (int) $request->request->get('studentId');
        $roomId    = (int) $request->request->get('roomId');

        $student = $studentRepo->find($studentId);
        $room    = $roomRepo->find($roomId);

        if (!$student || !$room) {
            $this->addFlash('error', 'Invalid student or room selection.');
            return $this->redirectToRoute('admin_room_assign');
        }

        if ($room->getCurrentOccupancy() >= $room->getCapacity()) {
            $this->addFlash('error', 'Selected room is already full.');
            return $this->redirectToRoute('admin_room_assign');
        }

        // Deactivate any existing assignment
        foreach ($student->getRoomAssignments() as $existing) {
            if ($existing->getStatus() === AssignmentStatus::Active) {
                $existing->setStatus(AssignmentStatus::Vacated);
                $existing->setVacatedDate(new DateTimeImmutable());
                $existing->getRoom()->setCurrentOccupancy(max(0, $existing->getRoom()->getCurrentOccupancy() - 1));
            }
        }

        // Create new assignment
        $assignment = new RoomAssignment();
        $assignment->setStudent($student);
        $assignment->setRoom($room);
        $assignment->setAssignedDate(new DateTimeImmutable());
        $assignment->setStatus(AssignmentStatus::Active);

        $room->setCurrentOccupancy($room->getCurrentOccupancy() + 1);
        if ($room->getCurrentOccupancy() >= $room->getCapacity()) {
            $room->setStatus(RoomStatus::Full);
        }

        $em->persist($assignment);
        $em->flush();

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
            $room = $assignment->getRoom();
            $room->setCurrentOccupancy(max(0, $room->getCurrentOccupancy() - 1));
            $room->setStatus(RoomStatus::Available);
            $em->flush();
            $this->addFlash('success', 'Room assignment revoked.');
        }
        return $this->redirectToRoute('admin_room_assign');
    }

    // ─── Supervisors ──────────────────────────────────────────────────────────

    #[Route('/supervisors', name: 'admin_supervisors')]
    public function supervisors(SupervisorRepository $repo): Response
    {
        return $this->render('admin/supervisors.html.twig', [
            'supervisors' => $repo->findAll(),
        ]);
    }

    #[Route('/supervisors/new', name: 'admin_supervisors_new', methods: ['POST'])]
    public function supervisorsNew(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $name  = trim((string) $request->request->get('name'));
        $email = trim((string) $request->request->get('email'));
        $phone = trim((string) $request->request->get('phone'));
        $block = trim((string) $request->request->get('block'));
        $pass  = (string) $request->request->get('password', 'HostelSup@123');

        if (!$name || !$email) {
            $this->addFlash('error', 'Name and email are required.');
            return $this->redirectToRoute('admin_supervisors');
        }

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
        $user->setPasswordHash($hasher->hashPassword($user, $pass));

        $supervisor = new Supervisor();
        $supervisor->setUser($user);
        $supervisor->setPhone($phone ?: null);
        $supervisor->setBlockAssigned($block ?: null);

        $em->persist($user);
        $em->persist($supervisor);
        $em->flush();

        $this->addFlash('success', "Supervisor {$name} created. Default password: {$pass}");
        return $this->redirectToRoute('admin_supervisors');
    }

    #[Route('/supervisors/{id}/delete', name: 'admin_supervisors_delete', methods: ['POST'])]
    public function supervisorsDelete(int $id, SupervisorRepository $repo, EntityManagerInterface $em): Response
    {
        $supervisor = $repo->find($id);
        if ($supervisor) {
            $user = $supervisor->getUser();
            $em->remove($user); // CASCADE removes supervisor
            $em->flush();
            $this->addFlash('success', 'Supervisor removed.');
        }
        return $this->redirectToRoute('admin_supervisors');
    }

    // ─── Tasks ────────────────────────────────────────────────────────────────

    #[Route('/tasks/new', name: 'admin_tasks_new', methods: ['POST'])]
    public function tasksNew(
        Request $request,
        SupervisorRepository $supRepo,
        EntityManagerInterface $em,
    ): Response {
        $supervisorId = (int) $request->request->get('supervisorId');
        $title        = trim((string) $request->request->get('title'));
        $description  = trim((string) $request->request->get('description', ''));
        $dueDateStr   = $request->request->get('dueDate');

        $supervisor = $supRepo->find($supervisorId);
        if (!$supervisor || !$title) {
            $this->addFlash('error', 'Supervisor and task title are required.');
            return $this->redirectToRoute('admin_supervisors');
        }

        $task = new SupervisorTask();
        $task->setSupervisor($supervisor);
        $task->setTitle($title);
        $task->setDescription($description ?: null);
        $task->setStatus(TaskStatus::Pending);
        $task->setAssignedBy($this->getUser());
        if ($dueDateStr) {
            $task->setDueDate(new DateTimeImmutable($dueDateStr));
        }

        $em->persist($task);
        $em->flush();

        $this->addFlash('success', "Task \"{$title}\" assigned to " . $supervisor->getUser()->getName() . '.');
        return $this->redirectToRoute('admin_supervisors');
    }

    // ─── Admission Requests (HP-2) ────────────────────────────────────────────

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
        AdmissionRequestRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        $admRequest = $repo->find($id);
        if (!$admRequest) {
            $this->addFlash('error', 'Request not found.');
            return $this->redirectToRoute('admin_admission_requests');
        }

        $admRequest->setStatus(RequestStatus::Approved);
        $admRequest->setReviewedBy($this->getUser());
        $admRequest->setReviewedAt(new DateTimeImmutable());
        $admRequest->setAdminNotes($this->getAdminNote($this->getUser()));

        // Update Student admission status
        $student = $admRequest->getStudent();
        $student->setAdmissionStatus(AdmissionStatus::Approved);
        $student->setAdmissionDate(new DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', $student->getUser()->getName() . '\'s admission has been approved!');
        return $this->redirectToRoute('admin_admission_requests');
    }

    #[Route('/admission-requests/{id}/reject', name: 'admin_admission_reject', methods: ['POST'])]
    public function admissionReject(
        int $id,
        Request $request,
        AdmissionRequestRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        $admRequest = $repo->find($id);
        if (!$admRequest) {
            $this->addFlash('error', 'Request not found.');
            return $this->redirectToRoute('admin_admission_requests');
        }

        $admRequest->setStatus(RequestStatus::Rejected);
        $admRequest->setReviewedBy($this->getUser());
        $admRequest->setReviewedAt(new DateTimeImmutable());
        $admRequest->setAdminNotes($request->request->get('notes') ?: null);

        $student = $admRequest->getStudent();
        $student->setAdmissionStatus(AdmissionStatus::Rejected);

        $em->flush();

        $this->addFlash('success', $student->getUser()->getName() . '\'s admission has been rejected.');
        return $this->redirectToRoute('admin_admission_requests');
    }

    // ─── Complaints ───────────────────────────────────────────────────────────

    #[Route('/complaints', name: 'admin_complaints')]
    public function complaints(ComplaintRepository $repo): Response
    {
        return $this->render('admin/complaints.html.twig', [
            'complaints' => $repo->findAll(),
        ]);
    }

    // ─── Reports ──────────────────────────────────────────────────────────────

    #[Route('/reports', name: 'admin_reports')]
    public function reports(ReportRepository $repo): Response
    {
        return $this->render('admin/reports.html.twig', [
            'reports' => $repo->findAll(),
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getAdminNote(?User $admin): ?string
    {
        return $admin ? 'Approved by ' . $admin->getName() : null;
    }
}
