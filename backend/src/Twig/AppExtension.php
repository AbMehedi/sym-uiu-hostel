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
        $block = $room ? $room->getBlock() : 'General';

        // Query announcements matching student's block or General
        $announcements = $this->announcementRepo->createQueryBuilder('a')
            ->where('a.targetBlock = :block OR a.targetBlock = :general')
            ->setParameter('block', $block)
            ->setParameter('general', 'General')
            ->getQuery()
            ->getResult();

        return count($announcements);
    }
}
