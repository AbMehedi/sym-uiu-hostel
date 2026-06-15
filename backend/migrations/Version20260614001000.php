<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop unique index on supervisors.hostel_assigned so multiple supervisors
 * can be assigned to the same hostel.
 * Also widen the hostel_assigned column to 100 chars for longer hostel names.
 */
final class Version20260614001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow multiple supervisors per hostel: drop uniq_supervisors_hostel, widen hostel_assigned to 100 chars.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $indexes = array_change_key_case($schemaManager->listTableIndexes('supervisors'), CASE_LOWER);

        // Drop the unique constraint if it still exists (idempotent).
        if (isset($indexes['uniq_supervisors_hostel'])) {
            $this->addSql('DROP INDEX uniq_supervisors_hostel ON supervisors');
        }

        // Widen column to accommodate longer hostel names (e.g. "UIU Boys Hostel I").
        $columns = array_change_key_case($schemaManager->listTableColumns('supervisors'), CASE_LOWER);
        if (isset($columns['hostel_assigned'])) {
            $this->addSql('ALTER TABLE supervisors MODIFY hostel_assigned VARCHAR(100) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // Restore uniqueness (only possible if data is already unique).
        $this->addSql('CREATE UNIQUE INDEX uniq_supervisors_hostel ON supervisors (hostel_assigned)');
        $this->addSql('ALTER TABLE supervisors MODIFY hostel_assigned VARCHAR(50) DEFAULT NULL');
    }
}
