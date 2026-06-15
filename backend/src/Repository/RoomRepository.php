<?php

namespace App\Repository;

use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    /**
     * Returns distinct room blocks, used as "hostel names" in the current data model.
     *
     * @return string[]
     */
    public function findDistinctHostelNames(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('DISTINCT r.hostel AS hostel')
            ->orderBy('r.hostel', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $hostels = array_map(static fn(array $row) => (string) ($row['hostel'] ?? ''), $rows);
        $hostels = array_values(array_filter($hostels, static fn(string $h) => trim($h) !== ''));

        return $hostels;
    }

    /**
     * @return Room[]
     */
    public function findAvailableByHostel(string $hostel): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.hostel = :hostel')
            ->setParameter('hostel', $hostel)
            ->orderBy('r.roomNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
