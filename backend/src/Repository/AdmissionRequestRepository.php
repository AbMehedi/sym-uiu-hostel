<?php

namespace App\Repository;

use App\Entity\AdmissionRequest;
use App\Enum\RequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AdmissionRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdmissionRequest::class);
    }

    /** @return AdmissionRequest[] */
    public function findPending(): array
    {
        return $this->findBy(['status' => RequestStatus::Pending], ['requestedDate' => 'ASC']);
    }
}
