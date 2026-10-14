<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ContractorBankAccount;
use App\Entity\Invoice;
use App\Enum\DataSource;
use App\Enum\InvoiceKind;
use App\Payment\TransferData;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Tests\Factory\ContractorFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class PaymentTest extends FunctionalTestCase
{
    public function testTransferDataFromContractorAccount(): void
    {
        $this->useFarm();
        $contractor = ContractorFactory::createOne(['nip' => '7311000000', 'name' => 'Petromex „Paliwa" "Sp. z o.o."']);
        $account = new ContractorBankAccount($contractor, 'PL61 1090 1014 0000 0712 1981 2874', DataSource::Manual);
        $contractor->addBankAccount($account);
        $this->service(EntityManagerInterface::class)->flush();
        $invoice = InvoiceFactory::createOne(['number' => 'FV/12/2026', 'kind' => InvoiceKind::Cost, 'contractor' => $contractor]);
        $this->service(Settings::class)->set(SettingKey::BSPAY_TITLE, 'Zapłata za {numer}');

        $transfer = $this->service(TransferData::class)->forInvoice($invoice);

        self::assertSame('Petromex Paliwa Sp. z o.o.', $transfer['recipient'], 'Bez cudzysłowów – bank ich nie przyjmuje');
        self::assertSame('61109010140000071219812874', $transfer['account']);
        self::assertSame('PL61109010140000071219812874', $transfer['iban']);
        self::assertSame('1230.00', $transfer['amount']);
        self::assertSame('Zapłata za FV/12/2026', $transfer['title']);
        self::assertFalse($transfer['splitPayment']);
    }

    public function testTransferModalAndPaidFromDashboard(): void
    {
        $owner = UserFactory::owner();
        $this->useFarm();
        $invoice = InvoiceFactory::createOne(['kind' => InvoiceKind::Cost, 'paymentDue' => new \DateTimeImmutable('-2 days')]);
        $this->loginAs($owner);

        $this->client->request('GET', \sprintf('/faktury/%d/przelew', $invoice->getId()), [], [], ['HTTP_TURBO_FRAME' => 'modal']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('script[data-bspay-transfer]');
        self::assertSelectorTextContains('turbo-frame#modal', $invoice->getNumber());

        $crawler = $this->client->request('GET', '/');
        $menu = $crawler->filter('.attention-row template[data-context-menu]');
        self::assertGreaterThan(0, $menu->count());
        self::assertStringContainsString(\sprintf('/faktury/%d/oplacona?z=pulpit', $invoice->getId()), $crawler->filter('[data-widget="attention"]')->html());

        preg_match('~oplacona\?z=pulpit"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"~', $crawler->filter('[data-widget="attention"]')->html(), $token);
        self::assertNotEmpty($token[1] ?? null);
        $this->client->request('POST', \sprintf('/faktury/%d/oplacona?z=pulpit', $invoice->getId()), ['_token' => $token[1] ?? '']);
        self::assertResponseRedirects('/');

        $em = $this->service(EntityManagerInterface::class);
        $em->clear();
        self::assertTrue($em->find(Invoice::class, $invoice->getId())?->isPaid());
    }

    public function testMarkAllInvoicesRead(): void
    {
        $this->useFarm();
        InvoiceFactory::createMany(3, ['kind' => InvoiceKind::Cost]);
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/');
        preg_match('~przeczytane\?z=pulpit"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"~', $crawler->filter('[data-widget="attention"]')->html(), $token);
        $this->client->request('POST', '/faktury/przeczytane?z=pulpit', ['_token' => $token[1] ?? '']);
        self::assertResponseRedirects('/');

        $em = $this->service(EntityManagerInterface::class);
        self::assertSame(0, $em->getRepository(Invoice::class)->count(['readAt' => null]));
    }

    public function testBsPaySettings(): void
    {
        $this->loginAs(UserFactory::owner());
        $this->client->request('GET', '/ustawienia/bs-pay');
        $this->client->submitForm('Zapisz', [
            'bs_pay_settings[bspay__enabled]' => true,
            'bs_pay_settings[bspay__bank_url]' => 'https://e-bank.example.pl/przelew',
            'bs_pay_settings[bspay__title]' => 'Faktura {numer}',
        ]);
        self::assertResponseRedirects('/ustawienia/bs-pay');

        $settings = $this->service(Settings::class);
        self::assertTrue($settings->bool(SettingKey::BSPAY_ENABLED));
        self::assertSame('https://e-bank.example.pl/przelew', $settings->string(SettingKey::BSPAY_BANK_URL));
    }

    public function testUserscriptForTampermonkey(): void
    {
        $this->loginAs(UserFactory::owner());
        $this->client->request('GET', '/bs-pay/folwark-bs-pay.user.js');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/javascript; charset=UTF-8');

        $script = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith('// ==UserScript==', $script);
        self::assertStringContainsString('// @match        http://localhost/*', $script);
        self::assertStringContainsString('// @match        https://i24.bsbydgoszcz.pl/*', $script);
        self::assertStringContainsString('"numerRachunkuField"', $script);
        self::assertStringNotContainsString('{%', $script);
    }

    public function testFillInBsButtonAndJson(): void
    {
        $this->useFarm();
        $invoice = InvoiceFactory::createOne(['kind' => InvoiceKind::Cost, 'paymentDue' => new \DateTimeImmutable('-1 day')]);
        $this->service(Settings::class)->set(SettingKey::BSPAY_ENABLED, true);
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', \sprintf('/faktury/%d', $invoice->getId()));
        $button = $crawler->filter('button[data-controller="bspay-send"]');
        self::assertCount(1, $button);
        self::assertSame(\sprintf('/faktury/%d/przelew.json', $invoice->getId()), $button->attr('data-bspay-send-url-value'));

        $crawler = $this->client->request('GET', '/');
        self::assertStringContainsString('data-controller="bspay-send"', $crawler->filter('[data-widget="attention"]')->html());

        $this->client->request('GET', \sprintf('/faktury/%d/przelew.json', $invoice->getId()));
        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame($invoice->getNumber(), $json['invoiceNumber']);
        self::assertSame('1230.00', $json['amount']);

        $this->client->request('GET', '/bs-pay/folwark-bs-pay.user.js');
        self::assertStringContainsString('"nameWidth":32', (string) $this->client->getResponse()->getContent());
    }
}
