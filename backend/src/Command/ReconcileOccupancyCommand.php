<?php

namespace App\Command;

use App\Entity\Room;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reconcile-occupancy',
    description: 'Recalculate Room.currentOccupancy from active RoomAssignment records and fix any drift.',
)]
class ReconcileOccupancyCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Reconciling Room Occupancy');

        /** @var Room[] $rooms */
        $rooms   = $this->em->getRepository(Room::class)->findAll();
        $fixed   = 0;
        $ok      = 0;

        foreach ($rooms as $room) {
            $before = $room->getCurrentOccupancy();
            $room->recalculateOccupancy();
            $after  = $room->getCurrentOccupancy();

            if ($before !== $after) {
                $io->warning(sprintf(
                    'Room %s (%s): stored=%d → corrected=%d',
                    $room->getRoomNumber(),
                    $room->getBlock(),
                    $before,
                    $after
                ));
                $fixed++;
            } else {
                $ok++;
            }
        }

        $this->em->flush();

        if ($fixed === 0) {
            $io->success(sprintf('All %d rooms are consistent. No drift detected.', $ok));
        } else {
            $io->success(sprintf(
                'Fixed %d room(s). %d room(s) were already consistent.',
                $fixed,
                $ok
            ));
        }

        return Command::SUCCESS;
    }
}
