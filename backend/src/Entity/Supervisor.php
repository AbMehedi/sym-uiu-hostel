<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'supervisors')]
#[ORM\UniqueConstraint(name: 'uniq_supervisors_user', columns: ['user_id'])]
class Supervisor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'supervisor', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'block_assigned', length: 50, nullable: true)]
    private ?string $blockAssigned = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\OneToMany(mappedBy: 'reviewedBy', targetEntity: RoomChangeRequest::class)]
    private Collection $roomChangeRequests;

    #[ORM\OneToMany(mappedBy: 'assignedTo', targetEntity: Complaint::class)]
    private Collection $complaints;

    #[ORM\OneToMany(mappedBy: 'supervisor', targetEntity: Announcement::class, orphanRemoval: true)]
    private Collection $announcements;

    #[ORM\OneToMany(mappedBy: 'supervisor', targetEntity: SupervisorTask::class, orphanRemoval: true)]
    private Collection $tasks;

    public function __construct()
    {
        $this->roomChangeRequests = new ArrayCollection();
        $this->complaints = new ArrayCollection();
        $this->announcements = new ArrayCollection();
        $this->tasks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        if ($user->getSupervisor() !== $this) {
            $user->setSupervisor($this);
        }

        return $this;
    }

    public function getBlockAssigned(): ?string
    {
        return $this->blockAssigned;
    }

    public function setBlockAssigned(?string $blockAssigned): self
    {
        $this->blockAssigned = $blockAssigned;

        return $this;
    }

    public function getHostelBlock(): ?string
    {
        return $this->blockAssigned;
    }

    public function setHostelBlock(?string $hostelBlock): self
    {
        $this->blockAssigned = $hostelBlock;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /** @return Collection<int, RoomChangeRequest> */
    public function getRoomChangeRequests(): Collection
    {
        return $this->roomChangeRequests;
    }

    /** @return Collection<int, Complaint> */
    public function getComplaints(): Collection
    {
        return $this->complaints;
    }

    /** @return Collection<int, Announcement> */
    public function getAnnouncements(): Collection
    {
        return $this->announcements;
    }

    /** @return Collection<int, SupervisorTask> */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }
}
