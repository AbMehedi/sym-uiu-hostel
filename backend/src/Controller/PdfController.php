<?php

namespace App\Controller;

use App\Entity\RoomAssignment;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintCategory;
use App\Repository\ComplaintRepository;
use App\Repository\RepairCostRepository;
use App\Repository\RoomRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/pdf')]
#[IsGranted('ROLE_ADMIN')]
class PdfController extends AbstractController
{
    private function buildPdf(string $html): Dompdf
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf;
    }

    // ─── Room Allocation PDF ──────────────────────────────────────────────────

    #[Route('/room-allocation', name: 'admin_pdf_room_allocation')]
    public function roomAllocation(
        EntityManagerInterface $em,
        RoomRepository $roomRepo,
    ): Response {
        $allAssignments = $em->getRepository(RoomAssignment::class)->findBy(
            ['status' => AssignmentStatus::Active],
        );

        // Group by block → room number
        $grouped = [];
        foreach ($allAssignments as $asgn) {
            $room  = $asgn->getRoom();
            $block = $room->getBlock();
            $num   = $room->getRoomNumber();
            $grouped[$block][$num][] = $asgn;
        }
        ksort($grouped);
        foreach ($grouped as &$rooms) {
            ksort($rooms);
        }
        unset($rooms);

        $html = $this->renderView('pdf/room-allocation.html.twig', [
            'grouped'    => $grouped,
            'generatedAt'=> new DateTimeImmutable(),
            'totalRooms' => count($roomRepo->findAll()),
            'activeCount'=> count($allAssignments),
        ]);

        $dompdf = $this->buildPdf($html);

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="room-allocation-' . date('Y-m-d') . '.pdf"',
            ]
        );
    }

    // ─── Monthly Complaint Log PDF ────────────────────────────────────────────

    #[Route('/complaint-log', name: 'admin_pdf_complaint_log')]
    public function complaintLog(
        Request $request,
        ComplaintRepository $complaintRepo,
        RepairCostRepository $repairCostRepo,
    ): Response {
        $monthParam = $request->query->get('month', date('Y-m'));
        try {
            $monthStart = new DateTimeImmutable($monthParam . '-01 00:00:00');
            $monthEnd   = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
        } catch (\Exception) {
            $monthStart = new DateTimeImmutable('first day of this month 00:00:00');
            $monthEnd   = new DateTimeImmutable('last day of this month 23:59:59');
        }

        // Complaints within the month
        $complaints = $complaintRepo->createQueryBuilder('c')
            ->leftJoin('c.room', 'r')->addSelect('r')
            ->leftJoin('c.student', 's')->addSelect('s')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->where('c.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $monthStart)
            ->setParameter('end', $monthEnd)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Per-category stats
        $categoryStats = [];
        foreach (ComplaintCategory::cases() as $cat) {
            $categoryStats[$cat->value] = ['total' => 0, 'resolved' => 0, 'pending' => 0, 'cost' => 0.0];
        }
        $grandTotal = 0.0;
        foreach ($complaints as $c) {
            $key = $c->getCategory()->value;
            if (!isset($categoryStats[$key])) {
                continue;
            }
            $categoryStats[$key]['total']++;
            $status = $c->getStatus(); // human-readable string
            if ($status === 'Resolved') {
                $categoryStats[$key]['resolved']++;
            } else {
                $categoryStats[$key]['pending']++;
            }
            $cost = $c->getCost();
            $categoryStats[$key]['cost'] += $cost;
            $grandTotal += $cost;
        }

        $html = $this->renderView('pdf/complaint-log.html.twig', [
            'complaints'    => $complaints,
            'categoryStats' => $categoryStats,
            'grandTotal'    => $grandTotal,
            'monthLabel'    => $monthStart->format('F Y'),
            'generatedAt'   => new DateTimeImmutable(),
        ]);

        $dompdf = $this->buildPdf($html);
        $filename = 'complaint-log-' . $monthStart->format('Y-m') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
}
