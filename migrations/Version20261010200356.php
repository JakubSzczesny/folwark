<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010200356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Etap 2: faktury, kontrahenci, umowy, KSeF, słowniki kosztów';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(100) NOT NULL, subject VARCHAR(255) NOT NULL, kind VARCHAR(10) NOT NULL, concluded_at DATE DEFAULT NULL, valid_from DATE DEFAULT NULL, valid_to DATE DEFAULT NULL, contracted_quantity NUMERIC(15, 3) DEFAULT NULL, price NUMERIC(15, 4) DEFAULT NULL, quality_terms LONGTEXT DEFAULT NULL, status VARCHAR(12) NOT NULL, notes LONGTEXT DEFAULT NULL, contractor_id INT NOT NULL, quantity_unit_id INT DEFAULT NULL, price_unit_id INT DEFAULT NULL, INDEX IDX_E98F28597B00651C8C3213D0 (status, valid_to), INDEX IDX_E98F2859B0265DC7 (contractor_id), INDEX IDX_E98F2859B09B908 (quantity_unit_id), INDEX IDX_E98F2859939BB851 (price_unit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contract_attachment (id INT AUTO_INCREMENT NOT NULL, added_at DATETIME NOT NULL, path VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, contract_id INT NOT NULL, INDEX IDX_601E78922576E0FD (contract_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contractor (id INT AUTO_INCREMENT NOT NULL, nip VARCHAR(10) DEFAULT NULL, name VARCHAR(512) NOT NULL, short_name VARCHAR(100) DEFAULT NULL, street VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, country VARCHAR(2) NOT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(40) DEFAULT NULL, contact_person VARCHAR(120) DEFAULT NULL, kind VARCHAR(10) DEFAULT NULL, delivery_days INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, data_source VARCHAR(12) NOT NULL, data_updated_at DATETIME DEFAULT NULL, update_locked TINYINT NOT NULL, white_list_checked_at DATETIME DEFAULT NULL, active TINYINT NOT NULL, data_from_invoice_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_437BD2EF59329EEA (nip), INDEX IDX_437BD2EF5E237E06 (name), INDEX IDX_437BD2EF779CDEED (data_from_invoice_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contractor_bank_account (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(34) NOT NULL, on_white_list TINYINT DEFAULT NULL, checked_at DATETIME DEFAULT NULL, source VARCHAR(12) NOT NULL, contractor_id INT NOT NULL, INDEX IDX_9819B4B8B0265DC7 (contractor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contractor_history (id INT AUTO_INCREMENT NOT NULL, changed_at DATETIME NOT NULL, field VARCHAR(40) NOT NULL, old_value LONGTEXT DEFAULT NULL, new_value LONGTEXT DEFAULT NULL, source VARCHAR(12) NOT NULL, contractor_id INT NOT NULL, invoice_id INT DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_4BE86B0CB0265DC7 (contractor_id), INDEX IDX_4BE86B0C2989F1FD (invoice_id), INDEX IDX_4BE86B0CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cost_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, active TINYINT NOT NULL, UNIQUE INDEX UNIQ_B06C5A745E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cost_center (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, name VARCHAR(120) NOT NULL, active TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, ksef_number VARCHAR(36) NOT NULL, number VARCHAR(256) NOT NULL, kind VARCHAR(10) NOT NULL, kind_overridden TINYINT NOT NULL, type VARCHAR(12) NOT NULL, ksef_invoice_type VARCHAR(12) NOT NULL, form_code VARCHAR(10) NOT NULL, issue_date DATE NOT NULL, ksef_received_at DATETIME NOT NULL, permanent_storage_at DATETIME NOT NULL, payment_due DATE DEFAULT NULL, contractor_nip VARCHAR(50) DEFAULT NULL, contractor_name VARCHAR(512) DEFAULT NULL, seller_nip VARCHAR(10) NOT NULL, seller_name VARCHAR(512) DEFAULT NULL, buyer_identifier_type VARCHAR(10) NOT NULL, buyer_identifier VARCHAR(50) DEFAULT NULL, buyer_name VARCHAR(512) DEFAULT NULL, net NUMERIC(14, 2) NOT NULL, vat NUMERIC(14, 2) NOT NULL, gross NUMERIC(14, 2) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(16) NOT NULL, payment VARCHAR(8) NOT NULL, paid_at DATE DEFAULT NULL, corrected_ksef_number VARCHAR(36) DEFAULT NULL, corrected_number VARCHAR(256) DEFAULT NULL, xml_path VARCHAR(255) DEFAULT NULL, xml_fetched_at DATETIME DEFAULT NULL, read_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, contractor_id INT DEFAULT NULL, contract_id INT DEFAULT NULL, cost_center_id INT DEFAULT NULL, cost_category_id INT DEFAULT NULL, corrected_invoice_id INT DEFAULT NULL, read_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_90651744F7B3D475 (ksef_number), INDEX IDX_90651744F279FFB6 (issue_date), INDEX IDX_906517443BC4BCD97B00651C (kind, status), INDEX IDX_906517443BC4BCD9F279FFB6 (kind, issue_date), INDEX IDX_9065174437568737 (contractor_nip), INDEX IDX_906517446D28840DE86C63BE (payment, payment_due), INDEX IDX_90651744C5633B21 (corrected_ksef_number), INDEX IDX_90651744B0265DC7 (contractor_id), INDEX IDX_906517442576E0FD (contract_id), INDEX IDX_90651744E28F9437 (cost_center_id), INDEX IDX_9065174431E99DA1 (cost_category_id), INDEX IDX_90651744210A46D1 (corrected_invoice_id), INDEX IDX_90651744F5675CD0 (read_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invoice_item (id INT AUTO_INCREMENT NOT NULL, quantity NUMERIC(15, 4) DEFAULT NULL, unit VARCHAR(30) DEFAULT NULL, unit_price_net NUMERIC(15, 4) DEFAULT NULL, net_value NUMERIC(14, 2) DEFAULT NULL, vat_rate VARCHAR(10) DEFAULT NULL, before_correction TINYINT NOT NULL, skipped TINYINT NOT NULL, position INT NOT NULL, name VARCHAR(512) NOT NULL, cost_center_id INT DEFAULT NULL, invoice_id INT NOT NULL, INDEX IDX_1DDE477BE28F9437 (cost_center_id), INDEX IDX_1DDE477B2989F1FD (invoice_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invoice_rule (id INT AUTO_INCREMENT NOT NULL, contractor_nip VARCHAR(10) NOT NULL, active TINYINT NOT NULL, note LONGTEXT DEFAULT NULL, cost_center_id INT DEFAULT NULL, cost_category_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_441DCEA937568737 (contractor_nip), INDEX IDX_441DCEA9E28F9437 (cost_center_id), INDEX IDX_441DCEA931E99DA1 (cost_category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ksef_sync (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, new_count INT NOT NULL, new_cost_count INT NOT NULL, error LONGTEXT DEFAULT NULL, manual TINYINT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_AAEF60DDD46F4E3 (started_at), INDEX IDX_AAEF60DDA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE unit (id INT AUTO_INCREMENT NOT NULL, symbol VARCHAR(12) NOT NULL, name VARCHAR(60) NOT NULL, UNIQUE INDEX UNIQ_DCBB0C53ECC836F9 (symbol), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id)');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859B09B908 FOREIGN KEY (quantity_unit_id) REFERENCES unit (id)');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859939BB851 FOREIGN KEY (price_unit_id) REFERENCES unit (id)');
        $this->addSql('ALTER TABLE contract_attachment ADD CONSTRAINT FK_601E78922576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contractor ADD CONSTRAINT FK_437BD2EF779CDEED FOREIGN KEY (data_from_invoice_id) REFERENCES invoice (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contractor_bank_account ADD CONSTRAINT FK_9819B4B8B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contractor_history ADD CONSTRAINT FK_4BE86B0CB0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contractor_history ADD CONSTRAINT FK_4BE86B0C2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contractor_history ADD CONSTRAINT FK_4BE86B0CA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517442576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744E28F9437 FOREIGN KEY (cost_center_id) REFERENCES cost_center (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_9065174431E99DA1 FOREIGN KEY (cost_category_id) REFERENCES cost_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744210A46D1 FOREIGN KEY (corrected_invoice_id) REFERENCES invoice (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744F5675CD0 FOREIGN KEY (read_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice_item ADD CONSTRAINT FK_1DDE477BE28F9437 FOREIGN KEY (cost_center_id) REFERENCES cost_center (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice_item ADD CONSTRAINT FK_1DDE477B2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invoice_rule ADD CONSTRAINT FK_441DCEA9E28F9437 FOREIGN KEY (cost_center_id) REFERENCES cost_center (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoice_rule ADD CONSTRAINT FK_441DCEA931E99DA1 FOREIGN KEY (cost_category_id) REFERENCES cost_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ksef_sync ADD CONSTRAINT FK_AAEF60DDA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859B0265DC7');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859B09B908');
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F2859939BB851');
        $this->addSql('ALTER TABLE contract_attachment DROP FOREIGN KEY FK_601E78922576E0FD');
        $this->addSql('ALTER TABLE contractor DROP FOREIGN KEY FK_437BD2EF779CDEED');
        $this->addSql('ALTER TABLE contractor_bank_account DROP FOREIGN KEY FK_9819B4B8B0265DC7');
        $this->addSql('ALTER TABLE contractor_history DROP FOREIGN KEY FK_4BE86B0CB0265DC7');
        $this->addSql('ALTER TABLE contractor_history DROP FOREIGN KEY FK_4BE86B0C2989F1FD');
        $this->addSql('ALTER TABLE contractor_history DROP FOREIGN KEY FK_4BE86B0CA76ED395');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744B0265DC7');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_906517442576E0FD');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744E28F9437');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_9065174431E99DA1');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744210A46D1');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744F5675CD0');
        $this->addSql('ALTER TABLE invoice_item DROP FOREIGN KEY FK_1DDE477BE28F9437');
        $this->addSql('ALTER TABLE invoice_item DROP FOREIGN KEY FK_1DDE477B2989F1FD');
        $this->addSql('ALTER TABLE invoice_rule DROP FOREIGN KEY FK_441DCEA9E28F9437');
        $this->addSql('ALTER TABLE invoice_rule DROP FOREIGN KEY FK_441DCEA931E99DA1');
        $this->addSql('ALTER TABLE ksef_sync DROP FOREIGN KEY FK_AAEF60DDA76ED395');
        $this->addSql('DROP TABLE contract');
        $this->addSql('DROP TABLE contract_attachment');
        $this->addSql('DROP TABLE contractor');
        $this->addSql('DROP TABLE contractor_bank_account');
        $this->addSql('DROP TABLE contractor_history');
        $this->addSql('DROP TABLE cost_category');
        $this->addSql('DROP TABLE cost_center');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_item');
        $this->addSql('DROP TABLE invoice_rule');
        $this->addSql('DROP TABLE ksef_sync');
        $this->addSql('DROP TABLE unit');
    }
}
