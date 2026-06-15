<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * B-20: Audit log for critical admin actions.
 * Records who did what and when, with optional JSON context.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_performed_by', columns: ['performed_by'])]
#[ORM\Index(name: 'idx_audit_action', columns: ['action'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** e.g. "supervisor.create", "supervisor.delete", "room.create", "admission.approve" */
    #[ORM\Column(length: 100)]
    private string $action;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'performed_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $performedBy = null;

    #[ORM\Column(name: 'performed_at', type: 'datetime_immutable')]
    private DateTimeImmutable $performedAt;

    /** Contextual data: e.g. ['supervisorId'=>5, 'name'=>'Rafiq'] */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $context = null;

    public function __construct(string $action, ?User $performedBy, ?array $context = null)
    {
        $this->action      = $action;
        $this->performedBy = $performedBy;
        $this->performedAt = new DateTimeImmutable();
        $this->context     = $context;
    }

    public function getId(): ?int { return $this->id; }

    public function getAction(): string { return $this->action; }

    public function getPerformedBy(): ?User { return $this->performedBy; }

    public function getPerformedAt(): DateTimeImmutable { return $this->performedAt; }

    public function getContext(): ?array { return $this->context; }
}
