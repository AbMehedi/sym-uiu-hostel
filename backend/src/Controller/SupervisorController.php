<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\Complaint;
use App\Entity\RoomAssignment;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintStatus;
use App\Enum\RequestStatus;
use App\Enum\RoomStatus;
use App\Repository\AnnouncementRepository;
use App\Repository\ComplaintRepository;
use App\Repository\RoomChangeRequestRepository;
use App\Repository\StudentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    // ─── Room Change Requests (HP-4) ──────────────────────────────────────────

    #[Route('/room-changes', name: 'supervisor_room_changes')]
    public function roomChanges(RoomChangeRequestRepository $repo): Response
    {
        return $this->render('supervisor/room-changes.html.twig', [
            'pendingRequests' => $repo->findPending(),
            'allRequests'     => $repo->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/room-changes/{id}/approve', name: 'supervisor_room_change_approve', methods: ['POST'])]
    public function roomChangeApprove(
        int $id,
        RoomChangeRequestRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        $rcRequest = $repo->find($id);
        if (!$rcRequest) {
            $this->addFlash('error', 'Request not found.');
            return $this->redirectToRoute('supervisor_room_changes');
        }

        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $supervisor = $user->getSupervisor();

        $student       = $rcRequest->getStudent();
        $requestedRoom = $rcRequest->getRequestedRoom();

        if ($requestedRoom->getCurrentOccupancy() >= $requestedRoom->getCapacity()) {
            $this->addFlash('error', 'The requested room is already full. Cannot approve.');
            return $this->redirectToRoute('supervisor_room_changes');
        }

        // Vacate old assignment
        foreach ($student->getRoomAssignments() as $assignment) {
            if ($assignment->getStatus() === AssignmentStatus::Active) {
                $assignment->setStatus(AssignmentStatus::Vacated);
                $assignment->setVacatedDate(new DateTimeImmutable());
                $oldRoom = $assignment->getRoom();
                $oldRoom->setCurrentOccupancy(max(0, $oldRoom->getCurrentOccupancy() - 1));
                $oldRoom->setStatus(RoomStatus::Available);
            }
        }

        // Create new assignment
        $newAssignment = new RoomAssignment();
        $newAssignment->setStudent($student);
        $newAssignment->setRoom($requestedRoom);
        $newAssignment->setAssignedDate(new DateTimeImmutable());
        $newAssignment->setStatus(AssignmentStatus::Active);

        $requestedRoom->setCurrentOccupancy($requestedRoom->getCurrentOccupancy() + 1);
        if ($requestedRoom->getCurrentOccupancy() >= $requestedRoom->getCapacity()) {
            $requestedRoom->setStatus(RoomStatus::Full);
        }

        // Mark the request as approved
        $rcRequest->setStatus(RequestStatus::Approved);
        $rcRequest->setReviewedBy($supervisor);
        $rcRequest->setReviewedAt(new DateTimeImmutable());

        $em->persist($newAssignment);
        $em->flush();

        $this->addFlash('success', $student->getUser()->getName() . '\'s room change to Room ' . $requestedRoom->getRoomNumber() . ' has been approved!');
        return $this->redirectToRoute('supervisor_room_changes');
    }

    #[Route('/room-changes/{id}/reject', name: 'supervisor_room_change_reject', methods: ['POST'])]
    public function roomChangeReject(
        int $id,
        RoomChangeRequestRepository $repo,
        EntityManagerInterface $em,
    ): Response {
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
}
