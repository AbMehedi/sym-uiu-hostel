<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ensure Room.supervisor_id is populated and add Student.supervisor_id relation.
 */
final class Version20260612005000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill rooms.supervisor_id and add students.supervisor_id FK (student↔supervisor relation).';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        // ── rooms: backfill supervisor_id (if column exists) ─────────────────
        $roomColumns = array_change_key_case($schemaManager->listTableColumns('rooms'), CASE_LOWER);
        $supColumns  = array_change_key_case($schemaManager->listTableColumns('supervisors'), CASE_LOWER);

        if (isset($roomColumns['supervisor_id']) && isset($roomColumns['hostel'])) {
            if (isset($supColumns['hostel_assigned'])) {
                // Exact match first.
                $this->addSql(<<<'SQL'
                    UPDATE rooms r
                    JOIN supervisors s ON s.hostel_assigned = r.hostel
                    SET r.supervisor_id = s.id
                    WHERE r.supervisor_id IS NULL
                SQL);

                // Try suffix normalization (-BLOCK ↔ -HOSTEL) for legacy data.
                $this->addSql(<<<'SQL'
                    UPDATE rooms r
                    JOIN supervisors s
                      ON UPPER(REPLACE(s.hostel_assigned, '-BLOCK', '-HOSTEL')) = UPPER(REPLACE(r.hostel, '-BLOCK', '-HOSTEL'))
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

        // ── students: add supervisor_id FK ───────────────────────────────────
        $studentColumns = array_change_key_case($schemaManager->listTableColumns('students'), CASE_LOWER);
        if (!isset($studentColumns['supervisor_id'])) {
            $this->addSql('ALTER TABLE students ADD COLUMN supervisor_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX idx_students_supervisor ON students (supervisor_id)');
            $this->addSql('ALTER TABLE students ADD CONSTRAINT FK_students_supervisor FOREIGN KEY (supervisor_id) REFERENCES supervisors (id) ON DELETE SET NULL');
        }

        // Backfill students.supervisor_id from ACTIVE room assignment → room.supervisor_id
        $studentColumns = array_change_key_case($schemaManager->listTableColumns('students'), CASE_LOWER);
        $roomColumns    = array_change_key_case($schemaManager->listTableColumns('rooms'), CASE_LOWER);

        if (isset($studentColumns['supervisor_id']) && isset($roomColumns['supervisor_id'])) {
            $this->addSql(<<<'SQL'
                UPDATE students st
                JOIN room_assignments ra ON ra.student_id = st.id AND ra.status = 'active'
                JOIN rooms r ON r.id = ra.room_id
                SET st.supervisor_id = r.supervisor_id
                WHERE st.supervisor_id IS NULL AND r.supervisor_id IS NOT NULL
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $studentColumns = array_change_key_case($schemaManager->listTableColumns('students'), CASE_LOWER);
        if (isset($studentColumns['supervisor_id'])) {
            $fkNames = array_map(
                static fn($fk) => strtolower($fk->getName()),
                $schemaManager->listTableForeignKeys('students')
            );
            if (in_array('fk_students_supervisor', $fkNames, true)) {
                $this->addSql('ALTER TABLE students DROP FOREIGN KEY FK_students_supervisor');
            }

            $indexes = array_change_key_case($schemaManager->listTableIndexes('students'), CASE_LOWER);
            if (isset($indexes['idx_students_supervisor'])) {
                $this->addSql('DROP INDEX idx_students_supervisor ON students');
            }

            $this->addSql('ALTER TABLE students DROP COLUMN supervisor_id');
        }
    }
}

