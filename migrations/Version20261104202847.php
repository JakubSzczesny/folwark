<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104202847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Domeny e-mail kontrahenta i Message-ID w indeksie maili';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contractor ADD mail_domains VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE contractor_mail ADD message_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contractor DROP mail_domains');
        $this->addSql('ALTER TABLE contractor_mail DROP message_id');
    }
}
