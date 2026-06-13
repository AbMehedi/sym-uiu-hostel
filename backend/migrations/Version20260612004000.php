<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename supervisors.block_assigned to supervisors.hostel_assigned and relate rooms to supervisors.
 */
final class Version20260612004000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename supervisors.block_assigned to hostel_assigned, update unique index, and add rooms.supervisor_id FK.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        // ── supervisors: block_assigned → hostel_assigned ────────────────────
        $supColumns = array_change_key_case($schemaManager->listTableColumns('supervisors'), CASE_LOWER);
        if (isset($supColumns['block_assigned']) && !isset($supColumns['hostel_assigned'])) {
            $this->addSql('ALTER TABLE supervisors CHANGE block_assigned hostel_assigned VARCHAR(50) DEFAULT NULL');
        }

        // Ensure unique hostel assignment (1 supervisor per hostel)
        $supIndexes = array_change_key_case($schemaManager->listTableIndexes('supervisors'), CASE_LOWER);
        if (isset($supIndexes['uniq_supervisors_block']) && !isset($supIndexes['uniq_supervisors_hostel'])) {
            $this->addSql('ALTER TABLE supervisors DROP INDEX uniq_supervisors_block');
        }
        $supIndexes = array_change_key_case($schemaManager->listTableIndexes('supervisors'), CASE_LOWER);
        $supColumns = array_change_key_case($schemaManager->listTableColumns('supervisors'), CASE_LOWER);
        if (!isset($supIndexes['uniq_supervisors_hostel']) && isset($supColumns['hostel_assigned'])) {
            $this->addSql('ALTER TABLE supervisors ADD CONSTRAINT uniq_supervisors_hostel UNIQUE (hostel_assigned)');
        }

        // ── rooms: add supervisor_id FK ─────────────────────────────────────
        $roomColumns = array_change_key_case($schemaManager->listTableColumns('rooms'), CASE_LOWER);
        if (!isset($roomColumns['supervisor_id'])) {
            $this->addSql('ALTER TABLE rooms ADD COLUMN supervisor_id INT DEFAULT NULL');
        }

        // Index + FK (idempotent-ish)
        $roomIndexes = array_change_key_case($schemaManager->listTableIndexes('rooms'), CASE_LOWER);
        if (!isset($roomIndexes['idx_rooms_supervisor'])) {
            $this->addSql('CREATE INDEX idx_rooms_supervisor ON rooms (supervisor_id)');
        }

        // Add FK if missing
        $fkNames = array_map(
            static fn($fk) => strtolower($fk->getName()),
            $schemaManager->listTableForeignKeys('rooms')
        );
        if (!in_array('fk_rooms_supervisor', $fkNames, true)) {
            $this->addSql(
                'ALTER TABLE rooms ADD CONSTRAINT FK_rooms_supervisor FOREIGN KEY (supervisor_id) REFERENCES supervisors (id) ON DELETE SET NULL'
            );
        }

        // Backfill supervisor_id based on hostel name matching.
        $roomColumns = array_change_key_case($schemaManager->listTableColumns('rooms'), CASE_LOWER);
        $supColumns = array_change_key_case($schemaManager->listTableColumns('supervisors'), CASE_LOWER);
        if (isset($roomColumns['supervisor_id']) && isset($roomColumns['hostel'])) {
            if (isset($supColumns['hostel_assigned'])) {
                $this->addSql(<<<'SQL'
                    UPDATE rooms r
                    JOIN supervisors s ON s.hostel_assigned = r.hostel
                    SET r.supervisor_id = s.id
                    WHERE r.supervisor_id IS NULL
                SQL);
            } elseif (isset($supColumns['block_assigned'])) {
                // Fallback for intermediate schemas.
                $this->addSql(<<<'SQL'
                    UPDATE rooms r
                    JOIN supervisors s ON s.block_assigned = r.hostel
                    SET r.supervisor_id = s.id
                    WHERE r.supervisor_id IS NULL
                SQL);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        // ── rooms: drop FK + index + column ─────────────────────────────────
        $roomColumns = array_change_key_case($schemaManager->listTableColumns('rooms'), CASE_LOWER);
        if (isset($roomColumns['supervisor_id'])) {
            $fkNames = array_map(
                static fn($fk) => strtolower($fk->getName()),
                $schemaManager->listTableForeignKeys('rooms')
            );
            if (in_array('fk_rooms_supervisor', $fkNames, true)) {
                $this->addSql('ALTER TABLE rooms DROP FOREIGN KEY FK_rooms_supervisor');
            }

            $roomIndexes = array_change_key_case($schemaManager->listTableIndexes('rooms'), CASE_LOWER);
            if (isset($roomIndexes['idx_rooms_supervisor'])) {
                $this->addSql('DROP INDEX idx_rooms_supervisor ON rooms');
            }

            $this->addSql('ALTER TABLE rooms DROP COLUMN supervisor_id');
        }

        // ── supervisors: unique index + rename back ─────────────────────────
        $supIndexes = array_change_key_case($schemaManager->listTableIndexes('supervisors'), CASE_LOWER);
        if (isset($supIndexes['uniq_supervisors_hostel']) && !isset($supIndexes['uniq_supervisors_block'])) {
            $this->addSql('ALTER TABLE supervisors DROP INDEX uniq_supervisors_hostel');
        }

        $supColumns = array_change_key_case($schemaManager->listTableColumns('supervisors'), CASE_LOWER);
        if (isset($supColumns['hostel_assigned']) && !isset($supColumns['block_assigned'])) {
            $this->addSql('ALTER TABLE supervisors CHANGE hostel_assigned block_assigned VARCHAR(50) DEFAULT NULL');
        }

        $supIndexes = array_change_key_case($schemaManager->listTableIndexes('supervisors'), CASE_LOWER);
        $supColumns = array_change_key_case($schemaManager->listTableColumns('supervisors'), CASE_LOWER);
        if (!isset($supIndexes['uniq_supervisors_block']) && isset($supColumns['block_assigned'])) {
            $this->addSql('ALTER TABLE supervisors ADD CONSTRAINT uniq_supervisors_block UNIQUE (block_assigned)');
        }
    }
}

