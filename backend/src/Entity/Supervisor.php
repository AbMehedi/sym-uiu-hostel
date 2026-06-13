<?php

namespace App\Entity;

use App\Enum\Gender;
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

    #[ORM\Column(name: 'hostel_assigned', length: 100, nullable: true)]
    private ?string $hostelAssigned = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(enumType: Gender::class, nullable: true)]
    private ?Gender $gender = null;

    #[ORM\Column(name: 'nid_number', length: 50, nullable: true)]
    private ?string $nidNumber = null;

    #[ORM\Column(name: 'nid_document_path', length: 255, nullable: true)]
    private ?string $nidDocumentPath = null;

    #[ORM\Column(name: 'additional_docs', type: 'json', nullable: true)]
    private ?array $additionalDocs = null;

    #[ORM\OneToMany(mappedBy: 'reviewedBy', targetEntity: RoomChangeRequest::class)]
    private Collection $roomChangeRequests;

    #[ORM\OneToMany(mappedBy: 'assignedTo', targetEntity: Complaint::class)]
    private Collection $complaints;

    #[ORM\OneToMany(mappedBy: 'supervisor', targetEntity: Announcement::class, orphanRemoval: true)]
    private Collection $announcements;

    #[ORM\OneToMany(mappedBy: 'supervisor', targetEntity: SupervisorTask::class, orphanRemoval: true)]
    private Collection $tasks;

    #[ORM\OneToMany(mappedBy: 'supervisor', targetEntity: Room::class)]
    private Collection $rooms;

    public function __construct()
    {
        $this->roomChangeRequests = new ArrayCollection();
        $this->complaints = new ArrayCollection();
        $this->announcements = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->rooms = new ArrayCollection();
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

    public function getHostelAssigned(): ?string
    {
        return $this->hostelAssigned;
    }

    public function setHostelAssigned(?string $hostel): self
    {
        $this->hostelAssigned = $hostel;

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

    public function getGender(): ?Gender
    {
        return $this->gender;
    }

    public function setGender(?Gender $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function getNidNumber(): ?string
    {
        return $this->nidNumber;
    }

    public function setNidNumber(?string $nidNumber): self
    {
        $this->nidNumber = $nidNumber;

        return $this;
    }

    public function getNidDocumentPath(): ?string
    {
        return $this->nidDocumentPath;
    }

    public function setNidDocumentPath(?string $nidDocumentPath): self
    {
        $this->nidDocumentPath = $nidDocumentPath;

        return $this;
    }

    public function getAdditionalDocs(): array
    {
        return $this->additionalDocs ?? [];
    }

    public function setAdditionalDocs(?array $additionalDocs): self
    {
        $this->additionalDocs = $additionalDocs;

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

    /** @return Collection<int, Room> */
    public function getRooms(): Collection
    {
        return $this->rooms;
    }
}
