<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds preferred_room_type to admission_requests and requested_at to room_change_requests.
 * Phase A entity additions.
 */
final class Version20260527001001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preferred_room_type to admission_requests and requested_at to room_change_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admission_requests ADD COLUMN preferred_room_type VARCHAR(50) DEFAULT NULL');
        $this->addSql("ALTER TABLE room_change_requests ADD COLUMN requested_at DATETIME NOT NULL DEFAULT NOW()");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admission_requests DROP COLUMN preferred_room_type');
        $this->addSql('ALTER TABLE room_change_requests DROP COLUMN requested_at');
    }
}
