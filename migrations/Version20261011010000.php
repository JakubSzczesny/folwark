<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Wiele gospodarstw. Istniejące dane (jeśli są) trafiają do jednego gospodarstwa utworzonego
 * z dotychczasowych ustawień, a dotychczasowe role użytkowników stają się członkostwami.
 */
final class Version20261011010000 extends AbstractMigration
{
    /** Tabela => [indeksy do usunięcia, polecenia po dodaniu farm_id]. */
    private const array SCOPED = [
        'audit_log' => [
            ['IDX_F6E1C0F58B8E8428'],
            ['CREATE INDEX IDX_F6E1C0F565FCFA0D8B8E8428 ON audit_log (farm_id, created_at)'],
        ],
        'contract' => [
            ['IDX_E98F28597B00651C8C3213D0'],
            ['CREATE INDEX IDX_E98F285965FCFA0D7B00651C8C3213D0 ON contract (farm_id, status, valid_to)'],
        ],
        'contract_attachment' => [[], []],
        'contractor' => [
            ['IDX_437BD2EF5E237E06', 'UNIQ_437BD2EF59329EEA'],
            [
                'CREATE INDEX IDX_437BD2EF65FCFA0D5E237E06 ON contractor (farm_id, name)',
                'CREATE UNIQUE INDEX UNIQ_437BD2EF65FCFA0D59329EEA ON contractor (farm_id, nip)',
            ],
        ],
        'cost_category' => [
            ['UNIQ_B06C5A745E237E06'],
            ['CREATE UNIQUE INDEX UNIQ_B06C5A7465FCFA0D5E237E06 ON cost_category (farm_id, name)'],
        ],
        'cost_center' => [[], []],
        'invoice' => [
            ['IDX_9065174437568737', 'IDX_906517443BC4BCD97B00651C', 'IDX_906517443BC4BCD9F279FFB6', 'IDX_906517446D28840DE86C63BE', 'IDX_90651744F279FFB6', 'UNIQ_90651744F7B3D475'],
            [
                'CREATE INDEX IDX_9065174465FCFA0DF279FFB6 ON invoice (farm_id, issue_date)',
                'CREATE INDEX IDX_9065174465FCFA0D3BC4BCD97B00651C ON invoice (farm_id, kind, status)',
                'CREATE INDEX IDX_9065174465FCFA0D3BC4BCD9F279FFB6 ON invoice (farm_id, kind, issue_date)',
                'CREATE INDEX IDX_9065174465FCFA0D37568737 ON invoice (farm_id, contractor_nip)',
                'CREATE INDEX IDX_9065174465FCFA0D6D28840DE86C63BE ON invoice (farm_id, payment, payment_due)',
                'CREATE UNIQUE INDEX UNIQ_9065174465FCFA0DF7B3D475 ON invoice (farm_id, ksef_number)',
            ],
        ],
        'invoice_rule' => [
            ['UNIQ_441DCEA937568737'],
            ['CREATE UNIQUE INDEX UNIQ_441DCEA965FCFA0D37568737 ON invoice_rule (farm_id, contractor_nip)'],
        ],
        'ksef_sync' => [
            ['IDX_AAEF60DDD46F4E3'],
            ['CREATE INDEX IDX_AAEF60DD65FCFA0DD46F4E3 ON ksef_sync (farm_id, started_at)'],
        ],
        'notification' => [
            ['UNIQ_BF5476CAA76ED39544F613DD'],
            ['CREATE UNIQUE INDEX UNIQ_BF5476CA65FCFA0DA76ED39544F613DD ON notification (farm_id, user_id, dedup_key)'],
        ],
        'recent_choice' => [
            ['UNIQ_829A0C6BA76ED3953BC4BCD981257D5D'],
            ['CREATE UNIQUE INDEX UNIQ_829A0C6BA76ED39565FCFA0D3BC4BCD981257D5D ON recent_choice (user_id, farm_id, kind, entity_id)'],
        ],
        'saved_filter' => [
            ['UNIQ_1BC22406A76ED3959B133784'],
            ['CREATE UNIQUE INDEX UNIQ_1BC22406A76ED39565FCFA0D9B133784 ON saved_filter (user_id, farm_id, list_key)'],
        ],
        'setting' => [
            [],
            ['CREATE UNIQUE INDEX UNIQ_9F74B89865FCFA0D5E237E06 ON setting (farm_id, name)'],
        ],
        'unit' => [
            ['UNIQ_DCBB0C53ECC836F9'],
            ['CREATE UNIQUE INDEX UNIQ_DCBB0C5365FCFA0DECC836F9 ON unit (farm_id, symbol)'],
        ],
    ];

