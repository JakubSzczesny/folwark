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
        $this->client->request('GET', '/');
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
