<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011184550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Towary, przypisanie pozycji faktur, linki towarów, cykle i ustawienia zamówień';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE item_mapping (id INT AUTO_INCREMENT NOT NULL, skip TINYINT NOT NULL, factor NUMERIC(15, 6) DEFAULT NULL, uses INT NOT NULL, updated_at DATETIME NOT NULL, name_key VARCHAR(255) NOT NULL, product_id INT DEFAULT NULL, cost_center_id INT DEFAULT NULL, contractor_id INT DEFAULT NULL, farm_id INT NOT NULL, UNIQUE INDEX UNIQ_42BB0ADE65FCFA0DB0265DC78E85A347 (farm_id, contractor_id, name_key), INDEX IDX_42BB0ADE4584665A (product_id), INDEX IDX_42BB0ADEE28F9437 (cost_center_id), INDEX IDX_42BB0ADEB0265DC7 (contractor_id), INDEX IDX_42BB0ADE65FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_cycle (id INT AUTO_INCREMENT NOT NULL, last_order_date DATE DEFAULT NULL, last_order_quantity NUMERIC(15, 3) DEFAULT NULL, last_unit_price NUMERIC(15, 4) DEFAULT NULL, median_unit_price NUMERIC(15, 4) DEFAULT NULL, predicted_date DATE DEFAULT NULL, window_from DATE DEFAULT NULL, window_to DATE DEFAULT NULL, reminder_date DATE DEFAULT NULL, typical_quantity NUMERIC(15, 3) DEFAULT NULL, typical_amount NUMERIC(14, 2) DEFAULT NULL, daily_usage NUMERIC(15, 4) DEFAULT NULL, mode VARCHAR(12) DEFAULT NULL, regularity VARCHAR(12) DEFAULT NULL, interval_days SMALLINT DEFAULT NULL, spread_days SMALLINT DEFAULT NULL, order_count SMALLINT NOT NULL, status VARCHAR(14) NOT NULL, marked_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, closed_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, marked_by_id INT DEFAULT NULL, product_id INT DEFAULT NULL, contractor_id INT DEFAULT NULL, farm_id INT NOT NULL, INDEX IDX_3318A56E65FCFA0D5D13417F7B00651C (farm_id, closed_at, status), INDEX IDX_3318A56E65FCFA0DAAF46E2C (farm_id, predicted_date), INDEX IDX_3318A56E61703BF (marked_by_id), INDEX IDX_3318A56E4584665A (product_id), INDEX IDX_3318A56EB0265DC7 (contractor_id), INDEX IDX_3318A56E65FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_settings (id INT AUTO_INCREMENT NOT NULL, manual_interval_days SMALLINT DEFAULT NULL, manual_quantity NUMERIC(15, 3) DEFAULT NULL, lead_days SMALLINT DEFAULT NULL, seasonal_disabled TINYINT NOT NULL, hidden TINYINT NOT NULL, product_id INT DEFAULT NULL, contractor_id INT DEFAULT NULL, farm_id INT NOT NULL, UNIQUE INDEX UNIQ_4BD427465FCFA0D4584665A (farm_id, product_id), UNIQUE INDEX UNIQ_4BD427465FCFA0DB0265DC7 (farm_id, contractor_id), INDEX IDX_4BD42744584665A (product_id), INDEX IDX_4BD4274B0265DC7 (contractor_id), INDEX IDX_4BD427465FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, internal_code VARCHAR(40) DEFAULT NULL, catalog_number VARCHAR(80) DEFAULT NULL, lead_time_days SMALLINT DEFAULT NULL, book_price NUMERIC(15, 4) DEFAULT NULL, active TINYINT NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, category_id INT DEFAULT NULL, base_unit_id INT DEFAULT NULL, farm_id INT NOT NULL, INDEX IDX_D34A04AD65FCFA0D4B1EFC025E237E06 (farm_id, active, name), INDEX IDX_D34A04AD12469DE2 (category_id), INDEX IDX_D34A04ADCCBBC969 (base_unit_id), INDEX IDX_D34A04AD65FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, position INT NOT NULL, parent_id INT DEFAULT NULL, farm_id INT NOT NULL, INDEX IDX_CDFC7356727ACA70 (parent_id), INDEX IDX_CDFC735665FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_link (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(1024) NOT NULL, name VARCHAR(160) NOT NULL, description VARCHAR(255) DEFAULT NULL, added_at DATETIME NOT NULL, added_by_id INT DEFAULT NULL, product_id INT NOT NULL, category_id INT NOT NULL, farm_id INT NOT NULL, INDEX IDX_BB44BB5055B127A4 (added_by_id), INDEX IDX_BB44BB504584665A (product_id), INDEX IDX_BB44BB5012469DE2 (category_id), INDEX IDX_BB44BB5065FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_link_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, position INT NOT NULL, parent_id INT DEFAULT NULL, farm_id INT NOT NULL, INDEX IDX_110B65FA727ACA70 (parent_id), INDEX IDX_110B65FA65FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_unit_conversion (id INT AUTO_INCREMENT NOT NULL, factor NUMERIC(15, 6) NOT NULL, product_id INT NOT NULL, unit_id INT NOT NULL, UNIQUE INDEX UNIQ_8CB2B4194584665AF8BD700D (product_id, unit_id), INDEX IDX_8CB2B4194584665A (product_id), INDEX IDX_8CB2B419F8BD700D (unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE item_mapping ADD CONSTRAINT FK_42BB0ADE4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_mapping ADD CONSTRAINT FK_42BB0ADEE28F9437 FOREIGN KEY (cost_center_id) REFERENCES cost_center (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE item_mapping ADD CONSTRAINT FK_42BB0ADEB0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_mapping ADD CONSTRAINT FK_42BB0ADE65FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_cycle ADD CONSTRAINT FK_3318A56E61703BF FOREIGN KEY (marked_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_cycle ADD CONSTRAINT FK_3318A56E4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_cycle ADD CONSTRAINT FK_3318A56EB0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_cycle ADD CONSTRAINT FK_3318A56E65FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_settings ADD CONSTRAINT FK_4BD42744584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_settings ADD CONSTRAINT FK_4BD4274B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_settings ADD CONSTRAINT FK_4BD427465FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES product_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADCCBBC969 FOREIGN KEY (base_unit_id) REFERENCES unit (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD65FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC7356727ACA70 FOREIGN KEY (parent_id) REFERENCES product_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_category ADD CONSTRAINT FK_CDFC735665FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_link ADD CONSTRAINT FK_BB44BB5055B127A4 FOREIGN KEY (added_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_link ADD CONSTRAINT FK_BB44BB504584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_link ADD CONSTRAINT FK_BB44BB5012469DE2 FOREIGN KEY (category_id) REFERENCES product_link_category (id)');
        $this->addSql('ALTER TABLE product_link ADD CONSTRAINT FK_BB44BB5065FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_link_category ADD CONSTRAINT FK_110B65FA727ACA70 FOREIGN KEY (parent_id) REFERENCES product_link_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_link_category ADD CONSTRAINT FK_110B65FA65FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_unit_conversion ADD CONSTRAINT FK_8CB2B4194584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_unit_conversion ADD CONSTRAINT FK_8CB2B419F8BD700D FOREIGN KEY (unit_id) REFERENCES unit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contract ADD product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28594584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E98F28594584665A ON contract (product_id)');
        $this->addSql('ALTER TABLE invoice_item ADD quantity_in_product_unit NUMERIC(15, 4) DEFAULT NULL, ADD product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_item ADD CONSTRAINT FK_1DDE477B4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1DDE477B4584665A ON invoice_item (product_id)');
        $this->addSql('ALTER TABLE machine_info_entry ADD product_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE machine_info_entry ADD CONSTRAINT FK_55DD40244584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_55DD40244584665A ON machine_info_entry (product_id)');

        // Słowniki startowe dla gospodarstw, które już istnieją (nowe dostają je przy zakładaniu).
        foreach (['Zboża', 'Pasze objętościowe', 'Pasze treściwe i mieszanki', 'Dodatki mineralne', 'ŚOR', 'Nasiona', 'Nawozy', 'Paliwa', 'Oleje i smary', 'Filtry i części', 'Leki weterynaryjne', 'Inne'] as $position => $name) {
            $this->addSql('INSERT INTO product_category (farm_id, name, position) SELECT id, ?, ? FROM farm', [$name, $position]);
        }
        foreach (['Sklepy', 'Producent', 'Karty charakterystyki i etykiety', 'Instrukcje', 'Zamienniki', 'Inne'] as $position => $name) {
            $this->addSql('INSERT INTO product_link_category (farm_id, name, position) SELECT id, ?, ? FROM farm', [$name, $position]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_mapping DROP FOREIGN KEY FK_42BB0ADE4584665A');
        $this->addSql('ALTER TABLE item_mapping DROP FOREIGN KEY FK_42BB0ADEE28F9437');
        $this->addSql('ALTER TABLE item_mapping DROP FOREIGN KEY FK_42BB0ADEB0265DC7');
        $this->addSql('ALTER TABLE item_mapping DROP FOREIGN KEY FK_42BB0ADE65FCFA0D');
        $this->addSql('ALTER TABLE order_cycle DROP FOREIGN KEY FK_3318A56E61703BF');
        $this->addSql('ALTER TABLE order_cycle DROP FOREIGN KEY FK_3318A56E4584665A');
        $this->addSql('ALTER TABLE order_cycle DROP FOREIGN KEY FK_3318A56EB0265DC7');
        $this->addSql('ALTER TABLE order_cycle DROP FOREIGN KEY FK_3318A56E65FCFA0D');
        $this->addSql('ALTER TABLE order_settings DROP FOREIGN KEY FK_4BD42744584665A');
        $this->addSql('ALTER TABLE order_settings DROP FOREIGN KEY FK_4BD4274B0265DC7');
        $this->addSql('ALTER TABLE order_settings DROP FOREIGN KEY FK_4BD427465FCFA0D');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADCCBBC969');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD65FCFA0D');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC7356727ACA70');
        $this->addSql('ALTER TABLE product_category DROP FOREIGN KEY FK_CDFC735665FCFA0D');
        $this->addSql('ALTER TABLE product_link DROP FOREIGN KEY FK_BB44BB5055B127A4');
        $this->addSql('ALTER TABLE product_link DROP FOREIGN KEY FK_BB44BB504584665A');
        $this->addSql('ALTER TABLE product_link DROP FOREIGN KEY FK_BB44BB5012469DE2');
        $this->addSql('ALTER TABLE product_link DROP FOREIGN KEY FK_BB44BB5065FCFA0D');
        $this->addSql('ALTER TABLE product_link_category DROP FOREIGN KEY FK_110B65FA727ACA70');
        $this->addSql('ALTER TABLE product_link_category DROP FOREIGN KEY FK_110B65FA65FCFA0D');
        $this->addSql('ALTER TABLE product_unit_conversion DROP FOREIGN KEY FK_8CB2B4194584665A');
        $this->addSql('ALTER TABLE product_unit_conversion DROP FOREIGN KEY FK_8CB2B419F8BD700D');
        $this->addSql('DROP TABLE item_mapping');
        $this->addSql('DROP TABLE order_cycle');
        $this->addSql('DROP TABLE order_settings');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE product_link');
        $this->addSql('DROP TABLE product_link_category');
        $this->addSql('DROP TABLE product_unit_conversion');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28594584665A');
        $this->addSql('DROP INDEX IDX_E98F28594584665A ON contract');
        $this->addSql('ALTER TABLE contract DROP product_id');
        $this->addSql('ALTER TABLE invoice_item DROP FOREIGN KEY FK_1DDE477B4584665A');
        $this->addSql('DROP INDEX IDX_1DDE477B4584665A ON invoice_item');
        $this->addSql('ALTER TABLE invoice_item DROP quantity_in_product_unit, DROP product_id');
        $this->addSql('ALTER TABLE machine_info_entry DROP FOREIGN KEY FK_55DD40244584665A');
        $this->addSql('DROP INDEX IDX_55DD40244584665A ON machine_info_entry');
        $this->addSql('ALTER TABLE machine_info_entry DROP product_id');
    }
}
