<?php

namespace App\Controller;

use App\Entity\ChatMessage;
use App\Entity\Complaint;
use App\Entity\RoomAssignment;
use App\Entity\RoomChangeRequest;
use App\Entity\Supervisor;
use App\Entity\User;
use App\Entity\Student;
use App\Entity\Room;
use App\Entity\ComplaintUpdate;
use App\Enum\AdmissionStatus;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintCategory;
use App\Enum\ComplaintStatus;
use App\Enum\RequestStatus;
use App\Repository\AnnouncementRepository;
use App\Repository\ChatRepository;
use App\Repository\ComplaintRepository;
use App\Repository\RoomChangeRequestRepository;
use App\Repository\RoomRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/student')]
#[IsGranted('ROLE_STUDENT')]
class StudentController extends AbstractController
{
    // ─── Dashboard ────────────────────────────────────────────────────────────

    #[Route('/dashboard', name: 'student_dashboard')]
    public function dashboard(
        ComplaintRepository $complaintRepo,
        AnnouncementRepository $announcementRepo,
        EntityManagerInterface $em,
    ): Response {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $student = $user->getStudent();
        $admissionStatus = $student?->getAdmissionStatus();

        $room = null;
        $roommates = [];
        $supervisor = null;

        if ($student && $admissionStatus === AdmissionStatus::Approved) {
            $room = $student->getRoom();
            if ($room) {
                // Roommates: other active assignments in the same room, excluding this student
                foreach ($room->getRoomAssignments() as $assignment) {
                    if (
                        $assignment->getStatus() === AssignmentStatus::Active
                        && $assignment->getStudent()?->getId() !== $student->getId()
                    ) {
                        $roommates[] = $assignment->getStudent();
                    }
                }

                // Supervisor for this student's hostel (stored as room.hostel)
                $supervisorEntity = $student->getSupervisor()
                    ?: $room->getSupervisor()
                    ?: $em->getRepository(Supervisor::class)->findOneBy(['hostelAssigned' => $room->getHostel()]);
                $supervisor = $supervisorEntity?->getUser();
            }
        }

        // Stats
        $myComplaints = $student ? $complaintRepo->findBy(['student' => $student]) : [];
        $pending      = array_filter($myComplaints, fn($c) => $c->getStatusEnum() === ComplaintStatus::Pending);
        $inProgress   = array_filter($myComplaints, fn($c) => $c->getStatusEnum() === ComplaintStatus::InProgress);
        $resolved     = array_filter($myComplaints, fn($c) => $c->getStatusEnum() === ComplaintStatus::Resolved);

        // Recent complaints (last 3)
        $recentComplaints = $student
            ? $complaintRepo->findBy(['student' => $student], ['createdAt' => 'DESC'], 3)
            : [];

        // Latest announcements (last 3)
        $hostel = $room ? $room->getHostel() : null;
        $qb = $announcementRepo->createQueryBuilder('a');
        if ($hostel) {
            $qb->where('a.targetBlock = :hostel OR a.targetBlock = :general')
               ->setParameter('hostel', $hostel)
               ->setParameter('general', 'General');
        } else {
            $qb->where('a.targetBlock = :general')
               ->setParameter('general', 'General');
        }
        $recentAnnouncements = $qb->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        // Total announcements for unread badge
        $totalAnnouncements = count($announcementRepo->createQueryBuilder('a')
            ->where('a.targetBlock = :hostel OR a.targetBlock = :general')
            ->setParameter('hostel', $hostel ?: 'General')
            ->setParameter('general', 'General')
            ->getQuery()
            ->getResult());

        return $this->render('student/dashboard.html.twig', [
            'student'              => $student,
            'room'                 => $room,
            'roommates'            => $roommates,
            'supervisor'           => $supervisor,
            'admissionStatus'      => $admissionStatus,
            'totalComplaints'      => count($myComplaints),
            'pendingComplaints'    => count($pending),
            'inProgressComplaints' => count($inProgress),
            'resolvedComplaints'   => count($resolved),
            'recentComplaints'     => $recentComplaints,
            'recentAnnouncements'  => $recentAnnouncements,
            'totalAnnouncements'   => $totalAnnouncements,
        ]);
    }

