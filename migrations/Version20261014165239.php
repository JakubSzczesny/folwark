<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014165239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Magazyny, pola i sezony upraw, produkcja mleka';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE crop_season (id INT AUTO_INCREMENT NOT NULL, year SMALLINT NOT NULL, crop VARCHAR(80) NOT NULL, area_ha NUMERIC(10, 4) DEFAULT NULL, field_id INT NOT NULL, cost_center_id INT DEFAULT NULL, farm_id INT NOT NULL, UNIQUE INDEX UNIQ_EB3CA8C1E28F9437 (cost_center_id), INDEX IDX_EB3CA8C165FCFA0DBB827337 (farm_id, year), INDEX IDX_EB3CA8C1443707B0 (field_id), INDEX IDX_EB3CA8C165FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE field (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, area_ha NUMERIC(10, 4) NOT NULL, parcel_numbers VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, active TINYINT NOT NULL, farm_id INT NOT NULL, INDEX IDX_5BF5455865FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE milk_production (id INT AUTO_INCREMENT NOT NULL, liters NUMERIC(12, 1) NOT NULL, month DATE NOT NULL, cost_center_id INT NOT NULL, farm_id INT NOT NULL, UNIQUE INDEX UNIQ_5DC01FB665FCFA0DE28F94378EB61006 (farm_id, cost_center_id, month), INDEX IDX_5DC01FB6E28F9437 (cost_center_id), INDEX IDX_5DC01FB665FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE warehouse (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, type VARCHAR(20) NOT NULL, capacity NUMERIC(14, 3) DEFAULT NULL, active TINYINT NOT NULL, capacity_unit_id INT DEFAULT NULL, farm_id INT NOT NULL, UNIQUE INDEX UNIQ_ECB38BFC65FCFA0D5E237E06 (farm_id, name), INDEX IDX_ECB38BFCDD5485 (capacity_unit_id), INDEX IDX_ECB38BFC65FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE crop_season ADD CONSTRAINT FK_EB3CA8C1443707B0 FOREIGN KEY (field_id) REFERENCES field (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE crop_season ADD CONSTRAINT FK_EB3CA8C1E28F9437 FOREIGN KEY (cost_center_id) REFERENCES cost_center (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE crop_season ADD CONSTRAINT FK_EB3CA8C165FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE field ADD CONSTRAINT FK_5BF5455865FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE milk_production ADD CONSTRAINT FK_5DC01FB6E28F9437 FOREIGN KEY (cost_center_id) REFERENCES cost_center (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE milk_production ADD CONSTRAINT FK_5DC01FB665FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE warehouse ADD CONSTRAINT FK_ECB38BFCDD5485 FOREIGN KEY (capacity_unit_id) REFERENCES unit (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE warehouse ADD CONSTRAINT FK_ECB38BFC65FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crop_season DROP FOREIGN KEY FK_EB3CA8C1443707B0');
        $this->addSql('ALTER TABLE crop_season DROP FOREIGN KEY FK_EB3CA8C1E28F9437');
        $this->addSql('ALTER TABLE crop_season DROP FOREIGN KEY FK_EB3CA8C165FCFA0D');
        $this->addSql('ALTER TABLE field DROP FOREIGN KEY FK_5BF5455865FCFA0D');
        $this->addSql('ALTER TABLE milk_production DROP FOREIGN KEY FK_5DC01FB6E28F9437');
        $this->addSql('ALTER TABLE milk_production DROP FOREIGN KEY FK_5DC01FB665FCFA0D');
        $this->addSql('ALTER TABLE warehouse DROP FOREIGN KEY FK_ECB38BFCDD5485');
        $this->addSql('ALTER TABLE warehouse DROP FOREIGN KEY FK_ECB38BFC65FCFA0D');
        $this->addSql('DROP TABLE crop_season');
        $this->addSql('DROP TABLE field');
        $this->addSql('DROP TABLE milk_production');
        $this->addSql('DROP TABLE warehouse');
    }
}
