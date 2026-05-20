<?php

namespace App\Entity;

use App\Enum\RoomStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rooms')]
#[ORM\UniqueConstraint(name: 'uniq_rooms_number', columns: ['room_number'])]
#[ORM\Index(name: 'idx_rooms_block_status', columns: ['block', 'status'])]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'room_number', length: 20, unique: true)]
    private string $roomNumber;

    #[ORM\Column(length: 50)]
    private string $block;

    #[ORM\Column(type: 'integer')]
    private int $floor;

    #[ORM\Column(type: 'integer')]
    private int $capacity;

    #[ORM\Column(name: 'current_occupancy', type: 'integer', options: ['default' => 0])]
    private int $currentOccupancy = 0;

    #[ORM\Column(name: 'room_type', length: 50, nullable: true)]
    private ?string $roomType = null;

    #[ORM\Column(enumType: RoomStatus::class)]
    private RoomStatus $status = RoomStatus::Available;

    #[ORM\OneToMany(mappedBy: 'room', targetEntity: RoomAssignment::class, orphanRemoval: true)]
    private Collection $roomAssignments;

    #[ORM\OneToMany(mappedBy: 'room', targetEntity: Complaint::class, orphanRemoval: true)]
    private Collection $complaints;

    public function __construct()
    {
        $this->roomAssignments = new ArrayCollection();
        $this->complaints = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoomNumber(): string
    {
        return $this->roomNumber;
    }

    public function setRoomNumber(string $roomNumber): self
    {
        $this->roomNumber = $roomNumber;

        return $this;
    }

    public function getBlock(): string
    {
        return $this->block;
    }

    public function setBlock(string $block): self
    {
        $this->block = $block;

        return $this;
    }

    public function getFloor(): int
    {
        return $this->floor;
    }

    public function setFloor(int $floor): self
    {
        $this->floor = $floor;

        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getCurrentOccupancy(): int
    {
        return $this->currentOccupancy;
    }

    public function setCurrentOccupancy(int $currentOccupancy): self
    {
        $this->currentOccupancy = $currentOccupancy;

        return $this;
    }

    public function getRoomType(): ?string
    {
        return $this->roomType;
    }

    public function setRoomType(?string $roomType): self
    {
        $this->roomType = $roomType;

        return $this;
    }

    public function getStatus(): RoomStatus
    {
        return $this->status;
    }

    public function setStatus(RoomStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /** @return Collection<int, RoomAssignment> */
    public function getRoomAssignments(): Collection
    {
        return $this->roomAssignments;
    }

    public function addRoomAssignment(RoomAssignment $assignment): self
    {
        if (!$this->roomAssignments->contains($assignment)) {
            $this->roomAssignments->add($assignment);
            $assignment->setRoom($this);
        }

        return $this;
    }

    public function removeRoomAssignment(RoomAssignment $assignment): self
    {
        if ($this->roomAssignments->removeElement($assignment) && $assignment->getRoom() === $this) {
            $assignment->setRoom(null);
        }

        return $this;
    }

    /** @return Collection<int, Complaint> */
    public function getComplaints(): Collection
    {
        return $this->complaints;
    }
}