    /** Nazwy kluczy obcych farm_id wygenerowane przez Doctrine. */
    private const array FOREIGN_KEYS = [
        'audit_log' => 'FK_F6E1C0F565FCFA0D',
        'contract' => 'FK_E98F285965FCFA0D',
        'contract_attachment' => 'FK_601E789265FCFA0D',
        'contractor' => 'FK_437BD2EF65FCFA0D',
        'cost_category' => 'FK_B06C5A7465FCFA0D',
        'cost_center' => 'FK_47C6AD8B65FCFA0D',
        'invoice' => 'FK_9065174465FCFA0D',
        'invoice_rule' => 'FK_441DCEA965FCFA0D',
        'ksef_sync' => 'FK_AAEF60DD65FCFA0D',
        'notification' => 'FK_BF5476CA65FCFA0D',
        'recent_choice' => 'FK_829A0C6B65FCFA0D',
        'saved_filter' => 'FK_1BC2240665FCFA0D',
        'setting' => 'FK_9F74B89865FCFA0D',
        'unit' => 'FK_DCBB0C5365FCFA0D',
    ];

    public function getDescription(): string
    {
        return 'Wiele gospodarstw: gospodarstwa, członkostwa z rolami, dane przypisane do gospodarstw; dzierżawa z płatnościami; bez listy wagi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE farm (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, nip VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_5816D045B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE farm_membership (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, role VARCHAR(20) NOT NULL, farm_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_44A3CB9165FCFA0DA76ED395 (farm_id, user_id), INDEX IDX_44A3CB9165FCFA0D (farm_id), INDEX IDX_44A3CB91A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE farm ADD CONSTRAINT FK_5816D045B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE farm_membership ADD CONSTRAINT FK_44A3CB9165FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE farm_membership ADD CONSTRAINT FK_44A3CB91A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            INSERT INTO farm (name, nip, created_at, created_by_id)
            SELECT
                COALESCE((SELECT CASE WHEN JSON_TYPE(value) = 'STRING' THEN JSON_UNQUOTE(value) END FROM setting WHERE name = 'farm.name'), 'Gospodarstwo'),
                (SELECT CASE WHEN JSON_TYPE(value) = 'STRING' THEN JSON_UNQUOTE(value) END FROM setting WHERE name = 'farm.nip'),
                NOW(),
                (SELECT MIN(id) FROM app_user WHERE role = 'ROLE_OWNER')
            FROM DUAL
            WHERE EXISTS (SELECT 1 FROM app_user)
            SQL);
        $this->addSql('INSERT INTO farm_membership (farm_id, user_id, role, created_at) SELECT f.id, u.id, u.role, NOW() FROM app_user u CROSS JOIN farm f');

        $this->addSql('ALTER TABLE app_user ADD last_farm_id INT DEFAULT NULL, DROP role');
        $this->addSql('UPDATE app_user SET last_farm_id = (SELECT MIN(id) FROM farm)');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E9370C645 FOREIGN KEY (last_farm_id) REFERENCES farm (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_88BDF3E9370C645 ON app_user (last_farm_id)');

        $this->addSql("DELETE FROM setting WHERE name IN ('farm.name', 'farm.nip')");
        $this->addSql('ALTER TABLE setting ADD id INT AUTO_INCREMENT NOT NULL FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');

        foreach (self::SCOPED as $table => [$dropIndexes, $create]) {
            foreach ($dropIndexes as $index) {
                $this->addSql(\sprintf('DROP INDEX %s ON %s', $index, $table));
            }
            $this->addSql(\sprintf('ALTER TABLE %s ADD farm_id INT DEFAULT NULL', $table));
            $this->addSql(\sprintf('UPDATE %s SET farm_id = (SELECT MIN(id) FROM farm)', $table));
            if ('audit_log' !== $table) {
                $this->addSql(\sprintf('DELETE FROM %s WHERE farm_id IS NULL', $table));
                $this->addSql(\sprintf('ALTER TABLE %s MODIFY farm_id INT NOT NULL', $table));
            }
            $this->addSql(\sprintf('ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE', $table, self::FOREIGN_KEYS[$table]));
            $this->addSql(\sprintf('CREATE INDEX IDX_%s ON %s (farm_id)', substr(self::FOREIGN_KEYS[$table], 3), $table));
            foreach ($create as $sql) {
                $this->addSql($sql);
            }
        }

        $this->addSql('ALTER TABLE contract ADD rent_amount NUMERIC(14, 2) DEFAULT NULL, ADD payment_frequency VARCHAR(12) DEFAULT NULL, ADD first_payment_date DATE DEFAULT NULL');

        $this->addSql('ALTER TABLE weighbridge_task DROP FOREIGN KEY FK_41FC1E8B35AE3EF9');
        $this->addSql('DROP TABLE weighbridge_task');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Przejście na wiele gospodarstw zmienia dane – przywróć kopię bazy sprzed migracji.');
    }
}
