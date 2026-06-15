<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615182420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE student_read_announcements (student_id INT NOT NULL, announcement_id INT NOT NULL, INDEX IDX_1C0FEB5ACB944F1A (student_id), INDEX IDX_1C0FEB5A913AEA17 (announcement_id), PRIMARY KEY (student_id, announcement_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE student_read_announcements ADD CONSTRAINT FK_1C0FEB5ACB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_read_announcements ADD CONSTRAINT FK_1C0FEB5A913AEA17 FOREIGN KEY (announcement_id) REFERENCES announcements (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_read_announcements DROP FOREIGN KEY FK_1C0FEB5ACB944F1A');
        $this->addSql('ALTER TABLE student_read_announcements DROP FOREIGN KEY FK_1C0FEB5A913AEA17');
        $this->addSql('DROP TABLE student_read_announcements');
    }
}
