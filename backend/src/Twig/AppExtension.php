<?php

namespace App\Twig;

use App\Repository\AnnouncementRepository;
use App\Entity\User;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private AnnouncementRepository $announcementRepo;

    public function __construct(AnnouncementRepository $announcementRepo)
    {
        $this->announcementRepo = $announcementRepo;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_announcements_count', [$this, 'getUnreadAnnouncementsCount']),
        ];
    }

    public function getUnreadAnnouncementsCount(User $user): int
    {
        $student = $user->getStudent();
        if (!$student) {
            return 0;
        }
        $room = $student->getRoom();
        $hostel = $room ? $room->getHostel() : 'General';

        // Query announcements matching student's block or General
        $announcements = $this->announcementRepo->createQueryBuilder('a')
            ->where('a.targetBlock = :hostel OR a.targetBlock = :general')
            ->setParameter('hostel', $hostel)
            ->setParameter('general', 'General')
            ->getQuery()
            ->getResult();

        $readCount = $student->getReadAnnouncements()->count();
        return max(0, count($announcements) - $readCount);
    }
}
