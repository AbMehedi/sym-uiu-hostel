<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename rooms.block to rooms.hostel to match the domain language used across the app.
 */
final class Version20260612003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename rooms.block column to hostel (and update index name).';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $columns = array_change_key_case($schemaManager->listTableColumns('rooms'), CASE_LOWER);
        if (isset($columns['block']) && !isset($columns['hostel'])) {
            $this->addSql('ALTER TABLE rooms CHANGE block hostel VARCHAR(50) NOT NULL');
        }

        $indexes = array_change_key_case($schemaManager->listTableIndexes('rooms'), CASE_LOWER);

        // Drop old index name (it may still exist even after column rename).
        if (isset($indexes['idx_rooms_block_status']) && !isset($indexes['idx_rooms_hostel_status'])) {
            $this->addSql('DROP INDEX idx_rooms_block_status ON rooms');
        }

        // Create new index if missing.
        $indexes = array_change_key_case($schemaManager->listTableIndexes('rooms'), CASE_LOWER);
        $columns = array_change_key_case($schemaManager->listTableColumns('rooms'), CASE_LOWER);
        if (!isset($indexes['idx_rooms_hostel_status']) && isset($columns['hostel'])) {
            $this->addSql('CREATE INDEX idx_rooms_hostel_status ON rooms (hostel, status)');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $columns = array_change_key_case($schemaManager->listTableColumns('rooms'), CASE_LOWER);
        if (isset($columns['hostel']) && !isset($columns['block'])) {
            $this->addSql('ALTER TABLE rooms CHANGE hostel block VARCHAR(50) NOT NULL');
        }

        $indexes = array_change_key_case($schemaManager->listTableIndexes('rooms'), CASE_LOWER);

        if (isset($indexes['idx_rooms_hostel_status']) && !isset($indexes['idx_rooms_block_status'])) {
            $this->addSql('DROP INDEX idx_rooms_hostel_status ON rooms');
        }

        $indexes = array_change_key_case($schemaManager->listTableIndexes('rooms'), CASE_LOWER);
        $columns = array_change_key_case($schemaManager->listTableColumns('rooms'), CASE_LOWER);
        if (!isset($indexes['idx_rooms_block_status']) && isset($columns['block'])) {
            $this->addSql('CREATE INDEX idx_rooms_block_status ON rooms (block, status)');
        }
    }
}

