<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Crypto\SecretBox;
use App\Entity\AuditLog;
use App\Entity\Contractor;
use App\Gmail\GmailMailbox;
use App\Gmail\MailIndexer;
use App\Gmail\MailParser;
use App\Repository\ContractorMailRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Tests\Factory\ContractorFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Support\FakeGmailMailbox;
use Doctrine\ORM\EntityManagerInterface;

final class GmailTest extends FunctionalTestCase
{
    public function testSaveSettingsEncryptsAppPassword(): void
    {
        $this->loginAs(UserFactory::owner());
        $this->client->request('GET', '/ustawienia');
        self::assertSelectorExists('a[href="/ustawienia/gmail"]');

        $this->client->request('GET', '/ustawienia/gmail');
        $this->client->submitForm('Zapisz', [
            'gmail_settings[gmail__address]' => ' Gospodarstwo@Gmail.com ',
            'gmail_settings[app_password]' => 'abcd efgh ijkl mnop',
        ]);
        self::assertResponseRedirects('/ustawienia/gmail');

        $this->useFarm();
        $settings = $this->service(Settings::class);
        self::assertSame('gospodarstwo@gmail.com', $settings->string(SettingKey::GMAIL_ADDRESS));
        $encrypted = (string) $settings->string(SettingKey::GMAIL_APP_PASSWORD);
        self::assertStringNotContainsString('abcd', $encrypted);
        self::assertSame('abcdefghijklmnop', $this->service(SecretBox::class)->decrypt($encrypted));

        $log = $this->service(EntityManagerInterface::class)->getRepository(AuditLog::class)->findOneBy(['entity' => 'Setting', 'entityId' => SettingKey::GMAIL_APP_PASSWORD]);
        self::assertNotNull($log);
        self::assertSame(['old' => '***', 'new' => '***'], $log->getChanges());
    }

