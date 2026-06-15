<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615173610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE room_change_requests CHANGE requested_at requested_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX idx_rooms_hostel_status ON rooms (hostel, status)');
        $this->addSql('ALTER TABLE rooms RENAME INDEX idx_rooms_supervisor TO IDX_7CA11A9619E9AC5F');
        $this->addSql('ALTER TABLE students ADD id_card_picture_path VARCHAR(255) DEFAULT NULL, ADD nid_or_birth_cert_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE students RENAME INDEX idx_students_supervisor TO IDX_A4698DB219E9AC5F');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE room_change_requests CHANGE requested_at requested_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_rooms_hostel_status ON rooms');
        $this->addSql('ALTER TABLE rooms RENAME INDEX idx_7ca11a9619e9ac5f TO idx_rooms_supervisor');
        $this->addSql('ALTER TABLE students DROP id_card_picture_path, DROP nid_or_birth_cert_path');
        $this->addSql('ALTER TABLE students RENAME INDEX idx_a4698db219e9ac5f TO idx_students_supervisor');
    }
}
