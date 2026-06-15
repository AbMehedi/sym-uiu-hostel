<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611192618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_logs CHANGE performed_at performed_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE repair_costs RENAME INDEX fk_repair_costs_room TO IDX_FC2930454177093');
        $this->addSql('ALTER TABLE supervisor_tasks CHANGE priority priority VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE supervisors ADD nid_number VARCHAR(50) DEFAULT NULL, ADD nid_document_path VARCHAR(255) DEFAULT NULL, ADD additional_docs JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_logs CHANGE performed_at performed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE repair_costs RENAME INDEX idx_fc2930454177093 TO FK_repair_costs_room');
        $this->addSql('ALTER TABLE supervisor_tasks CHANGE priority priority VARCHAR(20) DEFAULT \'normal\' NOT NULL');
        $this->addSql('ALTER TABLE supervisors DROP nid_number, DROP nid_document_path, DROP additional_docs');
    }
}
