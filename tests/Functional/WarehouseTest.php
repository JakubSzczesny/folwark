<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\InvoiceItem;
use App\Entity\Product;
use App\Entity\ProductUnitConversion;
use App\Entity\Stock;
use App\Entity\StockDocument;
use App\Entity\StockDocumentItem;
use App\Entity\Unit;
use App\Entity\Warehouse;
use App\Enum\DocumentType;
use App\Enum\InvoiceKind;
use App\Repository\StockRepository;
use App\Setup\StartupData;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\UserFactory;
use App\Warehouse\StockLedger;
use Doctrine\ORM\EntityManagerInterface;

final class WarehouseTest extends FunctionalTestCase
{
    private EntityManagerInterface $em;

    public function testWeightedAverageTransferInventoryAndCancel(): void
    {
        [$product, $hall, $silo] = $this->setUpWarehouse();
        $ledger = $this->service(StockLedger::class);
        $month = (new \DateTimeImmutable('today'))->format('Y/m');

        $first = $this->document(DocumentType::Pz, $product, '1000', '1.00', target: $hall);
        self::assertSame([], $ledger->approve($first, null));
        self::assertSame('PZ/'.$month.'/0001', $first->getNumber());

        $second = $this->document(DocumentType::Pz, $product, '1', '2000', target: $hall, unit: 't');
        $ledger->approve($second, null);
        self::assertSame('PZ/'.$month.'/0002', $second->getNumber());
        $this->assertStock($product, $hall, 2000.0, 3000.0);

        $ledger->approve($this->document(DocumentType::Rw, $product, '500', source: $hall), null);
        $this->assertStock($product, $hall, 1500.0, 2250.0);

        $ledger->approve($this->document(DocumentType::Mm, $product, '500', source: $hall, target: $silo), null);
        $this->assertStock($product, $hall, 1000.0, 1500.0);
        $this->assertStock($product, $silo, 500.0, 750.0);

        $ledger->approve($this->document(DocumentType::Inw, $product, '900', target: $hall), null);
        $this->assertStock($product, $hall, 900.0, 1350.0);

        $issue = $this->document(DocumentType::Wz, $product, '1000', source: $hall);
        self::assertCount(1, $ledger->preview($issue));
        $warnings = $ledger->approve($issue, null);
        self::assertCount(1, $warnings);
        self::assertStringContainsString('stan ujemny', $warnings[0]);
        $this->assertStock($product, $hall, -100.0, -150.0);

        $ledger->cancel($issue, 'Pomyłka', null);
        $this->assertStock($product, $hall, 900.0, 1350.0);
        self::assertTrue($issue->isCancelled());

        $draft = $ledger->correct($second, null);
        self::assertTrue($second->isCancelled());
        self::assertTrue($draft->isDraft());
        self::assertSame($second, $draft->getReplaces());
        self::assertCount(1, $draft->getItems());
        $this->assertStock($product, $hall, -100.0, -650.0);
    }

    public function testDocumentScreensAndPdf(): void
    {
        [$product, $hall] = $this->setUpWarehouse();
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/magazyn/dokumenty/nowy?typ=PZ');
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Zapisz szkic')->form()->getPhpValues();
        $values['stock_document']['targetWarehouse'] = (string) $hall->getId();
        $values['stock_document']['items'] = [['product' => (string) $product->getId(), 'enteredQuantity' => '2', 'enteredUnit' => '', 'unitPriceNet' => '1500']];
        $values['approve'] = '1';
        $this->client->request('POST', '/magazyn/dokumenty/nowy?typ=PZ', $values);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'PZ/');
        self::assertSelectorTextContains('main', 'zatwierdzony');

        $document = $this->em->getRepository(StockDocument::class)->findOneBy([]);
        self::assertNotNull($document);
        $this->client->request('GET', '/magazyn/dokumenty/'.$document->getId().'/pdf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');

        $this->client->request('GET', '/magazyn/stany');
        self::assertSelectorTextContains('main', 'Stany orientacyjne – bez ewidencji zużycia');
        self::assertSelectorTextContains('main', 'Pszenica');

