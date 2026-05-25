<?php

namespace App\Repository;

use App\Entity\RepairCost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RepairCost>
 */
class RepairCostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RepairCost::class);
    }

    /**
     * Returns total repair cost per complaint category.
     * e.g. ['plumbing' => 4500.00, 'electricity' => 3200.00, ...]
     *
     * @return array<string, float>
     */
    public function findTotalByCategory(): array
    {
        $rows = $this->createQueryBuilder('rc')
            ->select('c.category AS category, SUM(rc.amount) AS total')
            ->join('rc.complaint', 'c')
            ->groupBy('c.category')
            ->getQuery()
            ->getScalarResult();

        $result = [];
        foreach ($rows as $row) {
            // category is a backed enum value e.g. 'plumbing'
            $result[$row['category']] = (float) $row['total'];
        }

        return $result;
    }

    /**
     * Grand total of all repair costs.
     */
    public function findGrandTotal(): float
    {
        $total = $this->createQueryBuilder('rc')
            ->select('SUM(rc.amount)')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($total ?? 0);
    }
}