    // ─── Announcements ────────────────────────────────────────────────────────
 
    #[Route('/announcements', name: 'student_announcements')]
    public function announcements(AnnouncementRepository $repo): Response
    {
        if ($redirect = $this->checkApprovedStudent()) {
            return $redirect;
        }
        $student = $this->getUser()->getStudent();
        $room = $student->getRoom();
        $hostel = $room ? $room->getHostel() : null;

        $qb = $repo->createQueryBuilder('a');
        if ($hostel) {
            $qb->where('a.targetBlock = :hostel OR a.targetBlock = :general')
               ->setParameter('hostel', $hostel)
               ->setParameter('general', 'General');
        } else {
            $qb->where('a.targetBlock = :general')
               ->setParameter('general', 'General');
        }

        $announcements = $qb->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('student/announcements.html.twig', [
            'announcements' => $announcements,
        ]);
    }

    // ─── Complaints ───────────────────────────────────────────────────────────

    #[Route('/complaints', name: 'student_complaints', methods: ['GET', 'POST'])]
    public function complaints(Request $request, ComplaintRepository $repo, EntityManagerInterface $em): Response
    {
        if ($redirect = $this->checkApprovedStudent()) {
            return $redirect;
        }
        $student = $this->getUser()->getStudent();

        if ($request->isMethod('POST')) {
            $subject     = $request->request->get('subject');
            $type        = $request->request->get('type');
            $description = $request->request->get('description');

            // ── Phase 1 fix: block submission if student has no room ──
            $room = $student->getRoom();
            if (!$room) {
                $this->addFlash('error', 'You must be assigned to a room before filing a complaint.');
                return $this->redirectToRoute('student_complaints');
            }

            // Handle optional photo upload
            $photoFile = $request->files->get('photo');
            $photoUrl  = null;

            if ($photoFile && $photoFile->isValid()) {
                $newFilename = uniqid() . '.' . $photoFile->guessExtension();
                try {
                    $photoFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads',
                        $newFilename
                    );
                    $photoUrl = '/uploads/' . $newFilename;
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload photo.');
                }
            }

            // Create Complaint
            $complaint = new Complaint();
            $complaint->setStudent($student);
            $complaint->setSubject($subject);
            $complaint->setDescription($description);
            $complaint->setPhotoUrl($photoUrl);
            $complaint->setStatus(ComplaintStatus::Pending);
            $complaint->setRoom($room);

            try {
                $category = ComplaintCategory::from(strtolower($type));
            } catch (\ValueError $e) {
                $category = ComplaintCategory::Other;
            }
            $complaint->setCategory($category);

            $em->persist($complaint);
            $em->flush();
            $this->addFlash('success', 'Complaint submitted successfully!');

            return $this->redirectToRoute('student_complaints');
        }

