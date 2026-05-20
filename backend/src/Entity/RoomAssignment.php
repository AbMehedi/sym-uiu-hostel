<?php

namespace App\Entity;

use App\Enum\AssignmentStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'room_assignments')]
#[ORM\Index(name: 'idx_room_assignments_student_status', columns: ['student_id', 'status'])]
class RoomAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class, inversedBy: 'roomAssignments')]
    #[ORM\JoinColumn(name: 'student_id', nullable: false, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Room::class, inversedBy: 'roomAssignments')]
    #[ORM\JoinColumn(name: 'room_id', nullable: false, onDelete: 'RESTRICT')]
    private ?Room $room = null;

    #[ORM\Column(name: 'assigned_date', type: 'date_immutable')]
    private DateTimeImmutable $assignedDate;

    #[ORM\Column(name: 'vacated_date', type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $vacatedDate = null;

    #[ORM\Column(enumType: AssignmentStatus::class)]
    private AssignmentStatus $status = AssignmentStatus::Active;

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

    public function getAssignedDate(): DateTimeImmutable
    {
        return $this->assignedDate;
    }

    public function setAssignedDate(DateTimeImmutable $assignedDate): self
    {
        $this->assignedDate = $assignedDate;

        return $this;
    }

    public function getVacatedDate(): ?DateTimeImmutable
    {
        return $this->vacatedDate;
    }

    public function setVacatedDate(?DateTimeImmutable $vacatedDate): self
    {
        $this->vacatedDate = $vacatedDate;

        return $this;
    }

    public function getStatus(): AssignmentStatus
    {
        return $this->status;
    }

    public function setStatus(AssignmentStatus $status): self
    {
        $this->status = $status;

        return $this;
    }
}
