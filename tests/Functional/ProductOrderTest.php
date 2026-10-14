<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Entity\ItemMapping;
use App\Entity\OrderCycle;
use App\Entity\Product;
use App\Entity\ProductLink;
use App\Entity\ProductLinkCategory;
use App\Entity\Unit;
use App\Enum\InvoiceStatus;
use App\Enum\OrderStatus;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class ProductOrderTest extends FunctionalTestCase
{
    use KsefTestTrait;

    public function testSyncAssignGoodsAndOrderCycle(): void
    {
        $this->configureFarm();
        $em = $this->service(EntityManagerInterface::class);
        $em->persist($litre = new Unit('l', 'litr'));
        $em->persist($diesel = (new Product())->setName('Olej napędowy')->setBaseUnit($litre));
        $em->flush();
        $dieselId = (int) $diesel->getId();

        $today = new \DateTimeImmutable('today');
        foreach ([84, 63, 42, 21] as $i => $daysAgo) {
            $this->addInvoice($this->costInvoice('PET/'.($i + 1).'/2026', $today->modify("-{$daysAgo} days")->format('Y-m-d'))->item('Olej napędowy ON', 'l', 3000, 5.5));
        }
        $this->syncKsef();

        $owner = UserFactory::owner();
        $this->loginAs($owner);
        $invoices = $em->getRepository(Invoice::class)->findBy([], ['issueDate' => 'ASC']);
        self::assertCount(4, $invoices);

        $crawler = $this->client->request('GET', \sprintf('/faktury/%d/towary', $invoices[0]->getId()));
        self::assertSelectorTextContains('main', 'Olej napędowy ON');
        $item = $invoices[0]->getItems()->first();
        self::assertNotFalse($item);
        $form = $crawler->selectButton('Zatwierdź przypisanie')->form();
        $values = $form->getPhpValues();
        $values['rows'][$item->getId()] = ['product' => (string) $dieselId, 'factor' => '1', 'costCenter' => ''];
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects(\sprintf('/faktury/%d', $invoices[0]->getId()));

        foreach (\array_slice($invoices, 1) as $invoice) {
            $crawler = $this->client->request('GET', \sprintf('/faktury/%d/towary', $invoice->getId()));
            self::assertSelectorTextContains('main', 'zapamiętane', 'Druga faktura od dostawcy wypełnia się sama');
            $this->client->submit($crawler->selectButton('Zatwierdź przypisanie')->form());
            self::assertResponseRedirects();
        }

        $em = $this->service(EntityManagerInterface::class);
        $em->clear();
        self::assertSame(4, $em->getRepository(Invoice::class)->count(['status' => InvoiceStatus::GoodsAssigned]));
        self::assertSame(1, $em->getRepository(ItemMapping::class)->count([]));

        $cycle = $em->getRepository(OrderCycle::class)->findOneBy(['product' => $dieselId, 'closedAt' => null]);
        self::assertNotNull($cycle);
        self::assertSame(21, $cycle->getIntervalDays());
        self::assertSame($today->format('Y-m-d'), $cycle->getPredictedDate()?->format('Y-m-d'), 'Ostatni zakup + 21 dni');
        self::assertSame(OrderStatus::Approaching, $cycle->getStatus());

        $this->client->request('GET', '/zamowienia');
        self::assertSelectorTextContains('main', 'Olej napędowy');
        self::assertSelectorTextContains('main', 'co 21 dni');

        $this->client->request('GET', '/zamowienia');
        $this->client->submit($this->client->getCrawler()->filter(\sprintf('td.text-right form[action="/zamowienia/%d/zamowione"]', $cycle->getId()))->form());
        $em->clear();
        self::assertSame(OrderStatus::Ordered, $em->find(OrderCycle::class, $cycle->getId())?->getStatus());

        $this->useFarm();
        $this->addInvoice($this->costInvoice('PET/5/2026', $today->format('Y-m-d'))->item('Olej napędowy ON', 'l', 3000, 5.6));
        $this->syncKsef();

        $em = $this->service(EntityManagerInterface::class);
        $em->clear();
        $latest = $em->getRepository(Invoice::class)->findOneBy(['number' => 'PET/5/2026']);
        self::assertSame(InvoiceStatus::GoodsAssigned, $latest?->getStatus(), 'Nowa faktura przypisała się sama z zapamiętanego mapowania');
        self::assertTrue($latest->isUnread(), 'Automatyczne przypisanie nie oznacza faktury jako przeczytanej');

        self::assertNotNull($em->find(OrderCycle::class, $cycle->getId())?->getClosedAt(), 'Nowa faktura zamyka cykl');
        $next = $em->getRepository(OrderCycle::class)->findOneBy(['product' => $dieselId, 'closedAt' => null]);
        self::assertSame($today->modify('+21 days')->format('Y-m-d'), $next?->getPredictedDate()?->format('Y-m-d'));
        self::assertSame(OrderStatus::Expected, $next->getStatus(), '„Zamówione” dotyczyło poprzedniego cyklu');
    }

    public function testWorkerSeesOrdersAndProductsButCannotAssignInvoices(): void
    {
        $this->configureFarm();
        $this->addInvoice($this->costInvoice('PET/1/2026', '2026-09-01')->item('Olej napędowy ON', 'l', 3000, 5.5));
        $this->syncKsef();
        $invoice = $this->service(EntityManagerInterface::class)->getRepository(Invoice::class)->findOneBy([]);

        $this->loginAs(UserFactory::worker());
        $this->client->request('GET', '/zamowienia');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/towary');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', \sprintf('/faktury/%d/towary', $invoice?->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testNewProductFromAssignmentReturnsStreamWithConversion(): void
    {
        $this->configureFarm();
        $em = $this->service(EntityManagerInterface::class);
        $em->persist($litre = new Unit('l', 'litr'));
        $em->flush();
        $this->addInvoice($this->costInvoice('SER/1/2026', '2026-09-01')->item('Olej silnikowy 15W40 5L', 'szt', 4, 139));
        $this->syncKsef();
        $item = $em->getRepository(Invoice::class)->findOneBy([])?->getItems()->first();
        self::assertInstanceOf(InvoiceItem::class, $item);

        $this->loginAs(UserFactory::owner());
        $crawler = $this->client->request('GET', '/towary/szybki?nazwa=Olej&wiersz='.$item->getId());
        $form = $crawler->selectButton('Dodaj towar')->form(['product[name]' => 'Olej silnikowy 15W40', 'product[baseUnit]' => (string) $litre->getId()]);
        $this->client->request('POST', $form->getUri(), $form->getPhpValues(), [], ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html']);

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('action="product_created"', $content);
        self::assertStringContainsString('factor="5"', $content, 'Kanister 5 l rozpoznany z nazwy pozycji');

        $this->client->request('GET', \sprintf('/faktury/towary/przelicznik?pozycja=%d&towar=%d', $item->getId(), $em->getRepository(Product::class)->findOneBy([])?->getId()));
        self::assertSame(['factor' => 5, 'unit' => 'l'], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    public function testProductWithConversionAndLink(): void
    {
        $em = $this->service(EntityManagerInterface::class);
        $this->useFarm();
        $em->persist($kg = new Unit('kg', 'kilogram'));
        $em->persist($bale = new Unit('bela', 'bela'));
        $em->persist($shops = new ProductLinkCategory('Sklepy'));
        $em->flush();
        $this->loginAs(UserFactory::worker());

        $crawler = $this->client->request('GET', '/towary/nowy');
        $form = $crawler->selectButton('Zapisz')->form();
        $values = $form->getPhpValues();
        $values['product']['name'] = 'Sianokiszonka';
        $values['product']['baseUnit'] = (string) $kg->getId();
        $values['product']['conversions'] = [['unit' => (string) $bale->getId(), 'factor' => '450']];
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects();

        $product = $em->getRepository(Product::class)->findOneBy(['name' => 'Sianokiszonka']);
        self::assertNotNull($product);
        self::assertSame(450.0, $product->factorFor('bela'));

        $crawler = $this->client->request('GET', \sprintf('/towary/%d/linki/nowy', $product->getId()));
        $this->client->submit($crawler->selectButton('Zapisz')->form(['url' => 'agrihandel.pl/sianokiszonka', 'category' => (string) $shops->getId()]));
        self::assertResponseRedirects(\sprintf('/towary/%d/linki?kategoria=%d', $product->getId(), $shops->getId()));

        $link = $em->getRepository(ProductLink::class)->findOneBy(['product' => $product]);
        self::assertSame('https://agrihandel.pl/sianokiszonka', $link?->getUrl());
        self::assertSame('agrihandel.pl', $link->getName());

        $this->client->request('GET', \sprintf('/towary/%d/linki', $product->getId()));
        self::assertSelectorExists('a[href="https://agrihandel.pl/sianokiszonka"][target="_blank"]');
    }
}
