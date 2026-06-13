<?php

namespace App\Repository;

use App\Entity\Student;
use App\Entity\Supervisor;
use App\Enum\AssignmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Student>
 */
class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Student::class);
    }

    /**
     * Returns all students whose active RoomAssignment is in a given hostel.
     *
     * @return Student[]
     */
    public function findByHostel(string $hostel): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.roomAssignments', 'ra')
            ->join('ra.room', 'r')
            ->where('ra.status = :status')
            ->andWhere('r.hostel = :hostel')
            ->setParameter('status', AssignmentStatus::Active)
            ->setParameter('hostel', $hostel)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all students assigned to a specific supervisor.
     *
     * @return Student[]
     */
    public function findBySupervisor(Supervisor $supervisor): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.supervisor = :supervisor')
            ->setParameter('supervisor', $supervisor)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Backwards-compatible alias for older code paths.
     *
     * @return Student[]
     */
    public function findByBlock(string $block): array
    {
        return $this->findByHostel($block);
    }

    /**
     * Returns all students whose active RoomAssignment is for a specific room.
     *
     * @return Student[]
     */
    public function findByRoom(int $roomId): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.roomAssignments', 'ra')
            ->join('ra.room', 'r')
            ->where('ra.status = :status')
            ->andWhere('r.id = :roomId')
            ->setParameter('status', AssignmentStatus::Active)
            ->setParameter('roomId', $roomId)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

