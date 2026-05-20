<?php

namespace App\Entity;

use App\Enum\TaskStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'supervisor_tasks')]
class SupervisorTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Supervisor::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'supervisor_id', nullable: false, onDelete: 'CASCADE')]
    private ?Supervisor $supervisor = null;

    #[ORM\Column(length: 150)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: TaskStatus::class)]
    private TaskStatus $status = TaskStatus::Pending;

    #[ORM\Column(name: 'due_date', type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $dueDate = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'assignedTasks')]
    #[ORM\JoinColumn(name: 'assigned_by', nullable: false, onDelete: 'RESTRICT')]
    private ?User $assignedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupervisor(): ?Supervisor
    {
        return $this->supervisor;
    }

    public function setSupervisor(?Supervisor $supervisor): self
    {
        $this->supervisor = $supervisor;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getDueDate(): ?DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?DateTimeImmutable $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getAssignedBy(): ?User
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?User $assignedBy): self
    {
        $this->assignedBy = $assignedBy;

        return $this;
    }
}
