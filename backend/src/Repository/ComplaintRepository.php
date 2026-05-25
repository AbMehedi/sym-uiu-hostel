<?php

namespace App\Repository;

use App\Entity\Complaint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Complaint>
 */
class ComplaintRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Complaint::class);
    }

    /**
     * Returns complaint count per category enum value.
     * e.g. ['plumbing' => 5, 'electricity' => 3, ...]
     *
     * @return array<string, int>
     */
    public function findCountByCategory(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.category AS category, COUNT(c.id) AS total')
            ->groupBy('c.category')
            ->getQuery()
            ->getScalarResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['category']] = (int) $row['total'];
        }
        return $result;
    }

    /**
     * Returns complaint counts grouped by category AND status.
     * e.g. [['category'=>'plumbing','status'=>'pending','total'=>3], ...]
     */
    public function findCountByCategoryAndStatus(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.category AS category, c.status AS status, COUNT(c.id) AS total')
            ->groupBy('c.category, c.status')
            ->getQuery()
            ->getScalarResult();
    }

    /**
     * Most recent complaints with eager-loaded room and student for admin dashboard.
     *
     * @return Complaint[]
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.room', 'r')->addSelect('r')
            ->leftJoin('c.student', 's')->addSelect('s')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
