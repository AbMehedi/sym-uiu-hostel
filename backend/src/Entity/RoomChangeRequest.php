<?php

namespace App\Entity;

use App\Enum\RequestStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'room_change_requests')]
class RoomChangeRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class, inversedBy: 'roomChangeRequests')]
    #[ORM\JoinColumn(name: 'student_id', nullable: false, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(name: 'current_room_id', nullable: false, onDelete: 'RESTRICT')]
    private ?Room $currentRoom = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(name: 'requested_room_id', nullable: false, onDelete: 'RESTRICT')]
    private ?Room $requestedRoom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(enumType: RequestStatus::class)]
    private RequestStatus $status = RequestStatus::Pending;

    #[ORM\ManyToOne(targetEntity: Supervisor::class, inversedBy: 'roomChangeRequests')]
    #[ORM\JoinColumn(name: 'reviewed_by', nullable: true, onDelete: 'SET NULL')]
    private ?Supervisor $reviewedBy = null;

    #[ORM\Column(name: 'reviewed_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(name: 'requested_at', type: 'datetime_immutable')]
    private DateTimeImmutable $requestedAt;

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

    public function getCurrentRoom(): ?Room
    {
        return $this->currentRoom;
    }

    public function setCurrentRoom(?Room $currentRoom): self
    {
        $this->currentRoom = $currentRoom;

        return $this;
    }

    public function getRequestedRoom(): ?Room
    {
        return $this->requestedRoom;
    }

    public function setRequestedRoom(?Room $requestedRoom): self
    {
        $this->requestedRoom = $requestedRoom;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getStatus(): RequestStatus
    {
        return $this->status;
    }

    public function setStatus(RequestStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getReviewedBy(): ?Supervisor
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?Supervisor $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getReviewedAt(): ?DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?DateTimeImmutable $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getRequestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(DateTimeImmutable $requestedAt): self
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }
}
