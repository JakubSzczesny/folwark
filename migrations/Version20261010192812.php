<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010192812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Etap 1: użytkownicy, ustawienia, audyt, powiadomienia, lista wagi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(120) NOT NULL, role VARCHAR(20) NOT NULL, password VARCHAR(255) NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, entity VARCHAR(60) NOT NULL, entity_id VARCHAR(40) NOT NULL, action VARCHAR(60) NOT NULL, changes JSON NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_F6E1C0F5E28446881257D5D (entity, entity_id), INDEX IDX_F6E1C0F58B8E8428 (created_at), INDEX IDX_F6E1C0F5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, emailed_at DATETIME DEFAULT NULL, type VARCHAR(30) NOT NULL, priority VARCHAR(10) NOT NULL, title VARCHAR(200) NOT NULL, body LONGTEXT DEFAULT NULL, link VARCHAR(255) DEFAULT NULL, dedup_key VARCHAR(150) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395E2DA3872 (user_id, read_at), UNIQUE INDEX UNIQ_BF5476CAA76ED39544F613DD (user_id, dedup_key), INDEX IDX_BF5476CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification_preference (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, enabled TINYINT NOT NULL, email TINYINT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_A61B1571A76ED3958CDE5729 (user_id, type), INDEX IDX_A61B1571A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recent_choice (id INT AUTO_INCREMENT NOT NULL, used_at DATETIME NOT NULL, kind VARCHAR(40) NOT NULL, entity_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_829A0C6BA76ED3953BC4BCD981257D5D (user_id, kind, entity_id), INDEX IDX_829A0C6BA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE saved_filter (id INT AUTO_INCREMENT NOT NULL, query LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, list_key VARCHAR(60) NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_1BC22406A76ED3959B133784 (user_id, list_key), INDEX IDX_1BC22406A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE setting (value JSON DEFAULT NULL, updated_at DATETIME NOT NULL, name VARCHAR(80) NOT NULL, PRIMARY KEY (name)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE trusted_device (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, failed_attempts INT NOT NULL, name VARCHAR(100) NOT NULL, token_hash VARCHAR(64) NOT NULL, pin_hash VARCHAR(255) NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_F37E8F7BB3BC57DA (token_hash), INDEX IDX_F37E8F7BA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE weighbridge_task (id INT AUTO_INCREMENT NOT NULL, group_name VARCHAR(100) NOT NULL, content LONGTEXT NOT NULL, position INT NOT NULL, done TINYINT NOT NULL, note LONGTEXT DEFAULT NULL, done_at DATETIME DEFAULT NULL, done_by_id INT DEFAULT NULL, INDEX IDX_41FC1E8B35AE3EF9 (done_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_preference ADD CONSTRAINT FK_A61B1571A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recent_choice ADD CONSTRAINT FK_829A0C6BA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE saved_filter ADD CONSTRAINT FK_1BC22406A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trusted_device ADD CONSTRAINT FK_F37E8F7BA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE weighbridge_task ADD CONSTRAINT FK_41FC1E8B35AE3EF9 FOREIGN KEY (done_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_log DROP FOREIGN KEY FK_F6E1C0F5A76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification_preference DROP FOREIGN KEY FK_A61B1571A76ED395');
        $this->addSql('ALTER TABLE recent_choice DROP FOREIGN KEY FK_829A0C6BA76ED395');
        $this->addSql('ALTER TABLE saved_filter DROP FOREIGN KEY FK_1BC22406A76ED395');
        $this->addSql('ALTER TABLE trusted_device DROP FOREIGN KEY FK_F37E8F7BA76ED395');
        $this->addSql('ALTER TABLE weighbridge_task DROP FOREIGN KEY FK_41FC1E8B35AE3EF9');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE notification_preference');
        $this->addSql('DROP TABLE recent_choice');
        $this->addSql('DROP TABLE saved_filter');
        $this->addSql('DROP TABLE setting');
        $this->addSql('DROP TABLE trusted_device');
        $this->addSql('DROP TABLE weighbridge_task');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