        $this->client->request('GET', '/towary/'.$product->getId().'/stan');
        self::assertSelectorTextContains('main', $document->getNumber() ?? '');
    }

    public function testReceiptFromInvoiceAssignment(): void
    {
        [$product, $hall] = $this->setUpWarehouse();
        $invoice = InvoiceFactory::createOne(['kind' => InvoiceKind::Cost]);
        $item = (new InvoiceItem($invoice, 1, 'Pszenica konsumpcyjna'))->setValues('2', 't', '1000', '2000', '8');
        $invoice->addItem($item);
        $this->em->persist($item);
        $this->em->flush();

        $this->loginAs(UserFactory::owner());
        $crawler = $this->client->request('GET', \sprintf('/faktury/%d/towary', $invoice->getId()));
        self::assertSelectorTextContains('main', 'Utwórz też PZ');
        $form = $crawler->selectButton('Zatwierdź przypisanie')->form();
        $values = $form->getPhpValues();
        $values['rows'][$item->getId()] = ['product' => (string) $product->getId(), 'factor' => '1000', 'costCenter' => ''];
        $values['pz'] = '1';
        $values['pz_warehouse'] = (string) $hall->getId();
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects();

        $this->em->clear();
        $document = $this->em->getRepository(StockDocument::class)->findOneBy(['invoice' => $invoice->getId()]);
        self::assertNotNull($document);
        self::assertTrue($document->isApproved());
        self::assertSame('2000.00', $document->getNetTotal());
        $stock = $this->em->getRepository(Stock::class)->findOneBy(['product' => $product->getId()]);
        self::assertEqualsWithDelta(2000.0, (float) $stock?->getQuantity(), 0.0001);

        $this->client->request('GET', \sprintf('/faktury/%d/towary', $invoice->getId()));
        self::assertSelectorTextContains('main', 'Przyjęcie do magazynu');
    }

    /**
     * @return array{Product, Warehouse, Warehouse}
     */
    private function setUpWarehouse(): array
    {
        $this->useFarm();
        $this->service(StartupData::class)->seed();
        $this->em = $this->service(EntityManagerInterface::class);
        $units = $this->em->getRepository(Unit::class);
        $kg = $units->findOneBy(['symbol' => 'kg']);
        $tonne = $units->findOneBy(['symbol' => 't']);
        self::assertNotNull($kg);
        self::assertNotNull($tonne);

        $product = (new Product())->setName('Pszenica')->setBaseUnit($kg);
        $product->addConversion((new ProductUnitConversion())->setUnit($tonne)->setFactor('1000'));
        $hall = (new Warehouse())->setName('Hala');
        $silo = (new Warehouse())->setName('Silos 1');
        $this->em->persist($product);
        $this->em->persist($hall);
        $this->em->persist($silo);
        $this->em->flush();

        return [$product, $hall, $silo];
    }

    private function document(DocumentType $type, Product $product, string $quantity, ?string $price = null, ?Warehouse $source = null, ?Warehouse $target = null, ?string $unit = null): StockDocument
    {
        $item = (new StockDocumentItem())
            ->setProduct($product)
            ->setEnteredQuantity($quantity)
            ->setEnteredUnit(null !== $unit ? $this->em->getRepository(Unit::class)->findOneBy(['symbol' => $unit]) : null)
            ->setUnitPriceNet($price);
        $document = (new StockDocument($type))->setSourceWarehouse($source)->setTargetWarehouse($target)->addItem($item->recalculate());
        $this->em->persist($document);
        $this->em->flush();

        return $document;
    }

    private function assertStock(Product $product, Warehouse $warehouse, float $quantity, float $value): void
    {
        $stock = $this->service(StockRepository::class)->findOneBy(['product' => $product, 'warehouse' => $warehouse]);
        self::assertNotNull($stock);
        self::assertEqualsWithDelta($quantity, (float) $stock->getQuantity(), 0.0001, 'Ilość w '.$warehouse->getName());
        self::assertEqualsWithDelta($value, (float) $stock->getValue(), 0.01, 'Wartość w '.$warehouse->getName());
    }
}