    public function testInvalidAppPasswordIsRejected(): void
    {
        $this->loginAs(UserFactory::owner());
        $this->client->request('GET', '/ustawienia/gmail');
        $this->client->submitForm('Zapisz', [
            'gmail_settings[gmail__address]' => 'gospodarstwo@gmail.com',
            'gmail_settings[app_password]' => 'moje-zwykle-haslo1',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Hasło do aplikacji to 16 liter', (string) $this->client->getResponse()->getContent());
    }

    public function testMailTabIsHiddenUntilMailboxIsConnected(): void
    {
        $contractor = ContractorFactory::createOne(['email' => 'jan@agro-kowalski.pl']);
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', \sprintf('/kontrahenci/%d', $contractor->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a.tab[href$="zakladka=poczta"]');

        $this->client->request('GET', \sprintf('/kontrahenci/%d/poczta/1', $contractor->getId()));
        self::assertResponseStatusCodeSame(404);
    }

    public function testCorrespondenceTabMessageAndAttachment(): void
    {
        $contractor = ContractorFactory::createOne(['email' => 'Jan@agro-kowalski.pl', 'name' => 'Agro Kowalski']);
        $other = ContractorFactory::createOne(['email' => 'ktos@inny.pl']);
        $this->loginAs(UserFactory::accountant());
        $mailbox = $this->fakeMailbox();
        $mailbox->preview = true;
        $mailbox->add(42, (string) file_get_contents(__DIR__.'/../fixtures/gmail/faktura.eml'));

        $this->client->request('GET', \sprintf('/kontrahenci/%d?zakladka=poczta', $contractor->getId()));
        self::assertSelectorTextContains('[data-mail-tab]', 'Maile nie były jeszcze synchronizowane');

        $this->client->submitForm('Odśwież');
        self::assertResponseRedirects(\sprintf('/kontrahenci/%d?zakladka=poczta', $contractor->getId()));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Dopisano 1 wiadomość.');
        self::assertSelectorTextContains('a.tab-active', 'Maile (1)');
        $link = $crawler->filter(\sprintf('a[href="/kontrahenci/%d/poczta/42"]', $contractor->getId()));
        self::assertCount(1, $link);
        self::assertStringContainsString('Faktura za październik', $link->text());
        self::assertStringContainsString('od Jan Kowalski – Agro', $link->text());
        self::assertStringContainsString('09:15', $link->text());
        self::assertStringEndsWith('20 października', $crawler->filter('[data-mail-day]')->text());

        $crawler = $this->client->request('GET', \sprintf('/kontrahenci/%d/poczta/42', $contractor->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Faktura za październik');
        $frame = $crawler->filter('iframe[data-controller="mail-frame"]');
        self::assertSame('allow-same-origin allow-popups allow-popups-to-escape-sandbox', $frame->attr('sandbox'));
        $document = (string) $frame->attr('srcdoc');
        self::assertStringContainsString('img-src data: https: http:', $document, 'obrazy z maila pokazujemy od razu');
        self::assertStringNotContainsString('<script', $document);
        self::assertSelectorTextContains('[data-mail-attachments]', 'FV_10_2026.pdf');
        self::assertSelectorTextNotContains('[data-mail-attachments]', 'image001.png');
        self::assertSelectorExists('a[href*="rfc822msgid%3Aabc123%40agro-kowalski.pl"]');

        $this->client->request('GET', \sprintf('/kontrahenci/%d/poczta/42/zalacznik/1', $contractor->getId()));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertResponseHeaderSame('x-content-type-options', 'nosniff');
        self::assertStringStartsWith('inline;', (string) $this->client->getResponse()->headers->get('content-disposition'));

        $this->client->request('GET', \sprintf('/kontrahenci/%d/poczta/42/zalacznik/1?pobierz=1', $contractor->getId()));
        self::assertResponseHeaderSame('content-type', 'application/octet-stream');
        self::assertStringStartsWith('attachment;', (string) $this->client->getResponse()->headers->get('content-disposition'));

        $this->client->request('GET', \sprintf('/kontrahenci/%d/poczta/42/zalacznik/0', $contractor->getId()));
        self::assertResponseStatusCodeSame(404, 'logo ze stopki nie jest załącznikiem');

        // Przez kartę innego kontrahenta nie da się przeczytać cudzej wiadomości.
        $this->client->request('GET', \sprintf('/kontrahenci/%d/poczta/42', $other->getId()));
        self::assertResponseStatusCodeSame(404);
        $crawler = $this->client->request('GET', \sprintf('/kontrahenci/%d?zakladka=poczta', $other->getId()));
        self::assertSelectorTextContains('[data-mail-tab]', 'Brak maili');
    }

    public function testPreviewIsOffByDefaultAndRowsOpenGmail(): void
    {
        $contractor = ContractorFactory::createOne(['email' => 'jan@agro-kowalski.pl']);
        $this->loginAs(UserFactory::owner());
        $this->useFarm();
        self::assertFalse($this->service(Settings::class)->bool(SettingKey::GMAIL_PREVIEW));

        $mailbox = $this->fakeMailbox();
        $mailbox->add(42, (string) file_get_contents(__DIR__.'/../fixtures/gmail/faktura.eml'));
        $this->service(MailIndexer::class)->sync();

        $crawler = $this->client->request('GET', \sprintf('/kontrahenci/%d?zakladka=poczta', $contractor->getId()));
        $row = $crawler->filter('[data-mail-row]');
        self::assertCount(1, $row);
        self::assertCount(0, $row->filter('a[href*="/poczta/42"]'), 'bez podglądu nie ma linku do Folwarku');
        $links = $row->filter('a[href*="rfc822msgid%3Aabc123%40agro-kowalski.pl"][target="_blank"]');
        self::assertCount(2, $links, 'cały wiersz i przycisk otwierają Gmaila');

        $this->client->request('GET', \sprintf('/kontrahenci/%d/poczta/42', $contractor->getId()));
        self::assertResponseRedirects(\sprintf('/kontrahenci/%d?zakladka=poczta', $contractor->getId()));
        $this->client->request('GET', \sprintf('/kontrahenci/%d/poczta/42/zalacznik/1', $contractor->getId()));
        self::assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', '/szukaj/poczta?q=październik&limit=5');
        self::assertCount(1, $crawler->filter('turbo-frame#search-mail a[href*="rfc822msgid"][target="_blank"]'));

        // Po włączeniu w ustawieniach wiersz prowadzi do podglądu, a przycisk dalej do Gmaila.
        $mailbox->preview = true;
        $crawler = $this->client->request('GET', \sprintf('/kontrahenci/%d?zakladka=poczta', $contractor->getId()));
        self::assertCount(1, $crawler->filter(\sprintf('[data-mail-row] a[href="/kontrahenci/%d/poczta/42"]', $contractor->getId())));
        self::assertCount(1, $crawler->filter('[data-mail-row] a[href*="rfc822msgid"]'));
    }

    public function testPreviewSettingIsSaved(): void
    {
        $this->loginAs(UserFactory::owner());
        $this->client->request('GET', '/ustawienia/gmail');
        $this->client->submitForm('Zapisz', ['gmail_settings[gmail__preview]' => true]);
        self::assertResponseRedirects('/ustawienia/gmail');

        $this->useFarm();
        self::assertTrue($this->service(Settings::class)->bool(SettingKey::GMAIL_PREVIEW));
    }

    public function testMailsAreAssignedByDomain(): void
    {
        $domain = ContractorFactory::createOne(['email' => null, 'mailDomains' => '@Agro-Kowalski.pl, inna-firma.pl']);
        $subdomain = ContractorFactory::createOne(['email' => null, 'mailDomains' => 'kowalski.pl']);
        $this->loginAs(UserFactory::owner());
        $mailbox = $this->fakeMailbox();
        $mailbox->add(42, (string) file_get_contents(__DIR__.'/../fixtures/gmail/faktura.eml'));

        $this->useFarm();
        self::assertSame(1, $this->service(MailIndexer::class)->sync());
        $mails = $this->service(ContractorMailRepository::class);
        self::assertSame(1, $mails->countForContractor($domain));
        self::assertSame(0, $mails->countForContractor($subdomain), 'agro-kowalski.pl to nie subdomena kowalski.pl');
        self::assertTrue($mails->findOneBy(['uid' => 42])?->isIncoming());

        $mailbox->preview = true;
        $this->client->request('GET', \sprintf('/kontrahenci/%d/poczta/42', $domain->getId()));
        self::assertResponseIsSuccessful();

        // Własna domena skrzynki nie przypisuje wszystkich maili.
        $own = ContractorFactory::createOne(['email' => 'folwark@gmail.com', 'mailDomains' => null]);
        $this->service(MailIndexer::class)->sync();
        self::assertSame(0, $mails->countForContractor($own));
    }

    public function testPublicAndInvalidDomainsAreRejected(): void
    {
        $contractor = ContractorFactory::createOne();
        $this->loginAs(UserFactory::owner());

        foreach (['gmail.com' => 'wspólna dla wielu osób', '@wp.pl' => 'wspólna dla wielu osób', 'nie domena' => 'nie wygląda na domenę'] as $value => $message) {
            $this->client->request('GET', \sprintf('/kontrahenci/%d/edycja', $contractor->getId()));
            $this->client->submitForm('Zapisz', ['contractor[mailDomains]' => $value]);
            self::assertResponseStatusCodeSame(422);
            self::assertStringContainsString($message, (string) $this->client->getResponse()->getContent());
        }

        $this->client->request('GET', \sprintf('/kontrahenci/%d/edycja', $contractor->getId()));
        $this->client->submitForm('Zapisz', ['contractor[mailDomains]' => '@Firma.PL; mail.firma.com']);
        self::assertResponseRedirects();

        $em = $this->service(EntityManagerInterface::class);
        $em->clear();
        $saved = $em->find(Contractor::class, $contractor->getId());
        self::assertSame('firma.pl, mail.firma.com', $saved?->getMailDomains());
        self::assertSame(['@firma.pl', '@mail.firma.com'], $saved?->getMailMatchers());
    }

    public function testIndexFollowsMailboxAndContractorAddresses(): void
    {
        $agro = ContractorFactory::createOne(['email' => 'jan@agro-kowalski.pl']);
        $biuro = ContractorFactory::createOne(['email' => null]);
        $this->loginAs(UserFactory::owner());
        $mailbox = $this->fakeMailbox();
        $eml = (string) file_get_contents(__DIR__.'/../fixtures/gmail/faktura.eml');
        $mailbox->add(42, $eml);
        $mailbox->add(43, str_replace('abc123@', 'def456@', $eml));

        $this->useFarm();
        $indexer = $this->service(MailIndexer::class);
        $mails = $this->service(ContractorMailRepository::class);
        self::assertSame(2, $indexer->sync());
        self::assertSame(0, $indexer->sync(), 'kolejna synchronizacja nie dubluje wpisów');
        self::assertSame(2, $mails->countForContractor($agro));
        self::assertTrue($mails->findOneBy(['uid' => 42])?->isIncoming());
        self::assertTrue($mails->findOneBy(['uid' => 42])?->hasAttachments());

        // Mail skasowany w Gmailu znika z indeksu.
        $mailbox->remove(43);
        $indexer->sync();
        self::assertSame(1, $mails->countForContractor($agro));

        // Nowy adres kontrahenta (tu: z DW) – indeks budujemy od nowa i mail trafia też do niego.
        $em = $this->service(EntityManagerInterface::class);
        $em->find(Contractor::class, $biuro->getId())?->setEmail('biuro@agro-kowalski.pl');
        $em->flush();
        self::assertSame(2, $indexer->sync());
        $copy = $mails->findOneBy(['contractor' => $biuro->getId()]);
        self::assertNotNull($copy);
        self::assertFalse($copy->isIncoming(), 'kontrahent tylko w DW – mail nie jest od niego');
        self::assertNotNull($indexer->syncedAt());
    }

    public function testGlobalSearchFindsMailsAndContractorByEmail(): void
    {
        $contractor = ContractorFactory::createOne(['email' => 'jan@agro-kowalski.pl', 'name' => 'Agro Kowalski']);
        ContractorFactory::createOne(['email' => 'ktos@inny.pl', 'name' => 'Inny']);
        $this->loginAs(UserFactory::owner());
        $mailbox = $this->fakeMailbox();
        $mailbox->configured = false;
        $mailbox->preview = true;

        // Bez skrzynki: po adresie e-mail znajduje się kontrahent, a poczty nie ma.
        $crawler = $this->client->request('GET', '/szukaj?q=agro-kowalski.pl', [], [], ['HTTP_X_REQUESTED_WITH' => 'fetch']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(\sprintf('a[href="/kontrahenci/%d"]', $contractor->getId())));
        self::assertCount(0, $crawler->filter('turbo-frame#search-mail'));

        $mailbox->configured = true;
        $mailbox->add(42, (string) file_get_contents(__DIR__.'/../fixtures/gmail/faktura.eml'));
        $crawler = $this->client->request('GET', '/szukaj?q=październik', [], [], ['HTTP_X_REQUESTED_WITH' => 'fetch']);
        self::assertSame('/szukaj/poczta?q=pa%C5%BAdziernik&limit=5', $crawler->filter('turbo-frame#search-mail')->attr('src'));

        $crawler = $this->client->request('GET', '/szukaj/poczta?q=październik&limit=5');
        self::assertResponseIsSuccessful();
        $link = $crawler->filter(\sprintf('turbo-frame#search-mail a[href="/kontrahenci/%d/poczta/42"]', $contractor->getId()));
        self::assertCount(1, $link);
        self::assertStringContainsString('Faktura za październik', $link->text());
        self::assertStringContainsString('Agro Kowalski', $link->text());

        $this->client->request('GET', '/szukaj/poczta?q=nieistniejace&limit=5');
        self::assertSelectorTextContains('turbo-frame#search-mail', 'Brak maili kontrahentów');
    }

    public function testContractorWithoutEmail(): void
    {
        $contractor = ContractorFactory::createOne(['email' => null]);
        $this->loginAs(UserFactory::owner());
        $this->fakeMailbox();

        $this->client->request('GET', \sprintf('/kontrahenci/%d?zakladka=poczta', $contractor->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-mail-tab]', 'Ani kontrahent, ani jego osoby nie mają adresu e-mail');
    }

    private function fakeMailbox(): FakeGmailMailbox
    {
        $this->client->disableReboot();
        $mailbox = new FakeGmailMailbox($this->service(Settings::class), $this->service(SecretBox::class), new MailParser());
        static::getContainer()->set(GmailMailbox::class, $mailbox);

        return $mailbox;
    }
}
