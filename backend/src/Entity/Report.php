<?php

namespace App\Entity;

use App\Enum\ReportType;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reports')]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'report_type', enumType: ReportType::class)]
    private ReportType $reportType;

    #[ORM\Column(name: 'generated_date', type: 'date_immutable')]
    private DateTimeImmutable $generatedDate;

    #[ORM\Column(name: 'file_url', length: 255)]
    private string $fileUrl;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reports')]
    #[ORM\JoinColumn(name: 'generated_by', nullable: false, onDelete: 'RESTRICT')]
    private ?User $generatedBy = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReportType(): ReportType
    {
        return $this->reportType;
    }

    public function setReportType(ReportType $reportType): self
    {
        $this->reportType = $reportType;

        return $this;
    }

    public function getGeneratedDate(): DateTimeImmutable
    {
        return $this->generatedDate;
    }

    public function setGeneratedDate(DateTimeImmutable $generatedDate): self
    {
        $this->generatedDate = $generatedDate;

        return $this;
    }

    public function getFileUrl(): string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(string $fileUrl): self
    {
        $this->fileUrl = $fileUrl;

        return $this;
    }

    public function getGeneratedBy(): ?User
    {
        return $this->generatedBy;
    }

    public function setGeneratedBy(?User $generatedBy): self
    {
        $this->generatedBy = $generatedBy;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
