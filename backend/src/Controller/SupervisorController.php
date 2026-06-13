<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\ChatMessage;
use App\Entity\RepairCost;
use App\Entity\RoomAssignment;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintStatus;
use App\Enum\RequestStatus;
use App\Enum\RoomStatus;
use App\Enum\TaskStatus;
use App\Repository\AnnouncementRepository;
use App\Repository\ChatRepository;
use App\Repository\ComplaintRepository;
use App\Repository\RoomRepository;
use App\Repository\RoomChangeRequestRepository;
use App\Repository\StudentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/supervisor')]
#[IsGranted('ROLE_SUPERVISOR')]
class SupervisorController extends AbstractController
{
    // ─── Dashboard ────────────────────────────────────────────────────────────

    #[Route('/', name: 'supervisor_dashboard')]
    public function dashboard(
        StudentRepository $studentRepo,
        ComplaintRepository $complaintRepo,
        RoomChangeRequestRepository $roomChangeRepo,
        AnnouncementRepository $announcementRepo,
    ): Response {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();

        $pendingRequests     = $roomChangeRepo->findBy(['status' => RequestStatus::Pending], ['id' => 'DESC']);
        $recentComplaints    = $complaintRepo->findBy([], ['createdAt' => 'DESC'], 5);
        $recentAnnouncements = $announcementRepo->findBy([], ['createdAt' => 'DESC'], 3);

        // Resolved complaints in the current calendar month
        $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
        $monthEnd   = new DateTimeImmutable('last day of this month 23:59:59');
        $resolvedThisMonth = count($complaintRepo->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.resolvedAt BETWEEN :start AND :end')
            ->setParameter('status', ComplaintStatus::Resolved)
            ->setParameter('start', $monthStart)
            ->setParameter('end', $monthEnd)
            ->getQuery()
            ->getResult()
        );

        return $this->render('supervisor/dashboard.html.twig', [
            'totalStudents'        => count($studentRepo->findAll()),
            'pendingComplaints'    => count($complaintRepo->findBy(['status' => ComplaintStatus::Pending])),
            'inProgressComplaints' => count($complaintRepo->findBy(['status' => ComplaintStatus::InProgress])),
            'pendingRoomChanges'   => count($pendingRequests),
            'resolvedThisMonth'    => $resolvedThisMonth,
            'supervisor'           => $supervisor,
            'pendingRequests'      => $pendingRequests,
            'recentComplaints'     => $recentComplaints,
            'recentAnnouncements'  => $recentAnnouncements,
        ]);
    }

    // ─── Students ─────────────────────────────────────────────────────────────

    #[Route('/students', name: 'supervisor_students')]
    public function students(
        Request $request,
        StudentRepository $studentRepo,
        EntityManagerInterface $em
    ): Response {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();
        $hostel     = $supervisor?->getHostelAssigned() ?? '';

        // Students explicitly assigned to this supervisor
        $students = $supervisor ? $studentRepo->findBySupervisor($supervisor) : [];

        // Build list of unique room numbers in this hostel for the filter dropdown
        $rooms = [];
        foreach ($students as $student) {
            $room = $student->getRoom();
            if ($room && !isset($rooms[$room->getId()])) {
                $rooms[$room->getId()] = $room->getRoomNumber();
            }
        }
        asort($rooms);

        return $this->render('supervisor/students.html.twig', [
            'students'   => $students,
            'rooms'      => $rooms,          // [ roomId => roomNumber ] for filter dropdown
            'supervisor' => $supervisor,
            'hostel'     => $hostel,
        ]);
    }

    #[Route('/students/{id}', name: 'supervisor_student_detail', requirements: ['id' => '\d+'])]
    public function studentDetail(int $id, StudentRepository $studentRepo): Response
    {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();
        $hostel     = $supervisor?->getHostelAssigned() ?? '';

        $student = $studentRepo->find($id);

        // Guard: student must exist and must be in the supervisor's hostel
        if (!$student) {
            $this->addFlash('error', 'Student not found.');
            return $this->redirectToRoute('supervisor_students');
        }

        $room = $student->getRoom();
        if (!$room || $room->getHostel() !== $hostel) {
            $this->addFlash('error', 'Access denied — student is not in your hostel.');
            return $this->redirectToRoute('supervisor_students');
        }

        return $this->render('supervisor/student-detail.html.twig', [
            'student'    => $student,
            'room'       => $room,
            'supervisor' => $supervisor,
        ]);
    }

    // ─── Rooms ────────────────────────────────────────────────────────────────

    #[Route('/rooms', name: 'supervisor_rooms')]
    public function rooms(RoomRepository $repo): Response
    {
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();
        $hostel     = $supervisor?->getHostelAssigned() ?? '';

        if ($hostel) {
            $rooms = $repo->findBy(['hostel' => $hostel], ['roomNumber' => 'ASC']);
        } else {
            $rooms = $repo->findAll();
        }

        return $this->render('supervisor/rooms.html.twig', [
            'rooms' => $rooms,
            'hostel' => $hostel
        ]);
    }

