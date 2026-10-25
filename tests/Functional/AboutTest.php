<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\UserFactory;

final class AboutTest extends FunctionalTestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $file = static::getContainer()->getParameter('app.release_file');
        self::assertIsString($file);
        $this->file = $file;
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
        parent::tearDown();
    }

    public function testFooterAndSettingsShowVersionAndChanges(): void
    {
        file_put_contents($this->file, json_encode([
            'version' => '1.47',
            'commit' => 'c2619a7f00000000000000000000000000000000',
            'commit_date' => '2026-10-24T18:00:00+02:00',
            'updated_at' => '2026-10-25T03:17:00+02:00',
            'previous_commit' => 'd0b24ae000000000000000000000000000000000',
            'changes' => [
                ['commit' => 'c2619a7f', 'date' => '2026-10-24T18:00:00+02:00', 'subject' => 'Wygląd: czcionka Inter lokalnie', 'new' => true],
                ['commit' => 'd0b24ae0', 'date' => '2026-10-24T09:00:00+02:00', 'subject' => 'Faktury: status nie jest zapamiętywany', 'new' => false],
            ],
        ], \JSON_THROW_ON_ERROR));
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/ustawienia');
        self::assertSelectorTextContains('.app-sidebar', 'wersja 1.47');
        self::assertSelectorTextContains('main', 'Wersja 1.47, zaktualizowano 25.10.2026 03:17.');

        $this->client->request('GET', '/ustawienia/o-programie');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Folwark FMS 1.47');
        self::assertSelectorTextContains('main', 'Co nowego w ostatniej aktualizacji');
        self::assertSelectorTextContains('main', 'Wygląd: czcionka Inter lokalnie');
        self::assertSelectorCount(1, 'main .badge-new');
    }

    public function testWithoutVersionFileFooterHasNoVersion(): void
    {
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/ustawienia/o-programie');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('.app-sidebar', 'wersja');
        self::assertSelectorTextContains('main', 'Brak informacji o wersji');
    }
}