        return $this->render('student/complaints.html.twig', [
            'complaints' => $repo->findBy(['student' => $student], ['createdAt' => 'DESC']),
        ]);
    }

    // ─── Room Change ──────────────────────────────────────────────────────────

    #[Route('/room-change', name: 'student_room_change', methods: ['GET', 'POST'])]
    public function roomChange(
        Request $request,
        RoomChangeRequestRepository $repo,
        RoomRepository $roomRepo,
        EntityManagerInterface $em,
    ): Response {
        if ($redirect = $this->checkApprovedStudent()) {
            return $redirect;
        }
        $student = $this->getUser()->getStudent();
        $currentRoom = $student->getRoom();

        if ($request->isMethod('POST')) {
            $reason  = $request->request->get('reason');
            $details = $request->request->get('details');
            $roomId  = null;

            if ($request->getContentTypeFormat() === 'json') {
                $data    = json_decode($request->getContent(), true);
                $reason  = $data['reason'] ?? '';
                $details = $data['details'] ?? '';
                $roomId  = isset($data['roomId']) ? (int) $data['roomId'] : null;
            }

            // ── Block submission if student already has a pending request ──
            $existingPending = $repo->findOneBy(['student' => $student, 'status' => \App\Enum\RequestStatus::Pending]);
            if ($existingPending) {
                $errorMsg = 'You already have a pending room change request. Please wait for a supervisor decision before submitting another.';
                if ($request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest()) {
                    return $this->json(['status' => 'error', 'message' => $errorMsg], 400);
                }
                $this->addFlash('error', $errorMsg);
                return $this->redirectToRoute('student_room_change');
            }

            // ── Block submission if student has no room ──
            if (!$currentRoom) {
                $errorMsg = 'You must be assigned to a room before requesting a room change.';
                if ($request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest()) {
                    return $this->json(['status' => 'error', 'message' => $errorMsg], 400);
                }
                $this->addFlash('error', $errorMsg);
                return $this->redirectToRoute('student_room_change');
            }

            // ── Resolve the requested room from student's choice ──
            $requestedRoom = null;
            if ($roomId) {
                $requestedRoom = $roomRepo->find($roomId);
                // Validate: must exist, must not be the current room, must not be full
                if (!$requestedRoom || $requestedRoom->getId() === $currentRoom->getId()) {
                    $requestedRoom = null;
                } elseif ($requestedRoom->getActualOccupancy() >= $requestedRoom->getCapacity()) {
                    $errorMsg = 'The selected room is already full. Please choose another.';
                    if ($request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest()) {
                        return $this->json(['status' => 'error', 'message' => $errorMsg], 400);
                    }
                    $this->addFlash('error', $errorMsg);
                    return $this->redirectToRoute('student_room_change');
                }
            }

            // Fallback: pick any other available room (should not happen with UI)
            if (!$requestedRoom) {
                $requestedRoom = $roomRepo->createQueryBuilder('r')
                    ->where('r.id != :currentRoomId')
                    ->andWhere('r.currentOccupancy < r.capacity')
                    ->setParameter('currentRoomId', $currentRoom->getId())
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult() ?? $currentRoom;
            }

            $rcRequest = new RoomChangeRequest();
            $rcRequest->setStudent($student);
            $rcRequest->setCurrentRoom($currentRoom);
            $rcRequest->setRequestedRoom($requestedRoom);
            $rcRequest->setReason($reason . ': ' . $details);
            $rcRequest->setStatus(RequestStatus::Pending);

            $em->persist($rcRequest);
            $em->flush();

            if ($request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest()) {
                return $this->json([
                    'status'  => 'success',
                    'message' => 'Request submitted!',
                    'requestedRoom' => $requestedRoom->getRoomNumber(),
                ]);
            }

            $this->addFlash('success', 'Room change request submitted successfully!');
            return $this->redirectToRoute('student_room_change');
        }

        // GET: fetch available rooms the student can move to
        $availableRooms = $currentRoom
            ? $roomRepo->createQueryBuilder('r')
                ->where('r.id != :currentRoomId')
                ->andWhere('r.currentOccupancy < r.capacity')
                ->setParameter('currentRoomId', $currentRoom->getId())
                ->orderBy('r.hostel', 'ASC')
                ->addOrderBy('r.roomNumber', 'ASC')
                ->getQuery()
                ->getResult()
            : [];

        return $this->render('student/room-change.html.twig', [
            'requests'       => $repo->findBy(['student' => $student], ['id' => 'DESC']),
            'availableRooms' => $availableRooms,
            'currentRoom'    => $currentRoom,
        ]);
    }

    // ─── Profile ──────────────────────────────────────────────────────────────

    #[Route('/profile', name: 'student_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $student = $user->getStudent();

        if ($request->isMethod('POST')) {
            $phone     = trim((string) $request->request->get('phone', ''));
            $emergency = trim((string) $request->request->get('emergencyContact', ''));
            $student->setPhone($phone ?: null);
            $student->setEmergencyContact($emergency ?: null);
            $em->flush();
            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('student_profile');
        }

        return $this->render('student/profile.html.twig', [
            'student' => $student,
        ]);
    }

    // ─── Unified Messenger Chat ────────────────────────────────────────────────

    /**
     * Main messenger page — shows contact list (supervisor + roommates).
     * If a partnerId is provided via query, loads that conversation.
     */
    #[Route('/chat', name: 'student_chat')]
    public function chat(
        Request $request,
        EntityManagerInterface $em,
        ChatRepository $chatRepo,
    ): Response {
        if ($redirect = $this->checkApprovedStudent()) {
            return $redirect;
        }
        $student = $this->getUser()->getStudent();
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $room    = $student->getRoom();

        $contacts = $this->buildContactList($user, $student, $room, $em, $chatRepo);

        // Determine active partner (from URL ?partner=id or first contact)
        $partnerId     = (int) $request->query->get('partner', 0);
        $activeContact = null;
        $messages      = [];
        $lastId        = 0;

        if ($partnerId) {
            $partnerUser = $em->getRepository(User::class)->find($partnerId);
            if ($partnerUser) {
                $activeContact = $partnerUser;
                $messages      = $chatRepo->findConversation($user, $partnerUser, 50);
                $lastId        = count($messages) > 0 ? end($messages)->getId() : 0;
                // Mark received messages as read
                foreach ($messages as $msg) {
                    if ($msg->getReceiver() === $user && !$msg->isRead()) {
                        $msg->setIsRead(true);
                    }
                }
                $em->flush();
            }
        } elseif (!empty($contacts)) {
            // Default: open the first contact
            $activeContact = $contacts[0]['user'];
            $messages      = $chatRepo->findConversation($user, $activeContact, 50);
            $lastId        = count($messages) > 0 ? end($messages)->getId() : 0;
            foreach ($messages as $msg) {
                if ($msg->getReceiver() === $user && !$msg->isRead()) {
                    $msg->setIsRead(true);
                }
            }
            $em->flush();
        }

        return $this->render('student/chat.html.twig', [
            'contacts'      => $contacts,
            'activeContact' => $activeContact,
            'messages'      => $messages,
            'lastId'        => $lastId,
        ]);
    }

    /**
     * Send a message to a partner (JSON POST).
     */
    #[Route('/chat/{partnerId}/send', name: 'student_chat_send', methods: ['POST'], requirements: ['partnerId' => '\d+'])]
    public function chatSend(int $partnerId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if ($jsonError = $this->checkApprovedStudentJson()) {
            return $jsonError;
        }
        /** @var \App\Entity\User $user */
        $user        = $this->getUser();
        $partnerUser = $em->getRepository(User::class)->find($partnerId);

        if (!$partnerUser) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $body = trim((string) ($data['message'] ?? ''));
        if (!$body) {
            return $this->json(['error' => 'Empty message'], 400);
        }

        $msg = new ChatMessage();
        $msg->setSender($user);
        $msg->setReceiver($partnerUser);
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

    /**
     * Poll new messages from a partner after a given message ID.
     */
    #[Route('/chat/{partnerId}/poll', name: 'student_chat_poll', methods: ['GET'], requirements: ['partnerId' => '\d+'])]
    public function chatPoll(int $partnerId, Request $request, ChatRepository $chatRepo, EntityManagerInterface $em): JsonResponse
    {
        if ($jsonError = $this->checkApprovedStudentJson()) {
            return $jsonError;
        }
        /** @var \App\Entity\User $user */
        $user        = $this->getUser();
        $partnerUser = $em->getRepository(User::class)->find($partnerId);

        if (!$partnerUser) {
            return $this->json([]);
        }

        $afterId  = (int) $request->query->get('after', 0);
        $messages = $chatRepo->findAfter($user, $partnerUser, $afterId);

        foreach ($messages as $msg) {
            if ($msg->getReceiver() === $user && !$msg->isRead()) {
                $msg->setIsRead(true);
            }
        }
        $em->flush();

        return $this->json(array_map(fn($msg) => [
            'id'      => $msg->getId(),
            'message' => $msg->getMessage(),
            'sentAt'  => $msg->getSentAt()->format('H:i'),
            'mine'    => $msg->getSender()->getId() === $user->getId(),
        ], $messages));
    }

    // ─── Standalone Room & Roommate View ──────────────────────────────────────

    #[Route('/my-room', name: 'student_my_room', methods: ['GET'])]
    public function myRoom(EntityManagerInterface $em): Response
    {
        if ($redirect = $this->checkApprovedStudent()) {
            return $redirect;
        }
        $student = $this->getUser()->getStudent();
        $room = $student->getRoom();
        $roommates = [];
        $supervisor = null;

        if ($room) {
            foreach ($room->getRoomAssignments() as $assignment) {
                if (
                    $assignment->getStatus() === AssignmentStatus::Active
                    && $assignment->getStudent()?->getId() !== $student->getId()
                ) {
                    $roommates[] = $assignment->getStudent();
                }
            }
            $supervisorEntity = $student->getSupervisor()
                ?: $room->getSupervisor()
                ?: $em->getRepository(Supervisor::class)->findOneBy(['hostelAssigned' => $room->getHostel()]);
            $supervisor = $supervisorEntity?->getUser();
        }

        return $this->render('student/my-room.html.twig', [
            'student' => $student,
            'room' => $room,
            'roommates' => $roommates,
            'supervisor' => $supervisor,
        ]);
    }

    // ─── Individual Complaint Detail Timeline ─────────────────────────────────

    #[Route('/complaints/{id}', name: 'student_complaint_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function complaintDetail(int $id, EntityManagerInterface $em): Response
    {
        if ($redirect = $this->checkApprovedStudent()) {
            return $redirect;
        }
        $student = $this->getUser()->getStudent();

        $complaint = $em->getRepository(Complaint::class)->findOneBy([
            'id' => $id,
            'student' => $student,
        ]);

        if (!$complaint) {
            throw $this->createNotFoundException('Complaint not found.');
        }

        $updates = $em->getRepository(ComplaintUpdate::class)->findBy(
            ['complaint' => $complaint],
            ['updatedAt' => 'DESC']
        );

        return $this->render('student/complaint-detail.html.twig', [
            'complaint' => $complaint,
            'updates' => $updates,
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Check if student is approved, redirect to dashboard if not.
     */
    private function checkApprovedStudent(): ?Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $student = $user->getStudent();
        if (!$student || $student->getAdmissionStatus() !== AdmissionStatus::Approved) {
            $this->addFlash('error', 'Access denied. Your admission status is not approved.');
            return $this->redirectToRoute('student_dashboard');
        }
        return null;
    }

    /**
     * Check if student is approved, return JSON error if not.
     */
    private function checkApprovedStudentJson(): ?JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $student = $user->getStudent();
        if (!$student || $student->getAdmissionStatus() !== AdmissionStatus::Approved) {
            return $this->json(['error' => 'Access denied. Your admission status is not approved.'], 403);
        }
        return null;
    }

    /**
     * Build the contact list for the messenger: [supervisor, ...roommates].
     * Each entry: ['user' => User, 'role' => string, 'lastMessage' => ?ChatMessage, 'unread' => int]
     */
    private function buildContactList(
        User $user,
        ?\App\Entity\Student $student,
        ?\App\Entity\Room $room,
        EntityManagerInterface $em,
        ChatRepository $chatRepo,
    ): array {
        $contacts = [];

        if (!$room) {
            return $contacts;
        }

        // 1. Supervisor for this block
        $supervisorEntity = $student?->getSupervisor()
            ?: $room->getSupervisor()
            ?: $em->getRepository(Supervisor::class)->findOneBy(['hostelAssigned' => $room->getHostel()]);
        if ($supervisorEntity) {
            $supUser  = $supervisorEntity->getUser();
            $lastMsgs = $chatRepo->findConversation($user, $supUser, 1);
            $contacts[] = [
                'user'        => $supUser,
                'role'        => 'Supervisor · Hostel ' . $room->getHostel(),
                'lastMessage' => !empty($lastMsgs) ? end($lastMsgs) : null,
                'unread'      => $chatRepo->countUnread($supUser, $user),
            ];
        }

        // 2. Roommates
        foreach ($room->getRoomAssignments() as $assignment) {
            if (
                $assignment->getStatus() === AssignmentStatus::Active
                && $assignment->getStudent()?->getId() !== $student?->getId()
            ) {
                $rmUser   = $assignment->getStudent()->getUser();
                $lastMsgs = $chatRepo->findConversation($user, $rmUser, 1);
                $contacts[] = [
                    'user'        => $rmUser,
                    'role'        => 'Roommate · Room ' . $room->getRoomNumber(),
                    'lastMessage' => !empty($lastMsgs) ? end($lastMsgs) : null,
                    'unread'      => $chatRepo->countUnread($rmUser, $user),
                ];
            }
        }

        return $contacts;
    }
}