    // ─── Complaints ───────────────────────────────────────────────────────────

    #[Route('/complaints', name: 'supervisor_complaints')]
    public function complaints(ComplaintRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();
        $hostel     = $supervisor?->getHostelAssigned() ?? '';

        // Scope complaints to rooms in the supervisor's hostel only
        if ($hostel) {
            $complaints = $repo->createQueryBuilder('c')
                ->join('c.room', 'r')
                ->where('r.hostel = :hostel')
                ->setParameter('hostel', $hostel)
                ->orderBy('c.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $complaints = $repo->findBy([], ['createdAt' => 'DESC']);
        }

        return $this->render('supervisor/complaints.html.twig', [
            'complaints' => $complaints,
        ]);
    }

    #[Route('/complaints/update/{id}', name: 'supervisor_complaint_update', methods: ['POST'])]
    public function updateComplaint(int $id, Request $request, ComplaintRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();

        $complaint = $repo->find($id);
        if (!$complaint) {
            return $this->json(['status' => 'error', 'message' => 'Complaint not found.'], 404);
        }

        if (!$supervisor) {
            return $this->json(['status' => 'error', 'message' => 'Supervisor profile not found.'], 403);
        }

        $hostel = $supervisor->getHostelAssigned() ?? '';
        if ($hostel !== '' && $complaint->getRoom()?->getHostel() !== $hostel) {
            return $this->json(['status' => 'error', 'message' => 'Access denied for this complaint.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            $data = [];
        }
        $newStatusStr = trim((string) ($data['status'] ?? ''));
        $notes        = trim((string) ($data['notes'] ?? ''));
        $amountRaw    = trim((string) ($data['amount'] ?? ''));

        $statusMap = [
            'Pending'     => ComplaintStatus::Pending,
            'In Progress' => ComplaintStatus::InProgress,
            'Resolved'    => ComplaintStatus::Resolved,
        ];

        if (!isset($statusMap[$newStatusStr])) {
            return $this->json(['status' => 'error', 'message' => 'Invalid complaint status.'], 400);
        }

        $newStatus = $statusMap[$newStatusStr];
        $statusChanged = $complaint->getStatusEnum() !== $newStatus;
        if ($statusChanged) {
            $complaint->setStatus($newStatus);
        }

        $complaint->setAssignedTo($supervisor);

        if (!empty($notes) || $statusChanged) {
            $update = new \App\Entity\ComplaintUpdate();
            $update->setComplaint($complaint);
            $update->setNote(!empty($notes) ? $notes : null);
            $update->setUpdatedBy($this->getUser());
            $update->setStatus($newStatus);
            $em->persist($update);
        }

        if ($newStatus === ComplaintStatus::Resolved) {
            $complaint->setResolvedAt(new DateTimeImmutable());
        } else {
            // Clear resolvedAt if reverting from Resolved back to Pending/InProgress
            $complaint->setResolvedAt(null);
        }

        if ($amountRaw !== '') {
            if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) {
                return $this->json(['status' => 'error', 'message' => 'Repair cost must be a positive number.'], 400);
            }

            $repairCost = new RepairCost();
            $repairCost->setComplaint($complaint);
            $repairCost->setAmount((string) $amountRaw);
            $repairCost->setDescription($notes !== '' ? $notes : null);
            $repairCost->setCostDate(new DateTimeImmutable());
            $repairCost->setRecordedBy($this->getUser());
            $em->persist($repairCost);
        }

        $em->flush();

        return $this->json([
            'status' => 'success',
            'totalCost' => number_format($complaint->getCost(), 2, '.', ''),
        ]);
    }

    // ─── Announcements ────────────────────────────────────────────────────────

    #[Route('/announcements', name: 'supervisor_announcements', methods: ['GET', 'POST'])]
    public function announcements(Request $request, AnnouncementRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();

        if ($request->isMethod('POST')) {
            $title    = $request->request->get('title');
            $category = $request->request->get('category');
            $body     = $request->request->get('body');

            $announcement = new Announcement();
            $announcement->setTitle($title);
            $announcement->setCategory($category);
            $announcement->setBody($body);
            $announcement->setSupervisor($supervisor);
            $announcement->setTargetBlock($supervisor ? $supervisor->getHostelAssigned() : 'General');

            $em->persist($announcement);
            $em->flush();

            $this->addFlash('success', 'Announcement posted successfully!');
            return $this->redirectToRoute('supervisor_announcements');
        }

        return $this->render('supervisor/announcements.html.twig', [
            'announcements' => $repo->createQueryBuilder('a')
                ->where('a.targetBlock = :hostel OR a.targetBlock = :general')
                ->setParameter('hostel', $supervisor?->getHostelAssigned() ?? '')
                ->setParameter('general', 'General')
                ->orderBy('a.createdAt', 'DESC')
                ->getQuery()
                ->getResult(),
        ]);
    }

    // ─── Room Change Requests ─────────────────────────────────────────────────

    #[Route('/room-changes', name: 'supervisor_room_changes')]
    public function roomChanges(RoomChangeRequestRepository $repo): Response
    {
        return $this->render('supervisor/room-changes.html.twig', [
            'pendingRequests' => $repo->findPending(),
            'allRequests'     => $repo->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/room-changes/{id}/approve', name: 'supervisor_room_change_approve', methods: ['POST'])]
    public function roomChangeApprove(int $id, RoomChangeRequestRepository $repo, EntityManagerInterface $em): Response
    {
        $rcRequest = $repo->find($id);
        if (!$rcRequest) {
            $this->addFlash('error', 'Request not found.');
            return $this->redirectToRoute('supervisor_room_changes');
        }

        /** @var \App\Entity\User $user */
        $user          = $this->getUser();
        $supervisor    = $user->getSupervisor();
        $student       = $rcRequest->getStudent();
        $requestedRoom = $rcRequest->getRequestedRoom();

        if ($requestedRoom->getActualOccupancy() >= $requestedRoom->getCapacity()) {
            $this->addFlash('error', 'The requested room is already full. Cannot approve.');
            return $this->redirectToRoute('supervisor_room_changes');
        }

        foreach ($student->getRoomAssignments() as $assignment) {
            if ($assignment->getStatus() === AssignmentStatus::Active) {
                $assignment->setStatus(AssignmentStatus::Vacated);
                $assignment->setVacatedDate(new DateTimeImmutable());
                $em->flush(); // flush so the collection reflects the vacated state
                $assignment->getRoom()->recalculateOccupancy();
            }
        }

        $newAssignment = new RoomAssignment();
        $newAssignment->setStudent($student);
        $newAssignment->setRoom($requestedRoom);
        $newAssignment->setAssignedDate(new DateTimeImmutable());
        $newAssignment->setStatus(AssignmentStatus::Active);

        // Persist explicit Student↔Supervisor relation (derived from the requested room / hostel).
        $student->setSupervisor($requestedRoom->getSupervisor() ?? $supervisor);

        $rcRequest->setStatus(RequestStatus::Approved);
        $rcRequest->setReviewedBy($supervisor);
        $rcRequest->setReviewedAt(new DateTimeImmutable());

        $em->persist($newAssignment);
        $em->flush(); // persist new assignment first so collection is accurate

        $requestedRoom->recalculateOccupancy();
        $em->flush();

        $this->addFlash('success', $student->getUser()->getName() . '\'s room change approved!');
        return $this->redirectToRoute('supervisor_room_changes');
    }

    #[Route('/room-changes/{id}/reject', name: 'supervisor_room_change_reject', methods: ['POST'])]
    public function roomChangeReject(int $id, RoomChangeRequestRepository $repo, EntityManagerInterface $em): Response
    {
        $rcRequest = $repo->find($id);
        if (!$rcRequest) {
            $this->addFlash('error', 'Request not found.');
            return $this->redirectToRoute('supervisor_room_changes');
        }

        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();

        $rcRequest->setStatus(RequestStatus::Rejected);
        $rcRequest->setReviewedBy($supervisor);
        $rcRequest->setReviewedAt(new DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', 'Room change request rejected.');
        return $this->redirectToRoute('supervisor_room_changes');
    }

    // ─── Tasks ────────────────────────────────────────────────────────────────

    #[Route('/tasks', name: 'supervisor_tasks')]
    public function tasks(): Response
    {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();

        $tasks = $supervisor ? $supervisor->getTasks()->toArray() : [];

        usort($tasks, function ($a, $b) {
            if ($a->getDueDate() === null) return 1;
            if ($b->getDueDate() === null) return -1;
            return $a->getDueDate() <=> $b->getDueDate();
        });

        return $this->render('supervisor/tasks.html.twig', [
            'tasks'      => $tasks,
            'supervisor' => $supervisor,
        ]);
    }

    #[Route('/tasks/{id}/update', name: 'supervisor_task_update', methods: ['POST'])]
    public function taskUpdate(int $id, Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();
        $task       = $em->getRepository(\App\Entity\SupervisorTask::class)->find($id);

        if (!$task || $task->getSupervisor() !== $supervisor) {
            $this->addFlash('error', 'Task not found or access denied.');
            return $this->redirectToRoute('supervisor_tasks');
        }

        $statusMap = [
            'pending'     => TaskStatus::Pending,
            'in_progress' => TaskStatus::InProgress,
            'done'        => TaskStatus::Done,
        ];

        $newStatus = $request->request->get('status');
        if (isset($statusMap[$newStatus])) {
            $task->setStatus($statusMap[$newStatus]);
            $em->flush();
            $this->addFlash('success', 'Task "' . $task->getTitle() . '" updated.');
        }

        return $this->redirectToRoute('supervisor_tasks');
    }

    // ─── Chat (Student ↔ Supervisor) ──────────────────────────────────────────

    #[Route('/chat', name: 'supervisor_chat')]
    public function chat(
        Request $request,
        ChatRepository $chatRepo,
        EntityManagerInterface $em,
        StudentRepository $studentRepo,
    ): Response {
        /** @var \App\Entity\User $supervisorUser */
        $supervisorUser = $this->getUser();
        $supervisor     = $supervisorUser->getSupervisor();
        $hostel         = $supervisor?->getHostelAssigned() ?? '';

        // Start with students who already have message history
        $conversations = $chatRepo->findStudentConversations($supervisorUser);

        // Build a set of user IDs already in the conversations list
        $alreadyIncluded = [];
        foreach ($conversations as $conv) {
            $alreadyIncluded[$conv['student']->getId()] = true;
        }

        // Add ALL students in this supervisor's hostel who are not yet listed
        if ($supervisor) {
            $blockStudents = $studentRepo->findBySupervisor($supervisor);
            foreach ($blockStudents as $student) {
                $studentUser = $student->getUser();
                if (!$studentUser || isset($alreadyIncluded[$studentUser->getId()])) {
                    continue;
                }
                $conversations[] = [
                    'student'     => $studentUser,
                    'lastMessage' => null,
                    'unread'      => 0,
                ];
            }
        }

        // Check if a specific partner is requested via ?partner query param
        $partnerId = (int) $request->query->get('partner', 0);
        $activeContact = null;
        $messages = [];
        $lastId = 0;

        if ($partnerId > 0) {
            $partnerUser = $em->getRepository(\App\Entity\User::class)->find($partnerId);
            if ($partnerUser) {
                $activeContact = $partnerUser;
                $messages = $chatRepo->findConversation($supervisorUser, $partnerUser, 50);

                // Mark unread messages as read
                foreach ($messages as $msg) {
                    if ($msg->getReceiver() === $supervisorUser && !$msg->isRead()) {
                        $msg->setIsRead(true);
                    }
                }
                $em->flush();

                $lastId = count($messages) > 0 ? end($messages)->getId() : 0;
            }
        }

        return $this->render('supervisor/chat.html.twig', [
            'conversations' => $conversations,
            'activeContact' => $activeContact,
            'messages'      => $messages,
            'lastId'        => $lastId,
        ]);
    }

    #[Route('/chat/{studentId}/send', name: 'supervisor_chat_send', methods: ['POST'], requirements: ['studentId' => '\d+'])]
    public function chatSend(int $studentId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $supervisorUser */
        $supervisorUser = $this->getUser();
        $studentUser    = $em->getRepository(\App\Entity\User::class)->find($studentId);

        if (!$studentUser) {
            return $this->json(['error' => 'Student not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $body = trim((string) ($data['message'] ?? ''));
        if (!$body) {
            return $this->json(['error' => 'Empty message'], 400);
        }

        $msg = new ChatMessage();
        $msg->setSender($supervisorUser);
        $msg->setReceiver($studentUser);
        $msg->setMessage($body);
        $em->persist($msg);
        $em->flush();

        return $this->json([
            'id'      => $msg->getId(),
            'message' => $msg->getMessage(),
            'sentAt'  => $msg->getSentAt()->format('H:i'),
            'mine'    => true,
        ]);
    }

    #[Route('/chat/{studentId}/poll', name: 'supervisor_chat_poll', methods: ['GET'], requirements: ['studentId' => '\d+'])]
    public function chatPoll(int $studentId, Request $request, ChatRepository $chatRepo, EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $supervisorUser */
        $supervisorUser = $this->getUser();
        $studentUser    = $em->getRepository(\App\Entity\User::class)->find($studentId);

        if (!$studentUser) {
            return $this->json([]);
        }

        $afterId  = (int) $request->query->get('after', 0);
        $messages = $chatRepo->findAfter($supervisorUser, $studentUser, $afterId);

        foreach ($messages as $msg) {
            if ($msg->getReceiver() === $supervisorUser && !$msg->isRead()) {
                $msg->setIsRead(true);
            }
        }
        $em->flush();

        return $this->json(array_map(fn($msg) => [
            'id'      => $msg->getId(),
            'message' => $msg->getMessage(),
            'sentAt'  => $msg->getSentAt()->format('H:i'),
            'mine'    => $msg->getSender()->getId() === $supervisorUser->getId(),
        ], $messages));
    }
}
