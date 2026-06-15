<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add gender field to supervisors.
 */
final class Version20260612002000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gender column to supervisors';
    }

    public function up(Schema $schema): void
    {
        $columns = array_change_key_case(
            $this->connection->createSchemaManager()->listTableColumns('supervisors'),
            CASE_LOWER
        );

        if (!isset($columns['gender'])) {
            $this->addSql("ALTER TABLE supervisors ADD COLUMN gender VARCHAR(20) DEFAULT NULL");
        }
    }

    public function down(Schema $schema): void
    {
        $columns = array_change_key_case(
            $this->connection->createSchemaManager()->listTableColumns('supervisors'),
            CASE_LOWER
        );

        if (isset($columns['gender'])) {
            $this->addSql('ALTER TABLE supervisors DROP COLUMN gender');
        }
    }
}
