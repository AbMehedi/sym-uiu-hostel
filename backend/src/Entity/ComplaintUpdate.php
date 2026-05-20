<?php

namespace App\Entity;

use App\Enum\ComplaintStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'complaint_updates')]
class ComplaintUpdate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Complaint::class, inversedBy: 'updates')]
    #[ORM\JoinColumn(name: 'complaint_id', nullable: false, onDelete: 'CASCADE')]
    private ?Complaint $complaint = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'complaintUpdates')]
    #[ORM\JoinColumn(name: 'updated_by', nullable: false, onDelete: 'RESTRICT')]
    private ?User $updatedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(enumType: ComplaintStatus::class)]
    private ComplaintStatus $status;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new DateTimeImmutable();
    }

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

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getStatus(): ComplaintStatus
    {
        return $this->status;
    }

    public function setStatus(ComplaintStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
