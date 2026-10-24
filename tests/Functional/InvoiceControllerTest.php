<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceKind;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Tests\Factory\ContractorFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class InvoiceControllerTest extends FunctionalTestCase
{
    public function testWorkerHasNoAccessAndAccountantIsReadOnly(): void
    {
        $invoice = InvoiceFactory::createOne();

        $this->loginAs(UserFactory::worker());
        $this->client->request('GET', '/faktury');
        self::assertResponseStatusCodeSame(403);

        $this->loginAs(UserFactory::accountant());
        $this->client->request('GET', '/faktury/'.$invoice->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Podgląd tylko do odczytu');
        self::assertTrue($this->reload($invoice)->isUnread(), 'Księgowa nie oznacza faktur jako przeczytanych');
    }

    public function testTabsSumsAndFilters(): void
    {
        $contractor = ContractorFactory::createOne(['name' => 'Agro-Pasze']);
        InvoiceFactory::createOne(['number' => 'K/1', 'contractor' => $contractor, 'issueDate' => new \DateTimeImmutable('today')]);
        InvoiceFactory::createOne(['number' => 'K/2', 'issueDate' => new \DateTimeImmutable('-400 days')]);
        InvoiceFactory::createOne(['number' => 'P/1', 'kind' => InvoiceKind::Income]);

        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/faktury?rodzaj=zakupowe');
        self::assertSelectorCount(2, 'tbody tr');
        self::assertSelectorTextContains('tfoot', '2 faktury');
        self::assertSelectorTextContains('tfoot', '2 460,00');

        $this->client->request('GET', '/faktury?rodzaj=sprzedazowe');
        self::assertSelectorCount(1, 'tbody tr');

        $this->client->request('GET', '/faktury?rodzaj=zakupowe&okres=ten-rok&kontrahent='.$contractor->getId());
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'K/1');

        $this->client->request('GET', '/faktury');
        self::assertResponseRedirects('/faktury?rodzaj=zakupowe&okres=ten-rok&kontrahent='.$contractor->getId(), null, 'Filtry są zapamiętywane per użytkownik');
    }

    public function testStatusFilterIsNotRememberedSoReadInvoicesStayOnTheList(): void
    {
        InvoiceFactory::createOne(['number' => 'K/nowa']);
        $read = InvoiceFactory::createOne(['number' => 'K/przeczytana']);

        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/faktury?rodzaj=wszystkie&status=new');
        self::assertSelectorCount(2, 'tbody tr');

        $this->client->request('GET', '/faktury/'.$read->getId());
        self::assertFalse($this->reload($read)->isUnread());

        $this->client->request('GET', '/faktury');
        self::assertResponseRedirects('/faktury?rodzaj=wszystkie', null, 'Status nie jest zapamiętywany, domyślnie „Wszystkie”');
        $this->client->followRedirect();
        self::assertSelectorCount(2, 'tbody tr');
    }

    public function testEmptyFilterFieldsFromFormAreIgnored(): void
    {
        InvoiceFactory::createOne(['number' => 'K/7']);
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/faktury?rodzaj=zakupowe&okres=wszystko&kontrahent=&status=read&platnosc=&kategoria=&kwota_od=&kwota_do=&szukaj=');
        self::assertResponseIsSuccessful('Puste pola wyboru z formularza filtrów nie dają błędu 400');
        $this->client->request('GET', '/umowy?kontrahent=&rodzaj=');
        self::assertResponseIsSuccessful();
    }

    public function testOldTabNamesStillWork(): void
    {
        InvoiceFactory::createOne(['number' => 'P/9', 'kind' => InvoiceKind::Income]);
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/faktury?rodzaj=przychodowe');
        self::assertSelectorTextContains('.tab-active', 'Sprzedażowe');
        self::assertSelectorTextContains('tbody', 'P/9');
    }

    public function testInvoicesWithItemsWithoutProductAreMarked(): void
    {
        $em = $this->service(EntityManagerInterface::class);
        $missing = InvoiceFactory::createOne(['number' => 'BRAK/1']);
        $missing->addItem($item = new InvoiceItem($missing, 1, 'Nawóz NPK'));
        $missing->addItem($skipped = (new InvoiceItem($missing, 2, 'Transport'))->setSkipped(true));
        $costOnly = InvoiceFactory::createOne(['number' => 'KOSZT/1', 'status' => InvoiceStatus::CostOnly]);
        $costOnly->addItem($other = new InvoiceItem($costOnly, 1, 'Energia'));
        InvoiceFactory::createOne(['number' => 'BEZ-XML/1']);
        $em->persist($item);
        $em->persist($skipped);
        $em->persist($other);
        $em->flush();

        $this->loginAs(UserFactory::owner());
        $this->client->request('GET', '/faktury?rodzaj=zakupowe');
        self::assertSelectorTextContains('#invoice-row-'.$missing->getId(), 'Bez towaru: 1');
        self::assertSelectorTextNotContains('#invoice-row-'.$costOnly->getId(), 'Bez towaru', '„Tylko koszt” nie wymaga towarów');

        $this->client->request('GET', '/faktury?rodzaj=zakupowe&bez_towaru=1');
        self::assertSelectorCount(1, 'tbody tr');
        self::assertSelectorTextContains('tbody', 'BRAK/1');

        $this->client->request('GET', '/faktury/'.$missing->getId());
        self::assertSelectorTextContains('main', 'bez przypisanego towaru');
    }

    public function testPreviewMarksAsReadAndActionsWork(): void
    {
        $invoice = InvoiceFactory::createOne(['number' => 'FV/77/2026']);
        $owner = UserFactory::owner();
        $this->loginAs($owner);

        $crawler = $this->client->request('GET', '/faktury/'.$invoice->getId().'/podglad');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#preview');
        self::assertSelectorTextContains('#preview', 'FV/77/2026');

        $invoice = $this->reload($invoice);
        self::assertSame(InvoiceStatus::Read, $invoice->getStatus());
        self::assertSame($owner->getId(), $invoice->getReadBy()?->getId());

        $this->client->submit($crawler->filter('form[action*="/oplacona"]')->form());
        self::assertTrue($this->reload($invoice)->isPaid());

        $crawler = $this->client->request('GET', '/faktury/'.$invoice->getId());
        $this->client->submit($crawler->filter('form[action*="/pomin"]')->form());
        self::assertResponseRedirects('/faktury/'.$invoice->getId());
        self::assertSame(InvoiceStatus::Skipped, $this->reload($invoice)->getStatus());

        $actions = array_map(
            static fn (AuditLog $log): string => $log->getAction(),
            $this->service(EntityManagerInterface::class)->getRepository(AuditLog::class)->findBy(['entity' => 'Invoice', 'entityId' => (string) $invoice->getId()], ['id' => 'ASC']),
        );
        self::assertSame(['read', 'paid', 'status'], $actions);
    }

    public function testContextMenuActionReturnsToTheSameList(): void
    {
        $invoice = InvoiceFactory::createOne(['number' => 'FV/MENU/1']);
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/faktury?rodzaj=zakupowe&szukaj=MENU');
        self::assertSelectorExists('table[data-controller~="context-menu"]');
        $template = $crawler->filter(\sprintf('#invoice-row-%d template[data-context-menu]', $invoice->getId()));
        self::assertStringContainsString('Oznacz jako opłaconą', $template->html());
        $token = $crawler->filter(\sprintf('#invoice-row-%d template form[action$="/oplacona?z=lista"] input[name=_token]', $invoice->getId()))->attr('value');

        $this->client->request('POST', \sprintf('/faktury/%d/oplacona?z=lista', $invoice->getId()), ['_token' => $token], [], ['HTTP_REFERER' => 'http://localhost/faktury?rodzaj=zakupowe&szukaj=MENU']);
        self::assertResponseRedirects('/faktury?rodzaj=zakupowe&szukaj=MENU');

        $this->client->request('POST', \sprintf('/faktury/%d/nieoplacona?z=lista', $invoice->getId()), ['_token' => $token], [], ['HTTP_REFERER' => 'https://obca-strona.example/faktury?x=1']);
        self::assertResponseRedirects('/faktury', null, 'Obcy adres w nagłówku Referer nie przekierowuje poza aplikację');
    }

    public function testPreviewActionRespondsWithTurboStream(): void
    {
        $invoice = InvoiceFactory::createOne();
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/faktury/'.$invoice->getId().'/podglad');
        $form = $crawler->filter('form[action*="/pomin"]')->form();
        $this->client->request('POST', $form->getUri(), $form->getValues(), [], [
            'HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html',
            'HTTP_TURBO_FRAME' => 'preview',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('turbo-stream', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSelectorExists('turbo-stream[action=replace][target=invoice-row-'.$invoice->getId().']');
    }

    public function testCostOnlyAssignsCenterAndCategory(): void
    {
        $this->useFarm();
        $this->service(\App\Setup\StartupData::class)->seed();
        $invoice = InvoiceFactory::createOne();
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/faktury/'.$invoice->getId().'/tylko-koszt');
        $form = $crawler->selectButton('Zapisz')->form();
        $categoryValue = $crawler->filter('select[name="cost_only[costCategory]"] option:contains("Paliwo")')->attr('value');
        $form['cost_only[costCategory]'] = (string) $categoryValue;
        $this->client->submit($form);

        $invoice = $this->reload($invoice);
        self::assertSame(InvoiceStatus::CostOnly, $invoice->getStatus());
        self::assertSame('Paliwo', $invoice->getCostCategory()?->getName());
    }

    public function testSortByTwoColumns(): void
    {
        $a = ContractorFactory::createOne(['name' => 'Alfa']);
        $b = ContractorFactory::createOne(['name' => 'Beta']);
        InvoiceFactory::createOne(['number' => 'B-stara', 'contractor' => $b, 'contractorName' => 'Beta', 'issueDate' => new \DateTimeImmutable('-20 days')]);
        InvoiceFactory::createOne(['number' => 'A-nowa', 'contractor' => $a, 'contractorName' => 'Alfa', 'issueDate' => new \DateTimeImmutable('-1 day')]);
        InvoiceFactory::createOne(['number' => 'B-nowa', 'contractor' => $b, 'contractorName' => 'Beta', 'issueDate' => new \DateTimeImmutable('-2 days')]);
        InvoiceFactory::createOne(['number' => 'A-stara', 'contractor' => $a, 'contractorName' => 'Alfa', 'issueDate' => new \DateTimeImmutable('-30 days'), 'status' => InvoiceStatus::Skipped]);
        $this->loginAs(UserFactory::owner());

        $numbers = fn (string $query): array => $this->client->request('GET', '/faktury?rodzaj=zakupowe'.$query)->filter('tbody .card-title-cell a')->each(static fn ($a): string => trim($a->text()));

        self::assertSame(['A-nowa', 'B-nowa', 'B-stara', 'A-stara'], $numbers(''), 'Domyślnie od najnowszej');
        self::assertSame(['A-nowa', 'A-stara', 'B-nowa', 'B-stara'], $numbers('&sort=kontrahent&kierunek=asc&sort2=data&kierunek2=desc'));
        self::assertSame(['A-stara', 'A-nowa', 'B-nowa', 'B-stara'], $numbers('&sort=status&kierunek=desc&sort2=numer&kierunek2=asc'), 'Status sortowany przez ukrytą kolumnę');

        $crawler = $this->client->request('GET', '/faktury?rodzaj=zakupowe&sort=kontrahent&kierunek=asc');
        $number = $crawler->filter('th a.sort-link:contains("Numer")');
        self::assertStringContainsString('sort=numer&kierunek=asc', (string) $number->attr('href'), 'Klik – tylko ta kolumna');
        self::assertStringNotContainsString('sort2', (string) $number->attr('href'));
        $header = $crawler->filter('th a.sort-link:contains("Data wystawienia")');
        self::assertStringNotContainsString('sort', (string) $header->attr('href'), 'Klik w domyślną kolumnę wraca do domyślnego sortowania');
        self::assertStringContainsString('sort=kontrahent&kierunek=asc&sort2=data&kierunek2=desc', (string) $header->attr('data-sort-link-add-value'), 'Shift + klik – druga kolumna');
        self::assertSelectorExists('form.filter-bar input[type=hidden][name=sort][value=kontrahent]', 'Formularz filtrów nie gubi sortowania');
    }

    public function testBulkStatusChangeForSelectedAndAllMatching(): void
    {
        $first = InvoiceFactory::createOne(['number' => 'M/1']);
        $second = InvoiceFactory::createOne(['number' => 'M/2']);
        $income = InvoiceFactory::createOne(['number' => 'M/3', 'kind' => InvoiceKind::Income]);
        $other = InvoiceFactory::createOne(['number' => 'X/1']);
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/faktury?rodzaj=wszystkie&szukaj=M/');
        self::assertSelectorCount(3, 'tbody input[name="ids[]"]');
        $token = $crawler->filter('form#invoice-bulk input[name=_token]')->attr('value');
        $filter = $crawler->filter('form#invoice-bulk input[name=filtr]')->attr('value');

        $this->client->request('POST', '/faktury/zbiorczo', ['_token' => $token, 'akcja' => 'pomin', 'filtr' => $filter, 'ids' => [$first->getId(), $income->getId()]]);
        self::assertResponseRedirects('/faktury?'.$filter);
        self::assertSame(InvoiceStatus::Skipped, $this->reload($first)->getStatus());
        self::assertNotSame(InvoiceStatus::Skipped, $this->reload($income)->getStatus(), 'Sprzedażowej się nie pomija');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Zmieniono 1 fakturę. Pozostałych (1) zmiana nie dotyczy');

        $this->client->request('POST', '/faktury/zbiorczo', ['_token' => $token, 'akcja' => 'oplacone', 'filtr' => $filter, 'wszystkie' => 1]);
        foreach ([$first, $second, $income] as $invoice) {
            self::assertTrue($this->reload($invoice)->isPaid(), 'Wszystkie pasujące do filtrów');
        }
        self::assertFalse($this->reload($other)->isPaid(), 'Faktura spoza filtrów bez zmian');

        $this->client->request('POST', '/faktury/zbiorczo', ['_token' => 'zly', 'akcja' => 'oplacone', 'ids' => [$other->getId()]]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testBulkCostOnly(): void
    {
        $this->useFarm();
        $this->service(\App\Setup\StartupData::class)->seed();
        $first = InvoiceFactory::createOne();
        $second = InvoiceFactory::createOne();
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', \sprintf('/faktury/zbiorczo/tylko-koszt?ids[]=%d&ids[]=%d&filtr=rodzaj%%3Dzakupowe', $first->getId(), $second->getId()));
        self::assertSelectorTextContains('turbo-frame#modal', 'Tylko koszt – 2 faktury');
        $form = $crawler->selectButton('Zapisz dla 2 faktur')->form();
        $form['cost_only[costCategory]'] = (string) $crawler->filter('select[name="cost_only[costCategory]"] option:contains("Paliwo")')->attr('value');
        $this->client->submit($form);
        self::assertResponseRedirects('/faktury?rodzaj=zakupowe');

        foreach ([$first, $second] as $invoice) {
            self::assertSame(InvoiceStatus::CostOnly, $this->reload($invoice)->getStatus());
            self::assertSame('Paliwo', $this->reload($invoice)->getCostCategory()?->getName());
        }
    }

    public function testExportXlsx(): void
    {
        InvoiceFactory::createMany(3);
        $this->loginAs(UserFactory::accountant());

        $this->client->request('GET', '/faktury/eksport?rodzaj=zakupowe');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function testPaymentDeadlineNotifications(): void
    {
        $owner = UserFactory::owner();
        $soon = InvoiceFactory::createOne(['number' => 'SOON']);
        $soon->setPaymentDue(new \DateTimeImmutable('+2 days'))->setPayment(PaymentStatus::Unpaid);
        $late = InvoiceFactory::createOne(['number' => 'LATE']);
        $late->setPaymentDue(new \DateTimeImmutable('-1 day'))->setPayment(PaymentStatus::Unpaid);
        $paid = InvoiceFactory::createOne(['number' => 'PAID']);
        $paid->setPaymentDue(new \DateTimeImmutable('-1 day'))->markPaid();
        $this->service(EntityManagerInterface::class)->flush();

        $this->useFarm();
        $deadlines = $this->service(\App\Notification\DeadlineNotifications::class);
        $deadlines->generate();
        $deadlines->generate();

        $titles = array_map(
            static fn ($n): string => $n->getTitle(),
            $this->service(EntityManagerInterface::class)->getRepository(\App\Entity\Notification::class)->findBy(['user' => $owner]),
        );
        self::assertCount(2, $titles);
        self::assertStringContainsString('SOON', implode(' ', $titles));
        self::assertStringContainsString('LATE', implode(' ', $titles));
        self::assertStringContainsString('po terminie', implode(' ', $titles));

        $this->loginAs($owner);
        $this->client->request('GET', '/?edycja=1');
        self::assertSelectorTextContains('#attention-title', 'Wymaga uwagi');
        self::assertSelectorTextContains('main', 'LATE');
    }

    private function reload(Invoice $invoice): Invoice
    {
        $em = $this->service(EntityManagerInterface::class);
        $em->clear();
        $fresh = $em->find(Invoice::class, $invoice->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }
}
