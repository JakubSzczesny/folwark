<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Faktury: forma płatności z XML; kontrahenci: faktury przychodzą opłacone';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice ADD payment_form VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE contractor ADD auto_paid TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP payment_form');
        $this->addSql('ALTER TABLE contractor DROP auto_paid');
    }
}
