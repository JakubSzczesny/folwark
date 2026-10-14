<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014174119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Własny układ pulpitu użytkownika w gospodarstwie';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE farm_membership ADD dashboard_widgets JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE farm_membership DROP dashboard_widgets');
    }
}
