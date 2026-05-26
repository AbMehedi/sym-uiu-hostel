<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\ChatMessage;
use App\Entity\RoomAssignment;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintStatus;
use App\Enum\RequestStatus;
use App\Enum\RoomStatus;
use App\Enum\TaskStatus;
use App\Repository\AnnouncementRepository;
use App\Repository\ChatRepository;
use App\Repository\ComplaintRepository;
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
    ): Response {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();

        return $this->render('supervisor/dashboard.html.twig', [
            'totalStudents'       => count($studentRepo->findAll()),
            'pendingComplaints'   => count($complaintRepo->findBy(['status' => ComplaintStatus::Pending])),
            'inProgressComplaints'=> count($complaintRepo->findBy(['status' => ComplaintStatus::InProgress])),
            'pendingRoomChanges'  => count($roomChangeRepo->findPending()),
            'supervisor'          => $supervisor,
        ]);
    }

    // ─── Students ─────────────────────────────────────────────────────────────

    #[Route('/students', name: 'supervisor_students')]
    public function students(StudentRepository $repo): Response
    {
        return $this->render('supervisor/students.html.twig', [
            'students' => $repo->findAll(),
        ]);
    }

    // ─── Complaints ───────────────────────────────────────────────────────────

    #[Route('/complaints', name: 'supervisor_complaints')]
    public function complaints(ComplaintRepository $repo): Response
    {
        return $this->render('supervisor/complaints.html.twig', [
            'complaints' => $repo->findAll(),
        ]);
    }

    #[Route('/complaints/update/{id}', name: 'supervisor_complaint_update', methods: ['POST'])]
    public function updateComplaint(int $id, Request $request, ComplaintRepository $repo, EntityManagerInterface $em): Response
    {
        $complaint = $repo->find($id);
        if (!$complaint) {
            return $this->json(['status' => 'error', 'message' => 'Complaint not found.'], 404);
        }

        $data         = json_decode($request->getContent(), true);
        $newStatusStr = $data['status'] ?? '';
        $notes        = $data['notes'] ?? '';

        $statusMap = [
            'Pending'     => ComplaintStatus::Pending,
            'In Progress' => ComplaintStatus::InProgress,
            'Resolved'    => ComplaintStatus::Resolved,
        ];

        if (isset($statusMap[$newStatusStr])) {
            $complaint->setStatus($statusMap[$newStatusStr]);
        }

        if (!empty($notes)) {
            $update = new \App\Entity\ComplaintUpdate();
            $update->setComplaint($complaint);
            $update->setNote($notes);
            $update->setUpdatedBy($this->getUser());
            $update->setStatus($complaint->getStatusEnum());
            $em->persist($update);
        }

        if ($newStatusStr === 'Resolved') {
            $complaint->setResolvedAt(new DateTimeImmutable());
        }

        $em->flush();

        return $this->json(['status' => 'success']);
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
            $announcement->setTargetBlock($supervisor ? $supervisor->getBlockAssigned() : 'General');

            $em->persist($announcement);
            $em->flush();

            $this->addFlash('success', 'Announcement posted successfully!');
            return $this->redirectToRoute('supervisor_announcements');
        }

        return $this->render('supervisor/announcements.html.twig', [
            'announcements' => $repo->findBy([], ['createdAt' => 'DESC']),
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

        if ($requestedRoom->getCurrentOccupancy() >= $requestedRoom->getCapacity()) {
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
    public function chat(ChatRepository $chatRepo): Response
    {
        /** @var \App\Entity\User $user */
        $user          = $this->getUser();
        $conversations = $chatRepo->findStudentConversations($user);

        return $this->render('supervisor/chat.html.twig', [
            'conversations' => $conversations,
        ]);
    }

    #[Route('/chat/{studentId}', name: 'supervisor_chat_conversation', requirements: ['studentId' => '\d+'])]
    public function chatConversation(int $studentId, ChatRepository $chatRepo, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $supervisorUser */
        $supervisorUser = $this->getUser();
        $studentUser    = $em->getRepository(\App\Entity\User::class)->find($studentId);

        if (!$studentUser) {
            $this->addFlash('error', 'Student not found.');
            return $this->redirectToRoute('supervisor_chat');
        }

        $messages = $chatRepo->findConversation($supervisorUser, $studentUser, 50);

        foreach ($messages as $msg) {
            if ($msg->getReceiver() === $supervisorUser && !$msg->isRead()) {
                $msg->setIsRead(true);
            }
        }
        $em->flush();

        $lastId = count($messages) > 0 ? end($messages)->getId() : 0;

        return $this->render('supervisor/chat-conversation.html.twig', [
            'partner'  => $studentUser,
            'messages' => $messages,
            'lastId'   => $lastId,
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
