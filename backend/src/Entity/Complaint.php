<?php

namespace App\Entity;

use App\Enum\ComplaintCategory;
use App\Enum\ComplaintStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'complaints')]
#[ORM\Index(name: 'idx_complaints_status_assigned', columns: ['status', 'assigned_to'])]
class Complaint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class, inversedBy: 'complaints')]
    #[ORM\JoinColumn(name: 'student_id', nullable: false, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'complaints')]
    #[ORM\JoinColumn(name: 'room_id', nullable: false, onDelete: 'RESTRICT')]
    private ?Room $room = null;

    #[ORM\Column(enumType: ComplaintCategory::class)]
    private ComplaintCategory $category;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(name: 'photo_url', length: 255, nullable: true)]
    private ?string $photoUrl = null;

    #[ORM\Column(enumType: ComplaintStatus::class)]
    private ComplaintStatus $status = ComplaintStatus::Pending;

    #[ORM\ManyToOne(targetEntity: Supervisor::class, inversedBy: 'complaints')]
    #[ORM\JoinColumn(name: 'assigned_to', nullable: true, onDelete: 'SET NULL')]
    private ?Supervisor $assignedTo = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[ORM\OneToMany(mappedBy: 'complaint', targetEntity: ComplaintUpdate::class, orphanRemoval: true)]
    private Collection $updates;

    #[ORM\OneToMany(mappedBy: 'complaint', targetEntity: RepairCost::class, orphanRemoval: true)]
    private Collection $repairCosts;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updates = new ArrayCollection();
        $this->repairCosts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): self
    {
        $this->student = $student;

        return $this;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): self
    {
        $this->room = $room;

        return $this;
    }

    public function getCategory(): ComplaintCategory
    {
        return $this->category;
    }

    public function setCategory(ComplaintCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): self
    {
        $this->photoUrl = $photoUrl;

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

    public function getAssignedTo(): ?Supervisor
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?Supervisor $assignedTo): self
    {
        $this->assignedTo = $assignedTo;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?DateTimeImmutable $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    /** @return Collection<int, ComplaintUpdate> */
    public function getUpdates(): Collection
    {
        return $this->updates;
    }

    public function addUpdate(ComplaintUpdate $update): self
    {
        if (!$this->updates->contains($update)) {
            $this->updates->add($update);
            $update->setComplaint($this);
        }

        return $this;
    }

    public function removeUpdate(ComplaintUpdate $update): self
    {
        if ($this->updates->removeElement($update) && $update->getComplaint() === $this) {
            $update->setComplaint(null);
        }

        return $this;
    }

    /** @return Collection<int, RepairCost> */
    public function getRepairCosts(): Collection
    {
        return $this->repairCosts;
    }
}
