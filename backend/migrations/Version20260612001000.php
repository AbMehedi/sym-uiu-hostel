<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add supervisor identity fields (NID) and document uploads metadata.
 */
final class Version20260612001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nid_number, nid_document_path, additional_docs to supervisors';
    }

    public function up(Schema $schema): void
    {
        $columns = array_change_key_case(
            $this->connection->createSchemaManager()->listTableColumns('supervisors'),
            CASE_LOWER
        );

        if (!isset($columns['nid_number'])) {
            $this->addSql('ALTER TABLE supervisors ADD COLUMN nid_number VARCHAR(50) DEFAULT NULL');
        }
        if (!isset($columns['nid_document_path'])) {
            $this->addSql('ALTER TABLE supervisors ADD COLUMN nid_document_path VARCHAR(255) DEFAULT NULL');
        }
        if (!isset($columns['additional_docs'])) {
            $this->addSql('ALTER TABLE supervisors ADD COLUMN additional_docs JSON DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $columns = array_change_key_case(
            $this->connection->createSchemaManager()->listTableColumns('supervisors'),
            CASE_LOWER
        );

        if (isset($columns['additional_docs'])) {
            $this->addSql('ALTER TABLE supervisors DROP COLUMN additional_docs');
        }
        if (isset($columns['nid_document_path'])) {
            $this->addSql('ALTER TABLE supervisors DROP COLUMN nid_document_path');
        }
        if (isset($columns['nid_number'])) {
            $this->addSql('ALTER TABLE supervisors DROP COLUMN nid_number');
        }
    }
}
