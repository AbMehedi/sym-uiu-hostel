<?php

namespace App\Entity;

use App\Enum\AdmissionStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'students')]
#[ORM\UniqueConstraint(name: 'uniq_students_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_students_number', columns: ['student_number'])]
class Student
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'student', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'student_number', length: 50, unique: true)]
    private string $studentNumber;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'emergency_contact', length: 150, nullable: true)]
    private ?string $emergencyContact = null;

    #[ORM\Column(name: 'admission_status', enumType: AdmissionStatus::class)]
    private AdmissionStatus $admissionStatus = AdmissionStatus::Pending;

    #[ORM\Column(name: 'admission_date', type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $admissionDate = null;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: RoomAssignment::class, orphanRemoval: true)]
    private Collection $roomAssignments;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: AdmissionRequest::class, orphanRemoval: true)]
    private Collection $admissionRequests;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: RoomChangeRequest::class, orphanRemoval: true)]
    private Collection $roomChangeRequests;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: Complaint::class, orphanRemoval: true)]
    private Collection $complaints;

    public function __construct()
    {
        $this->roomAssignments = new ArrayCollection();
        $this->admissionRequests = new ArrayCollection();
        $this->roomChangeRequests = new ArrayCollection();
        $this->complaints = new ArrayCollection();
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

        if ($user->getStudent() !== $this) {
            $user->setStudent($this);
        }

        return $this;
    }

    public function getStudentNumber(): string
    {
        return $this->studentNumber;
    }

    public function setStudentNumber(string $studentNumber): self
    {
        $this->studentNumber = $studentNumber;

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

    public function getEmergencyContact(): ?string
    {
        return $this->emergencyContact;
    }

    public function setEmergencyContact(?string $emergencyContact): self
    {
        $this->emergencyContact = $emergencyContact;

        return $this;
    }

    public function getAdmissionStatus(): AdmissionStatus
    {
        return $this->admissionStatus;
    }

    public function setAdmissionStatus(AdmissionStatus $admissionStatus): self
    {
        $this->admissionStatus = $admissionStatus;

        return $this;
    }

    public function getAdmissionDate(): ?DateTimeImmutable
    {
        return $this->admissionDate;
    }

    public function setAdmissionDate(?DateTimeImmutable $admissionDate): self
    {
        $this->admissionDate = $admissionDate;

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
            $assignment->setStudent($this);
        }

        return $this;
    }

    public function removeRoomAssignment(RoomAssignment $assignment): self
    {
        if ($this->roomAssignments->removeElement($assignment) && $assignment->getStudent() === $this) {
            $assignment->setStudent(null);
        }

        return $this;
    }

    /** @return Collection<int, AdmissionRequest> */
    public function getAdmissionRequests(): Collection
    {
        return $this->admissionRequests;
    }

    public function addAdmissionRequest(AdmissionRequest $request): self
    {
        if (!$this->admissionRequests->contains($request)) {
            $this->admissionRequests->add($request);
            $request->setStudent($this);
        }

        return $this;
    }

    public function removeAdmissionRequest(AdmissionRequest $request): self
    {
        if ($this->admissionRequests->removeElement($request) && $request->getStudent() === $this) {
            $request->setStudent(null);
        }

        return $this;
    }

    /** @return Collection<int, RoomChangeRequest> */
    public function getRoomChangeRequests(): Collection
    {
        return $this->roomChangeRequests;
    }

    public function addRoomChangeRequest(RoomChangeRequest $request): self
    {
        if (!$this->roomChangeRequests->contains($request)) {
            $this->roomChangeRequests->add($request);
            $request->setStudent($this);
        }

        return $this;
    }

    public function removeRoomChangeRequest(RoomChangeRequest $request): self
    {
        if ($this->roomChangeRequests->removeElement($request) && $request->getStudent() === $this) {
            $request->setStudent(null);
        }

        return $this;
    }

    /** @return Collection<int, Complaint> */
    public function getComplaints(): Collection
    {
        return $this->complaints;
    }
}
