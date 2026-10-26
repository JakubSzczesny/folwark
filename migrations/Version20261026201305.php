<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026201305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Indeks korespondencji z kontrahentami (nagłówki maili z Gmaila)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contractor_mail (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) NOT NULL, from_name VARCHAR(180) NOT NULL, from_address VARCHAR(180) NOT NULL, recipients VARCHAR(500) NOT NULL, sent_at DATETIME DEFAULT NULL, incoming TINYINT NOT NULL, has_attachments TINYINT NOT NULL, uid INT NOT NULL, contractor_id INT NOT NULL, farm_id INT NOT NULL, INDEX IDX_A92A6638B0265DC796E4F388 (contractor_id, sent_at), UNIQUE INDEX UNIQ_A92A663865FCFA0D539B0606B0265DC7 (farm_id, uid, contractor_id), INDEX IDX_A92A6638B0265DC7 (contractor_id), INDEX IDX_A92A663865FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contractor_mail ADD CONSTRAINT FK_A92A6638B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contractor_mail ADD CONSTRAINT FK_A92A663865FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contractor_mail DROP FOREIGN KEY FK_A92A6638B0265DC7');
        $this->addSql('ALTER TABLE contractor_mail DROP FOREIGN KEY FK_A92A663865FCFA0D');
        $this->addSql('DROP TABLE contractor_mail');
    }
}
