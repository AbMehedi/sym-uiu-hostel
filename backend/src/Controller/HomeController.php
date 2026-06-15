<?php

namespace App\Controller;

use App\Entity\RoomAssignment;
use App\Enum\AssignmentStatus;
use App\Enum\ComplaintStatus;
use App\Repository\ComplaintRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        EntityManagerInterface $em,
        RoomRepository $roomRepo,
        ComplaintRepository $complaintRepo
    ): Response {
        // Query distinct room types based on capacity
        $rooms = $roomRepo->findAll();
        $roomTypes = [];
        foreach ($rooms as $room) {
            $capacity = $room->getCapacity();
            $typeName = $room->getRoomType() ?: ($capacity . ' Sharing');
            if (!isset($roomTypes[$capacity])) {
                // Determine price and features based on capacity
                $price = 3500;
                $features = ['Shared Attached bathroom', 'Wifi', 'Fan', '3 beds', 'Common Table'];
                $badge = 'Economy';
                if ($capacity === 1) {
                    $price = 8000;
                    $features = ['Private Attached bathroom', 'Wifi', 'AC', 'Mini Fridge', 'Study Table and chair'];
                    $badge = 'Premium';
                } elseif ($capacity === 2) {
                    $price = 6000;
                    $features = ['Shared Attached bathroom', 'Wifi', 'AC', 'Mini Fridge', '2 Study Desks'];
                    $badge = 'Standard';
                }
                $roomTypes[$capacity] = [
                    'name' => $typeName,
                    'capacity' => $capacity,
                    'photoPath' => $room->getPhotoPath(),
                    'price' => $price,
                    'features' => $features,
                    'badge' => $badge,
                ];
            } elseif (!$roomTypes[$capacity]['photoPath'] && $room->getPhotoPath()) {
                $roomTypes[$capacity]['photoPath'] = $room->getPhotoPath();
            }
        }
        ksort($roomTypes);

        // Stats
        $totalRooms = count($rooms);
        $accommodatedCount = $em->getRepository(RoomAssignment::class)->count(['status' => AssignmentStatus::Active]);
        $totalComplaints = count($complaintRepo->findAll());
        $resolvedComplaints = count($complaintRepo->findBy(['status' => ComplaintStatus::Resolved]));
        $resolutionRate = $totalComplaints > 0 ? round(($resolvedComplaints / $totalComplaints) * 100) : 98;

        // Config Parameters
        $rules = $this->getParameter('hostel_rules');
        $contact = $this->getParameter('hostel_contact');

        return $this->render('home/index.html.twig', [
            'roomTypes' => $roomTypes,
            'totalRooms' => $totalRooms,
            'accommodatedCount' => $accommodatedCount,
            'resolutionRate' => $resolutionRate,
            'rules' => $rules,
            'contact' => $contact,
        ]);
    }
}
