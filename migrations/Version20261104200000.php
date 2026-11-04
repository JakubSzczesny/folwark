<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Faktury: kwota do zapłaty z sekcji Rozliczenie (obciążenia i odliczenia)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice ADD amount_due NUMERIC(14, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP amount_due');
    }
}
