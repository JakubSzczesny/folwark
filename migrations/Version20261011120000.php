<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Maszyny: kategorie, karta, historia tary, informacje z załącznikami, serwis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE machine (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, registration_number VARCHAR(20) DEFAULT NULL, brand VARCHAR(60) DEFAULT NULL, model VARCHAR(80) DEFAULT NULL, production_year SMALLINT DEFAULT NULL, serial_number VARCHAR(40) DEFAULT NULL, owned TINYINT NOT NULL, tare_kg INT DEFAULT NULL, tare_set_at DATETIME DEFAULT NULL, tare_source VARCHAR(10) DEFAULT NULL, payload_kg INT DEFAULT NULL, insurance_until DATE DEFAULT NULL, inspection_until DATE DEFAULT NULL, meter_value NUMERIC(10, 1) DEFAULT NULL, meter_unit VARCHAR(3) DEFAULT NULL, meter_updated_at DATETIME DEFAULT NULL, photo_path VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, category_id INT DEFAULT NULL, contractor_id INT DEFAULT NULL, cost_center_id INT DEFAULT NULL, farm_id INT NOT NULL, UNIQUE INDEX UNIQ_1505DF84E28F9437 (cost_center_id), INDEX IDX_1505DF8465FCFA0D4B1EFC025E237E06 (farm_id, active, name), INDEX IDX_1505DF8465FCFA0D20FE1F80 (farm_id, insurance_until), INDEX IDX_1505DF8465FCFA0D9B016BAF (farm_id, inspection_until), INDEX IDX_1505DF8465FCFA0D38CEDFBE (farm_id, registration_number), INDEX IDX_1505DF8412469DE2 (category_id), INDEX IDX_1505DF84B0265DC7 (contractor_id), INDEX IDX_1505DF8465FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine_set_component (set_id INT NOT NULL, machine_id INT NOT NULL, INDEX IDX_EDB5FC8010FB0D18 (set_id), INDEX IDX_EDB5FC80F6B75B26 (machine_id), PRIMARY KEY (set_id, machine_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, position INT NOT NULL, farm_id INT NOT NULL, UNIQUE INDEX UNIQ_CD2BD5DD65FCFA0D5E237E06 (farm_id, name), INDEX IDX_CD2BD5DD65FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine_info_attachment (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, location VARCHAR(1024) NOT NULL, description VARCHAR(255) DEFAULT NULL, entry_id INT NOT NULL, farm_id INT NOT NULL, INDEX IDX_6D900F38BA364942 (entry_id), INDEX IDX_6D900F3865FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine_info_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, position INT NOT NULL, parent_id INT DEFAULT NULL, farm_id INT NOT NULL, INDEX IDX_ED649AB6727ACA70 (parent_id), INDEX IDX_ED649AB665FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine_info_entry (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, catalog_number VARCHAR(80) DEFAULT NULL, manufacturer VARCHAR(80) DEFAULT NULL, size_quantity VARCHAR(80) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, machine_id INT NOT NULL, category_id INT NOT NULL, farm_id INT NOT NULL, INDEX IDX_55DD4024F6B75B26 (machine_id), INDEX IDX_55DD402412469DE2 (category_id), INDEX IDX_55DD402465FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine_service (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, meter_value NUMERIC(10, 1) DEFAULT NULL, description LONGTEXT DEFAULT NULL, performer_name VARCHAR(120) DEFAULT NULL, net_cost NUMERIC(14, 2) DEFAULT NULL, next_service_date DATE DEFAULT NULL, next_service_meter NUMERIC(10, 1) DEFAULT NULL, created_at DATETIME NOT NULL, type_id INT DEFAULT NULL, contractor_id INT DEFAULT NULL, invoice_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, machine_id INT NOT NULL, farm_id INT NOT NULL, INDEX IDX_E7E20A6265FCFA0DAA9E377A (farm_id, date), INDEX IDX_E7E20A6265FCFA0DB3D2837C (farm_id, next_service_date), INDEX IDX_E7E20A62C54C8C93 (type_id), INDEX IDX_E7E20A62B0265DC7 (contractor_id), INDEX IDX_E7E20A622989F1FD (invoice_id), INDEX IDX_E7E20A62B03A8386 (created_by_id), INDEX IDX_E7E20A62F6B75B26 (machine_id), INDEX IDX_E7E20A6265FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine_service_attachment (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, photo TINYINT NOT NULL, service_id INT NOT NULL, farm_id INT NOT NULL, INDEX IDX_985B13E6ED5CA9E6 (service_id), INDEX IDX_985B13E665FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine_service_part (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, quantity NUMERIC(12, 3) DEFAULT NULL, service_id INT NOT NULL, unit_id INT DEFAULT NULL, INDEX IDX_233CA571ED5CA9E6 (service_id), INDEX IDX_233CA571F8BD700D (unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine_tare (id INT AUTO_INCREMENT NOT NULL, tare_kg INT NOT NULL, source VARCHAR(10) NOT NULL, measured_at DATETIME NOT NULL, machine_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_FAD72C1AF6B75B26 (machine_id), INDEX IDX_FAD72C1AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service_type (id INT AUTO_INCREMENT NOT NULL, active TINYINT NOT NULL, name VARCHAR(80) NOT NULL, position INT NOT NULL, farm_id INT NOT NULL, UNIQUE INDEX UNIQ_429DE3C565FCFA0D5E237E06 (farm_id, name), INDEX IDX_429DE3C565FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF8412469DE2 FOREIGN KEY (category_id) REFERENCES machine_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF84B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF84E28F9437 FOREIGN KEY (cost_center_id) REFERENCES cost_center (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF8465FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_set_component ADD CONSTRAINT FK_EDB5FC8010FB0D18 FOREIGN KEY (set_id) REFERENCES machine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_set_component ADD CONSTRAINT FK_EDB5FC80F6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_category ADD CONSTRAINT FK_CD2BD5DD65FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_info_attachment ADD CONSTRAINT FK_6D900F38BA364942 FOREIGN KEY (entry_id) REFERENCES machine_info_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_info_attachment ADD CONSTRAINT FK_6D900F3865FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_info_category ADD CONSTRAINT FK_ED649AB6727ACA70 FOREIGN KEY (parent_id) REFERENCES machine_info_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_info_category ADD CONSTRAINT FK_ED649AB665FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_info_entry ADD CONSTRAINT FK_55DD4024F6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_info_entry ADD CONSTRAINT FK_55DD402412469DE2 FOREIGN KEY (category_id) REFERENCES machine_info_category (id)');
        $this->addSql('ALTER TABLE machine_info_entry ADD CONSTRAINT FK_55DD402465FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_service ADD CONSTRAINT FK_E7E20A62C54C8C93 FOREIGN KEY (type_id) REFERENCES service_type (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine_service ADD CONSTRAINT FK_E7E20A62B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine_service ADD CONSTRAINT FK_E7E20A622989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine_service ADD CONSTRAINT FK_E7E20A62B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine_service ADD CONSTRAINT FK_E7E20A62F6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_service ADD CONSTRAINT FK_E7E20A6265FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_service_attachment ADD CONSTRAINT FK_985B13E6ED5CA9E6 FOREIGN KEY (service_id) REFERENCES machine_service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_service_attachment ADD CONSTRAINT FK_985B13E665FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_service_part ADD CONSTRAINT FK_233CA571ED5CA9E6 FOREIGN KEY (service_id) REFERENCES machine_service (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_service_part ADD CONSTRAINT FK_233CA571F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE machine_tare ADD CONSTRAINT FK_FAD72C1AF6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE machine_tare ADD CONSTRAINT FK_FAD72C1AA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_type ADD CONSTRAINT FK_429DE3C565FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');

        // Słowniki startowe dla gospodarstw, które już istnieją (nowe dostają je przy zakładaniu).
        foreach (['Ciągniki', 'Przyczepy i naczepy', 'Zestawy', 'Samochody', 'Kombajny', 'Maszyny uprawowe', 'Inne'] as $position => $name) {
            $this->addSql('INSERT INTO machine_category (farm_id, name, position) SELECT id, ?, ? FROM farm', [$name, $position]);
        }
        foreach (['Przegląd okresowy', 'Naprawa', 'Wymiana oleju', 'Wymiana filtrów', 'Wymiana opon', 'Badanie techniczne', 'Inne'] as $position => $name) {
            $this->addSql('INSERT INTO service_type (farm_id, name, position, active) SELECT id, ?, ?, 1 FROM farm', [$name, $position]);
        }
        foreach (['Filtry', 'Oleje i płyny', 'Opony i koła', 'Części', 'Pasy i łańcuchy', 'Akumulatory', 'Dokumenty'] as $position => $name) {
            $this->addSql('INSERT INTO machine_info_category (farm_id, name, position) SELECT id, ?, ? FROM farm', [$name, $position]);
        }
        foreach (['Oleju silnika', 'Paliwa', 'Powietrza', 'Hydrauliki', 'Kabinowy'] as $position => $name) {
            $this->addSql("INSERT INTO machine_info_category (farm_id, name, position, parent_id) SELECT farm_id, ?, ?, id FROM machine_info_category WHERE name = 'Filtry' AND parent_id IS NULL", [$name, $position]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_1505DF8412469DE2');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_1505DF84B0265DC7');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_1505DF84E28F9437');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_1505DF8465FCFA0D');
        $this->addSql('ALTER TABLE machine_set_component DROP FOREIGN KEY FK_EDB5FC8010FB0D18');
        $this->addSql('ALTER TABLE machine_set_component DROP FOREIGN KEY FK_EDB5FC80F6B75B26');
        $this->addSql('ALTER TABLE machine_category DROP FOREIGN KEY FK_CD2BD5DD65FCFA0D');
        $this->addSql('ALTER TABLE machine_info_attachment DROP FOREIGN KEY FK_6D900F38BA364942');
        $this->addSql('ALTER TABLE machine_info_attachment DROP FOREIGN KEY FK_6D900F3865FCFA0D');
        $this->addSql('ALTER TABLE machine_info_category DROP FOREIGN KEY FK_ED649AB6727ACA70');
        $this->addSql('ALTER TABLE machine_info_category DROP FOREIGN KEY FK_ED649AB665FCFA0D');
        $this->addSql('ALTER TABLE machine_info_entry DROP FOREIGN KEY FK_55DD4024F6B75B26');
        $this->addSql('ALTER TABLE machine_info_entry DROP FOREIGN KEY FK_55DD402412469DE2');
        $this->addSql('ALTER TABLE machine_info_entry DROP FOREIGN KEY FK_55DD402465FCFA0D');
        $this->addSql('ALTER TABLE machine_service DROP FOREIGN KEY FK_E7E20A62C54C8C93');
        $this->addSql('ALTER TABLE machine_service DROP FOREIGN KEY FK_E7E20A62B0265DC7');
        $this->addSql('ALTER TABLE machine_service DROP FOREIGN KEY FK_E7E20A622989F1FD');
        $this->addSql('ALTER TABLE machine_service DROP FOREIGN KEY FK_E7E20A62B03A8386');
        $this->addSql('ALTER TABLE machine_service DROP FOREIGN KEY FK_E7E20A62F6B75B26');
        $this->addSql('ALTER TABLE machine_service DROP FOREIGN KEY FK_E7E20A6265FCFA0D');
        $this->addSql('ALTER TABLE machine_service_attachment DROP FOREIGN KEY FK_985B13E6ED5CA9E6');
        $this->addSql('ALTER TABLE machine_service_attachment DROP FOREIGN KEY FK_985B13E665FCFA0D');
        $this->addSql('ALTER TABLE machine_service_part DROP FOREIGN KEY FK_233CA571ED5CA9E6');
        $this->addSql('ALTER TABLE machine_service_part DROP FOREIGN KEY FK_233CA571F8BD700D');
        $this->addSql('ALTER TABLE machine_tare DROP FOREIGN KEY FK_FAD72C1AF6B75B26');
        $this->addSql('ALTER TABLE machine_tare DROP FOREIGN KEY FK_FAD72C1AA76ED395');
        $this->addSql('ALTER TABLE service_type DROP FOREIGN KEY FK_429DE3C565FCFA0D');
        $this->addSql('DROP TABLE machine');
        $this->addSql('DROP TABLE machine_set_component');
        $this->addSql('DROP TABLE machine_category');
        $this->addSql('DROP TABLE machine_info_attachment');
        $this->addSql('DROP TABLE machine_info_category');
        $this->addSql('DROP TABLE machine_info_entry');
        $this->addSql('DROP TABLE machine_service');
        $this->addSql('DROP TABLE machine_service_attachment');
        $this->addSql('DROP TABLE machine_service_part');
        $this->addSql('DROP TABLE machine_tare');
        $this->addSql('DROP TABLE service_type');
    }
}
