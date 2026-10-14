<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014170522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dokumenty magazynowe, ruchy i stany';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock (id INT AUTO_INCREMENT NOT NULL, quantity NUMERIC(15, 4) NOT NULL, value NUMERIC(14, 2) NOT NULL, last_movement_date DATE DEFAULT NULL, product_id INT NOT NULL, warehouse_id INT NOT NULL, farm_id INT NOT NULL, UNIQUE INDEX UNIQ_4B36566065FCFA0D4584665A5080ECDE (farm_id, product_id, warehouse_id), INDEX IDX_4B3656604584665A (product_id), INDEX IDX_4B3656605080ECDE (warehouse_id), INDEX IDX_4B36566065FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stock_document (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(3) NOT NULL, number VARCHAR(40) DEFAULT NULL, sequence SMALLINT DEFAULT NULL, date DATE NOT NULL, status VARCHAR(10) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, approved_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, cancel_reason VARCHAR(255) DEFAULT NULL, contractor_id INT DEFAULT NULL, contract_id INT DEFAULT NULL, source_warehouse_id INT DEFAULT NULL, target_warehouse_id INT DEFAULT NULL, cost_center_id INT DEFAULT NULL, invoice_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, approved_by_id INT DEFAULT NULL, replaces_id INT DEFAULT NULL, farm_id INT NOT NULL, INDEX IDX_97AFDE3465FCFA0DAA9E377A (farm_id, date), INDEX IDX_97AFDE3465FCFA0D8CDE5729AA9E377A (farm_id, type, date), UNIQUE INDEX UNIQ_97AFDE3465FCFA0D96901F54 (farm_id, number), INDEX IDX_97AFDE34B0265DC7 (contractor_id), INDEX IDX_97AFDE342576E0FD (contract_id), INDEX IDX_97AFDE34866A3066 (source_warehouse_id), INDEX IDX_97AFDE34B0D23F8F (target_warehouse_id), INDEX IDX_97AFDE34E28F9437 (cost_center_id), INDEX IDX_97AFDE342989F1FD (invoice_id), INDEX IDX_97AFDE34B03A8386 (created_by_id), INDEX IDX_97AFDE342D234F6A (approved_by_id), INDEX IDX_97AFDE349F193203 (replaces_id), INDEX IDX_97AFDE3465FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stock_document_item (id INT AUTO_INCREMENT NOT NULL, entered_quantity NUMERIC(15, 4) NOT NULL, quantity NUMERIC(15, 4) NOT NULL, unit_price_net NUMERIC(15, 4) DEFAULT NULL, net_value NUMERIC(14, 2) DEFAULT NULL, document_id INT NOT NULL, product_id INT NOT NULL, entered_unit_id INT DEFAULT NULL, INDEX IDX_3C3083694584665A (product_id), INDEX IDX_3C308369C33F7837 (document_id), INDEX IDX_3C3083695248959B (entered_unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stock_movement (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, date DATE NOT NULL, quantity NUMERIC(15, 4) NOT NULL, value NUMERIC(14, 2) NOT NULL, document_id INT NOT NULL, product_id INT NOT NULL, warehouse_id INT NOT NULL, farm_id INT NOT NULL, INDEX IDX_BB1BC1B565FCFA0D4584665AAA9E377A (farm_id, product_id, date), INDEX IDX_BB1BC1B5C33F7837 (document_id), INDEX IDX_BB1BC1B54584665A (product_id), INDEX IDX_BB1BC1B55080ECDE (warehouse_id), INDEX IDX_BB1BC1B565FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B3656604584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B3656605080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B36566065FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE34B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE342576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE34866A3066 FOREIGN KEY (source_warehouse_id) REFERENCES warehouse (id)');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE34B0D23F8F FOREIGN KEY (target_warehouse_id) REFERENCES warehouse (id)');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE34E28F9437 FOREIGN KEY (cost_center_id) REFERENCES cost_center (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE342989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE34B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE342D234F6A FOREIGN KEY (approved_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE349F193203 FOREIGN KEY (replaces_id) REFERENCES stock_document (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_document ADD CONSTRAINT FK_97AFDE3465FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_document_item ADD CONSTRAINT FK_3C308369C33F7837 FOREIGN KEY (document_id) REFERENCES stock_document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_document_item ADD CONSTRAINT FK_3C3083694584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE stock_document_item ADD CONSTRAINT FK_3C3083695248959B FOREIGN KEY (entered_unit_id) REFERENCES unit (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B5C33F7837 FOREIGN KEY (document_id) REFERENCES stock_document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B54584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B55080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B565FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B3656604584665A');
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B3656605080ECDE');
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B36566065FCFA0D');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE34B0265DC7');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE342576E0FD');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE34866A3066');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE34B0D23F8F');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE34E28F9437');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE342989F1FD');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE34B03A8386');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE342D234F6A');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE349F193203');
        $this->addSql('ALTER TABLE stock_document DROP FOREIGN KEY FK_97AFDE3465FCFA0D');
        $this->addSql('ALTER TABLE stock_document_item DROP FOREIGN KEY FK_3C308369C33F7837');
        $this->addSql('ALTER TABLE stock_document_item DROP FOREIGN KEY FK_3C3083694584665A');
        $this->addSql('ALTER TABLE stock_document_item DROP FOREIGN KEY FK_3C3083695248959B');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B5C33F7837');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B54584665A');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B55080ECDE');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B565FCFA0D');
        $this->addSql('DROP TABLE stock');
        $this->addSql('DROP TABLE stock_document');
        $this->addSql('DROP TABLE stock_document_item');
        $this->addSql('DROP TABLE stock_movement');
    }
}
