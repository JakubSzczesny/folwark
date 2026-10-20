<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020172721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Kalendarz: wydarzenia i ich uczestnicy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE calendar_event (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, category VARCHAR(12) NOT NULL, start_date DATE NOT NULL, start_time TIME DEFAULT NULL, end_date DATE DEFAULT NULL, end_time TIME DEFAULT NULL, location VARCHAR(160) DEFAULT NULL, description LONGTEXT DEFAULT NULL, recurrence VARCHAR(10) NOT NULL, recurrence_until DATE DEFAULT NULL, reminder_days SMALLINT DEFAULT NULL, private TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, farm_id INT NOT NULL, INDEX IDX_57FA09C965FCFA0D95275AB8 (farm_id, start_date), INDEX IDX_57FA09C965FCFA0D1FB7F221 (farm_id, recurrence), INDEX IDX_57FA09C9B03A8386 (created_by_id), INDEX IDX_57FA09C965FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE calendar_event_participant (calendar_event_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_2E3F35047495C8E3 (calendar_event_id), INDEX IDX_2E3F3504A76ED395 (user_id), PRIMARY KEY (calendar_event_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C9B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C965FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_event_participant ADD CONSTRAINT FK_2E3F35047495C8E3 FOREIGN KEY (calendar_event_id) REFERENCES calendar_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_event_participant ADD CONSTRAINT FK_2E3F3504A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C9B03A8386');
        $this->addSql('ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C965FCFA0D');
        $this->addSql('ALTER TABLE calendar_event_participant DROP FOREIGN KEY FK_2E3F35047495C8E3');
        $this->addSql('ALTER TABLE calendar_event_participant DROP FOREIGN KEY FK_2E3F3504A76ED395');
        $this->addSql('DROP TABLE calendar_event');
        $this->addSql('DROP TABLE calendar_event_participant');
    }
}
