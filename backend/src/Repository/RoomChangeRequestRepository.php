<?php

namespace App\Repository;

use App\Entity\RoomChangeRequest;
use App\Enum\RequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RoomChangeRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoomChangeRequest::class);
    }

    /** @return RoomChangeRequest[] */
    public function findPending(): array
    {
        return $this->findBy(['status' => RequestStatus::Pending], ['id' => 'ASC']);
    }
}
