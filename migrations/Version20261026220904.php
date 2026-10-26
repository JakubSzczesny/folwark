<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026220904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Osoby u kontrahentów (imię i nazwisko, stanowisko, telefon, e-mail)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_person (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, position VARCHAR(120) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, contractor_id INT NOT NULL, farm_id INT NOT NULL, INDEX IDX_A44EE6F7B0265DC7 (contractor_id), INDEX IDX_A44EE6F765FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contact_person ADD CONSTRAINT FK_A44EE6F7B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_person ADD CONSTRAINT FK_A44EE6F765FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_person DROP FOREIGN KEY FK_A44EE6F7B0265DC7');
        $this->addSql('ALTER TABLE contact_person DROP FOREIGN KEY FK_A44EE6F765FCFA0D');
        $this->addSql('DROP TABLE contact_person');
    }
}
