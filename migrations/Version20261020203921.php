<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020203921 extends AbstractMigration
{
    /** Tabela obiektu => rodzaj notatki (App\Note\NoteSubjects). */
    private const array SUBJECTS = ['contractor' => 'contractor', 'machine' => 'machine', 'invoice' => 'invoice'];

    public function getDescription(): string
    {
        return 'Notatki jako lista wpisów (przeniesienie dotychczasowych notatek), kontrahent przy wydarzeniu w kalendarzu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE note (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, edited_at DATETIME DEFAULT NULL, subject_type VARCHAR(20) NOT NULL, subject_id INT NOT NULL, author_id INT DEFAULT NULL, farm_id INT NOT NULL, INDEX IDX_CFBDFA1465FCFA0D1E14D3F223EDC878B8E8428 (farm_id, subject_type, subject_id, created_at), INDEX IDX_CFBDFA14F675F31B (author_id), INDEX IDX_CFBDFA1465FCFA0D (farm_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14F675F31B FOREIGN KEY (author_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA1465FCFA0D FOREIGN KEY (farm_id) REFERENCES farm (id) ON DELETE CASCADE');

        // Dotychczasowe notatki stają się pierwszym wpisem (bez autora).
        foreach (self::SUBJECTS as $table => $type) {
            $this->addSql(\sprintf("INSERT INTO note (farm_id, subject_type, subject_id, body, created_at) SELECT farm_id, '%s', id, TRIM(notes), NOW() FROM %s WHERE notes IS NOT NULL AND TRIM(notes) <> ''", $type, $table));
        }

        $this->addSql('ALTER TABLE calendar_event ADD contractor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C9B0265DC7 FOREIGN KEY (contractor_id) REFERENCES contractor (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_57FA09C9B0265DC7 ON calendar_event (contractor_id)');
        $this->addSql('ALTER TABLE contractor DROP notes');
        $this->addSql('ALTER TABLE invoice DROP notes');
        $this->addSql('ALTER TABLE machine DROP notes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contractor ADD notes LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice ADD notes LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE machine ADD notes LONGTEXT DEFAULT NULL');

        // Wpisy wracają jako jeden tekst, od najstarszego.
        foreach (self::SUBJECTS as $table => $type) {
            $this->addSql(\sprintf("UPDATE %s t SET notes = (SELECT GROUP_CONCAT(n.body ORDER BY n.created_at, n.id SEPARATOR '\\n\\n') FROM note n WHERE n.subject_type = '%s' AND n.subject_id = t.id)", $table, $type));
        }

        $this->addSql('ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C9B0265DC7');
        $this->addSql('DROP INDEX IDX_57FA09C9B0265DC7 ON calendar_event');
        $this->addSql('ALTER TABLE calendar_event DROP contractor_id');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14F675F31B');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA1465FCFA0D');
        $this->addSql('DROP TABLE note');
    }
}
