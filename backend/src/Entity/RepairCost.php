<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'repair_costs')]
class RepairCost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Complaint::class, inversedBy: 'repairCosts')]
    #[ORM\JoinColumn(name: 'complaint_id', nullable: false, onDelete: 'CASCADE')]
    private ?Complaint $complaint = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'cost_date', type: 'date_immutable')]
    private DateTimeImmutable $costDate;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'repairCosts')]
    #[ORM\JoinColumn(name: 'recorded_by', nullable: false, onDelete: 'RESTRICT')]
    private ?User $recordedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getComplaint(): ?Complaint
    {
        return $this->complaint;
    }

    public function setComplaint(?Complaint $complaint): self
    {
        $this->complaint = $complaint;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCostDate(): DateTimeImmutable
    {
        return $this->costDate;
    }

    public function setCostDate(DateTimeImmutable $costDate): self
    {
        $this->costDate = $costDate;

        return $this;
    }

    public function getRecordedBy(): ?User
    {
        return $this->recordedBy;
    }

    public function setRecordedBy(?User $recordedBy): self
    {
        $this->recordedBy = $recordedBy;

        return $this;
    }
}
