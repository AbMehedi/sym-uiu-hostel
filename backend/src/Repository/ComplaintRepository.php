<?php

namespace App\Repository;

use App\Entity\Complaint;
use App\Enum\ComplaintStatus;
use DateTimeImmutable;
use DateTimeInterface;
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

    public function countByStatus(ComplaintStatus $status): int
    {
        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    public function countResolvedBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :status')
            ->andWhere('c.resolvedAt >= :start')
            ->andWhere('c.resolvedAt < :end')
            ->setParameter('status', ComplaintStatus::Resolved)
            ->setParameter('start', DateTimeImmutable::createFromInterface($start))
            ->setParameter('end', DateTimeImmutable::createFromInterface($end))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * Filter complaints by category type, status string, and/or date range.
     * B-10 (type/status filters) + B-23 (date range filter).
     *
     * @return Complaint[]
     */
    public function findFiltered(
        ?string $type,
        ?string $status,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if ($type) {
            try {
                $categoryEnum = \App\Enum\ComplaintCategory::from(strtolower($type));
                $qb->andWhere('c.category = :category')->setParameter('category', $categoryEnum);
            } catch (\ValueError) {
                // invalid category — ignore filter
            }
        }

        if ($status) {
            $statusMap = [
                'pending'     => ComplaintStatus::Pending,
                'in_progress' => ComplaintStatus::InProgress,
                'resolved'    => ComplaintStatus::Resolved,
            ];
            if (isset($statusMap[$status])) {
                $qb->andWhere('c.status = :status')->setParameter('status', $statusMap[$status]);
            }
        }

        if ($from) {
            $qb->andWhere('c.createdAt >= :from')
               ->setParameter('from', DateTimeImmutable::createFromInterface($from));
        }

        if ($to) {
            $qb->andWhere('c.createdAt <= :to')
               ->setParameter('to', DateTimeImmutable::createFromInterface($to));
        }

        return $qb->getQuery()->getResult();
    }
}
