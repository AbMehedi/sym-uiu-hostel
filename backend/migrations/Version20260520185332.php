<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260520185332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admission_requests (id INT AUTO_INCREMENT NOT NULL, requested_date DATE NOT NULL, status VARCHAR(255) NOT NULL, admin_notes LONGTEXT DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, student_id INT NOT NULL, reviewed_by INT DEFAULT NULL, INDEX IDX_F0AD5D80CB944F1A (student_id), INDEX IDX_F0AD5D8085D7FB47 (reviewed_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE announcements (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) NOT NULL, body LONGTEXT NOT NULL, target_block VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, supervisor_id INT NOT NULL, INDEX IDX_F422A9D19E9AC5F (supervisor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE chat_messages (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, sent_at DATETIME NOT NULL, is_read TINYINT DEFAULT 0 NOT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_EF20C9A6F624B39D (sender_id), INDEX IDX_EF20C9A6CD53EDB6 (receiver_id), INDEX idx_chat_messages_sender_receiver_sent (sender_id, receiver_id, sent_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE complaint_updates (id INT AUTO_INCREMENT NOT NULL, note LONGTEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, updated_at DATETIME NOT NULL, complaint_id INT NOT NULL, updated_by INT NOT NULL, INDEX IDX_3000F361EDAE188E (complaint_id), INDEX IDX_3000F36116FE72E1 (updated_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE complaints (id INT AUTO_INCREMENT NOT NULL, category VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, photo_url VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, student_id INT NOT NULL, room_id INT NOT NULL, assigned_to INT DEFAULT NULL, INDEX IDX_A05AAF3ACB944F1A (student_id), INDEX IDX_A05AAF3A54177093 (room_id), INDEX IDX_A05AAF3A89EEAF91 (assigned_to), INDEX idx_complaints_status_assigned (status, assigned_to), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE repair_costs (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, description LONGTEXT DEFAULT NULL, cost_date DATE NOT NULL, complaint_id INT NOT NULL, recorded_by INT NOT NULL, INDEX IDX_FC29304EDAE188E (complaint_id), INDEX IDX_FC2930482D4278B (recorded_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE reports (id INT AUTO_INCREMENT NOT NULL, report_type VARCHAR(255) NOT NULL, generated_date DATE NOT NULL, file_url VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, generated_by INT NOT NULL, INDEX IDX_F11FA74570252688 (generated_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE room_assignments (id INT AUTO_INCREMENT NOT NULL, assigned_date DATE NOT NULL, vacated_date DATE DEFAULT NULL, status VARCHAR(255) NOT NULL, student_id INT NOT NULL, room_id INT NOT NULL, INDEX IDX_24BFFEE2CB944F1A (student_id), INDEX IDX_24BFFEE254177093 (room_id), INDEX idx_room_assignments_student_status (student_id, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE room_change_requests (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, reviewed_at DATETIME DEFAULT NULL, student_id INT NOT NULL, current_room_id INT NOT NULL, requested_room_id INT NOT NULL, reviewed_by INT DEFAULT NULL, INDEX IDX_173A44E9CB944F1A (student_id), INDEX IDX_173A44E9FE1AF516 (current_room_id), INDEX IDX_173A44E996DB69D7 (requested_room_id), INDEX IDX_173A44E985D7FB47 (reviewed_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE rooms (id INT AUTO_INCREMENT NOT NULL, room_number VARCHAR(20) NOT NULL, block VARCHAR(50) NOT NULL, floor INT NOT NULL, capacity INT NOT NULL, current_occupancy INT DEFAULT 0 NOT NULL, room_type VARCHAR(50) DEFAULT NULL, status VARCHAR(255) NOT NULL, INDEX idx_rooms_block_status (block, status), UNIQUE INDEX uniq_rooms_number (room_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE students (id INT AUTO_INCREMENT NOT NULL, student_number VARCHAR(50) NOT NULL, phone VARCHAR(20) DEFAULT NULL, emergency_contact VARCHAR(150) DEFAULT NULL, admission_status VARCHAR(255) NOT NULL, admission_date DATE DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_students_user (user_id), UNIQUE INDEX uniq_students_number (student_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE supervisor_tasks (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, due_date DATE DEFAULT NULL, supervisor_id INT NOT NULL, assigned_by INT NOT NULL, INDEX IDX_F5D9FE5D19E9AC5F (supervisor_id), INDEX IDX_F5D9FE5D61A2AF17 (assigned_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE supervisors (id INT AUTO_INCREMENT NOT NULL, block_assigned VARCHAR(50) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_supervisors_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_users_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE admission_requests ADD CONSTRAINT FK_F0AD5D80CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE admission_requests ADD CONSTRAINT FK_F0AD5D8085D7FB47 FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE announcements ADD CONSTRAINT FK_F422A9D19E9AC5F FOREIGN KEY (supervisor_id) REFERENCES supervisors (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT FK_EF20C9A6F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_messages ADD CONSTRAINT FK_EF20C9A6CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE complaint_updates ADD CONSTRAINT FK_3000F361EDAE188E FOREIGN KEY (complaint_id) REFERENCES complaints (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE complaint_updates ADD CONSTRAINT FK_3000F36116FE72E1 FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE complaints ADD CONSTRAINT FK_A05AAF3ACB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE complaints ADD CONSTRAINT FK_A05AAF3A54177093 FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE complaints ADD CONSTRAINT FK_A05AAF3A89EEAF91 FOREIGN KEY (assigned_to) REFERENCES supervisors (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE repair_costs ADD CONSTRAINT FK_FC29304EDAE188E FOREIGN KEY (complaint_id) REFERENCES complaints (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repair_costs ADD CONSTRAINT FK_FC2930482D4278B FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA74570252688 FOREIGN KEY (generated_by) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE room_assignments ADD CONSTRAINT FK_24BFFEE2CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE room_assignments ADD CONSTRAINT FK_24BFFEE254177093 FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE room_change_requests ADD CONSTRAINT FK_173A44E9CB944F1A FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE room_change_requests ADD CONSTRAINT FK_173A44E9FE1AF516 FOREIGN KEY (current_room_id) REFERENCES rooms (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE room_change_requests ADD CONSTRAINT FK_173A44E996DB69D7 FOREIGN KEY (requested_room_id) REFERENCES rooms (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE room_change_requests ADD CONSTRAINT FK_173A44E985D7FB47 FOREIGN KEY (reviewed_by) REFERENCES supervisors (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE students ADD CONSTRAINT FK_A4698DB2A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supervisor_tasks ADD CONSTRAINT FK_F5D9FE5D19E9AC5F FOREIGN KEY (supervisor_id) REFERENCES supervisors (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supervisor_tasks ADD CONSTRAINT FK_F5D9FE5D61A2AF17 FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE supervisors ADD CONSTRAINT FK_A82524B7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admission_requests DROP FOREIGN KEY FK_F0AD5D80CB944F1A');
        $this->addSql('ALTER TABLE admission_requests DROP FOREIGN KEY FK_F0AD5D8085D7FB47');
        $this->addSql('ALTER TABLE announcements DROP FOREIGN KEY FK_F422A9D19E9AC5F');
        $this->addSql('ALTER TABLE chat_messages DROP FOREIGN KEY FK_EF20C9A6F624B39D');
        $this->addSql('ALTER TABLE chat_messages DROP FOREIGN KEY FK_EF20C9A6CD53EDB6');
        $this->addSql('ALTER TABLE complaint_updates DROP FOREIGN KEY FK_3000F361EDAE188E');
        $this->addSql('ALTER TABLE complaint_updates DROP FOREIGN KEY FK_3000F36116FE72E1');
        $this->addSql('ALTER TABLE complaints DROP FOREIGN KEY FK_A05AAF3ACB944F1A');
        $this->addSql('ALTER TABLE complaints DROP FOREIGN KEY FK_A05AAF3A54177093');
        $this->addSql('ALTER TABLE complaints DROP FOREIGN KEY FK_A05AAF3A89EEAF91');
        $this->addSql('ALTER TABLE repair_costs DROP FOREIGN KEY FK_FC29304EDAE188E');
        $this->addSql('ALTER TABLE repair_costs DROP FOREIGN KEY FK_FC2930482D4278B');
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA74570252688');
        $this->addSql('ALTER TABLE room_assignments DROP FOREIGN KEY FK_24BFFEE2CB944F1A');
        $this->addSql('ALTER TABLE room_assignments DROP FOREIGN KEY FK_24BFFEE254177093');
        $this->addSql('ALTER TABLE room_change_requests DROP FOREIGN KEY FK_173A44E9CB944F1A');
        $this->addSql('ALTER TABLE room_change_requests DROP FOREIGN KEY FK_173A44E9FE1AF516');
        $this->addSql('ALTER TABLE room_change_requests DROP FOREIGN KEY FK_173A44E996DB69D7');
        $this->addSql('ALTER TABLE room_change_requests DROP FOREIGN KEY FK_173A44E985D7FB47');
        $this->addSql('ALTER TABLE students DROP FOREIGN KEY FK_A4698DB2A76ED395');
        $this->addSql('ALTER TABLE supervisor_tasks DROP FOREIGN KEY FK_F5D9FE5D19E9AC5F');
        $this->addSql('ALTER TABLE supervisor_tasks DROP FOREIGN KEY FK_F5D9FE5D61A2AF17');
        $this->addSql('ALTER TABLE supervisors DROP FOREIGN KEY FK_A82524B7A76ED395');
        $this->addSql('DROP TABLE admission_requests');
        $this->addSql('DROP TABLE announcements');
        $this->addSql('DROP TABLE chat_messages');
        $this->addSql('DROP TABLE complaint_updates');
        $this->addSql('DROP TABLE complaints');
        $this->addSql('DROP TABLE repair_costs');
        $this->addSql('DROP TABLE reports');
        $this->addSql('DROP TABLE room_assignments');
        $this->addSql('DROP TABLE room_change_requests');
        $this->addSql('DROP TABLE rooms');
        $this->addSql('DROP TABLE students');
        $this->addSql('DROP TABLE supervisor_tasks');
        $this->addSql('DROP TABLE supervisors');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
