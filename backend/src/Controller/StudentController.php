<?php

namespace App\Controller;

use App\Entity\Complaint;
use App\Entity\RoomAssignment;
use App\Entity\RoomChangeRequest;
use App\Enum\AdmissionStatus;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintCategory;
use App\Enum\ComplaintStatus;
use App\Enum\RequestStatus;
use App\Repository\AnnouncementRepository;
use App\Repository\ComplaintRepository;
use App\Repository\RoomChangeRequestRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/student')]
#[IsGranted('ROLE_STUDENT')]
class StudentController extends AbstractController
{
    // ─── Dashboard (HP-3) ─────────────────────────────────────────────────────

    #[Route('/dashboard', name: 'student_dashboard')]
    public function dashboard(
        ComplaintRepository $complaintRepo,
        AnnouncementRepository $announcementRepo,
    ): Response {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $student = $user->getStudent();
        $room    = $student?->getRoom();

        // Roommates: other active assignments in the same room, excluding this student
        $roommates = [];
        if ($room) {
            foreach ($room->getRoomAssignments() as $assignment) {
                if (
                    $assignment->getStatus() === AssignmentStatus::Active
                    && $assignment->getStudent()?->getId() !== $student?->getId()
                ) {
                    $roommates[] = $assignment->getStudent();
                }
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
        $recentAnnouncements = $announcementRepo->findBy([], ['createdAt' => 'DESC'], 3);

        return $this->render('student/dashboard.html.twig', [
            'student'             => $student,
            'room'                => $room,
            'roommates'           => $roommates,
            'admissionStatus'     => $student?->getAdmissionStatus(),
            'totalComplaints'     => count($myComplaints),
            'pendingComplaints'   => count($pending),
            'inProgressComplaints'=> count($inProgress),
            'resolvedComplaints'  => count($resolved),
            'recentComplaints'    => $recentComplaints,
            'recentAnnouncements' => $recentAnnouncements,
        ]);
    }

    // ─── Announcements ────────────────────────────────────────────────────────

    #[Route('/announcements', name: 'student_announcements')]
    public function announcements(AnnouncementRepository $repo): Response
    {
        return $this->render('student/announcements.html.twig', [
            'announcements' => $repo->findAll(),
        ]);
    }

    // ─── Complaints ───────────────────────────────────────────────────────────

    #[Route('/complaints', name: 'student_complaints', methods: ['GET', 'POST'])]
    public function complaints(Request $request, ComplaintRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $student = $user->getStudent();

        if ($request->isMethod('POST')) {
            $subject     = $request->request->get('subject');
            $type        = $request->request->get('type');
            $description = $request->request->get('description');

            // Handle optional photo upload
            $photoFile = $request->files->get('photo');
            $photoUrl  = null;

            if ($photoFile) {
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

            try {
                $category = ComplaintCategory::from(strtolower($type));
            } catch (\ValueError $e) {
                $category = ComplaintCategory::Other;
            }
            $complaint->setCategory($category);

            $room = $student->getRoom();
            if (!$room) {
                $room = $em->getRepository(\App\Entity\Room::class)->findOneBy([]);
            }

            if ($room) {
                $complaint->setRoom($room);
                $em->persist($complaint);
                $em->flush();
                $this->addFlash('success', 'Complaint submitted successfully!');
            } else {
                $this->addFlash('error', 'You must be assigned to a room to file a complaint.');
            }

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
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $student = $user->getStudent();

        if ($request->isMethod('POST')) {
            $reason  = $request->request->get('reason');
            $details = $request->request->get('details');

            if ($request->getContentTypeFormat() === 'json') {
                $data    = json_decode($request->getContent(), true);
                $reason  = $data['reason'] ?? '';
                $details = $data['details'] ?? '';
            }

            $currentRoom = $student->getRoom();
            if (!$currentRoom) {
                $currentRoom = $roomRepo->findOneBy([]);
            }

            if (!$currentRoom) {
                return $this->json(['status' => 'error', 'message' => 'No current room found.'], 400);
            }

            $requestedRoom = $roomRepo->createQueryBuilder('r')
                ->where('r.id != :currentRoomId')
                ->setParameter('currentRoomId', $currentRoom->getId())
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$requestedRoom) {
                $requestedRoom = $currentRoom;
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
                return $this->json(['status' => 'success', 'message' => 'Request submitted!']);
            }

            $this->addFlash('success', 'Room change request submitted successfully!');
            return $this->redirectToRoute('student_room_change');
        }

        return $this->render('student/room-change.html.twig', [
            'requests' => $repo->findBy(['student' => $student]),
        ]);
    }

    // ─── Chat (stubs — medium priority) ───────────────────────────────────────

    #[Route('/chat-roommate', name: 'student_chat_roommate')]
    public function chatRoommate(): Response
    {
        return $this->render('student/chat-roommate.html.twig');
    }

    #[Route('/chat-supervisor', name: 'student_chat_supervisor')]
    public function chatSupervisor(): Response
    {
        return $this->render('student/chat-supervisor.html.twig');
    }
}
