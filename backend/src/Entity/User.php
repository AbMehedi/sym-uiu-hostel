<?php

namespace App\Entity;

use App\Enum\Role;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 150, unique: true)]
    private string $email;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    #[ORM\Column(enumType: Role::class)]
    private Role $role = Role::Student;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Student::class, cascade: ['persist', 'remove'])]
    private ?Student $student = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Supervisor::class, cascade: ['persist', 'remove'])]
    private ?Supervisor $supervisor = null;

    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: ChatMessage::class, orphanRemoval: true)]
    private Collection $sentMessages;

    #[ORM\OneToMany(mappedBy: 'receiver', targetEntity: ChatMessage::class, orphanRemoval: true)]
    private Collection $receivedMessages;

    #[ORM\OneToMany(mappedBy: 'updatedBy', targetEntity: ComplaintUpdate::class)]
    private Collection $complaintUpdates;

    #[ORM\OneToMany(mappedBy: 'recordedBy', targetEntity: RepairCost::class)]
    private Collection $repairCosts;

    #[ORM\OneToMany(mappedBy: 'assignedBy', targetEntity: SupervisorTask::class)]
    private Collection $assignedTasks;

    #[ORM\OneToMany(mappedBy: 'generatedBy', targetEntity: Report::class)]
    private Collection $reports;

    #[ORM\OneToMany(mappedBy: 'reviewedBy', targetEntity: AdmissionRequest::class)]
    private Collection $reviewedAdmissionRequests;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->sentMessages = new ArrayCollection();
        $this->receivedMessages = new ArrayCollection();
        $this->complaintUpdates = new ArrayCollection();
        $this->repairCosts = new ArrayCollection();
        $this->assignedTasks = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->reviewedAdmissionRequests = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return [match ($this->role) {
            Role::Admin => 'ROLE_ADMIN',
            Role::Supervisor => 'ROLE_SUPERVISOR',
            Role::Student => 'ROLE_STUDENT',
        }];
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
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

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): self
    {
        $this->student = $student;

        if ($student && $student->getUser() !== $this) {
            $student->setUser($this);
        }

        return $this;
    }

    public function getSupervisor(): ?Supervisor
    {
        return $this->supervisor;
    }

    public function setSupervisor(?Supervisor $supervisor): self
    {
        $this->supervisor = $supervisor;

        if ($supervisor && $supervisor->getUser() !== $this) {
            $supervisor->setUser($this);
        }

        return $this;
    }

    /** @return Collection<int, ChatMessage> */
    public function getSentMessages(): Collection
    {
        return $this->sentMessages;
    }

    public function addSentMessage(ChatMessage $message): self
    {
        if (!$this->sentMessages->contains($message)) {
            $this->sentMessages->add($message);
            $message->setSender($this);
        }

        return $this;
    }

    public function removeSentMessage(ChatMessage $message): self
    {
        if ($this->sentMessages->removeElement($message) && $message->getSender() === $this) {
            $message->setSender(null);
        }

        return $this;
    }

    /** @return Collection<int, ChatMessage> */
    public function getReceivedMessages(): Collection
    {
        return $this->receivedMessages;
    }

    public function addReceivedMessage(ChatMessage $message): self
    {
        if (!$this->receivedMessages->contains($message)) {
            $this->receivedMessages->add($message);
            $message->setReceiver($this);
        }

        return $this;
    }

    public function removeReceivedMessage(ChatMessage $message): self
    {
        if ($this->receivedMessages->removeElement($message) && $message->getReceiver() === $this) {
            $message->setReceiver(null);
        }

        return $this;
    }

    /** @return Collection<int, ComplaintUpdate> */
    public function getComplaintUpdates(): Collection
    {
        return $this->complaintUpdates;
    }

    /** @return Collection<int, RepairCost> */
    public function getRepairCosts(): Collection
    {
        return $this->repairCosts;
    }

    /** @return Collection<int, SupervisorTask> */
    public function getAssignedTasks(): Collection
    {
        return $this->assignedTasks;
    }

    /** @return Collection<int, Report> */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    /** @return Collection<int, AdmissionRequest> */
    public function getReviewedAdmissionRequests(): Collection
    {
        return $this->reviewedAdmissionRequests;
    }
}
