<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bug-fix migration: B-08 (Supervisor block unique), B-17 (TaskPriority enum),
 * B-20 (AuditLog table), B-21 (RepairCost.room_id nullable FK)
 */
final class Version20260611001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bug fixes: unique block on supervisors, priority on supervisor_tasks, audit_logs table, room_id on repair_costs';
    }

    public function up(Schema $schema): void
    {
        // B-08: Unique constraint on supervisors.block_assigned
        // (nullable unique — NULL values are not considered equal in SQL, so multiple NULLs are fine)
        $this->addSql(
            'ALTER TABLE supervisors ADD CONSTRAINT uniq_supervisors_block UNIQUE (block_assigned)'
        );

        // B-17: Add priority column to supervisor_tasks (default 'normal')
        $this->addSql(
            "ALTER TABLE supervisor_tasks ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'normal'"
        );

        // B-20: Create audit_logs table
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_logs (
                id            INT AUTO_INCREMENT NOT NULL,
                performed_by  INT      DEFAULT NULL,
                action        VARCHAR(100) NOT NULL,
                performed_at  DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                context       JSON     DEFAULT NULL,
                INDEX idx_audit_performed_by (performed_by),
                INDEX idx_audit_action (action),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs
            ADD CONSTRAINT FK_audit_logs_user
            FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
        SQL);

        // B-21: Add nullable room_id FK on repair_costs for standalone costs
        $this->addSql(
            'ALTER TABLE repair_costs ADD COLUMN room_id INT DEFAULT NULL'
        );
        $this->addSql(
            'ALTER TABLE repair_costs MODIFY complaint_id INT DEFAULT NULL'
        );
        $this->addSql(<<<'SQL'
            ALTER TABLE repair_costs
            ADD CONSTRAINT FK_repair_costs_room
            FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // B-21
        $this->addSql('ALTER TABLE repair_costs DROP FOREIGN KEY FK_repair_costs_room');
        $this->addSql('ALTER TABLE repair_costs DROP COLUMN room_id');
        $this->addSql('ALTER TABLE repair_costs MODIFY complaint_id INT NOT NULL');

        // B-20
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_audit_logs_user');
        $this->addSql('DROP TABLE audit_logs');

        // B-17
        $this->addSql('ALTER TABLE supervisor_tasks DROP COLUMN priority');

        // B-08
        $this->addSql('ALTER TABLE supervisors DROP INDEX uniq_supervisors_block');
    }
}
