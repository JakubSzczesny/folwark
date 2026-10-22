<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\AuditLogRepository;
use App\Repository\ContractorRepository;
use App\Repository\ContractRepository;
use App\Repository\InvoiceRepository;
use App\Repository\MachineRepository;
use App\Repository\ProductRepository;
use App\Repository\StockDocumentRepository;
use App\Repository\StockRepository;
use App\Tests\Factory\ContractFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\MachineFactory;
use App\Tests\Factory\UserFactory;

final class ListSortTest extends FunctionalTestCase
{
    /**
     * Każda kolumna sortowania każdej listy daje poprawne zapytanie – także jako druga kolumna
     * (podzapytania i CASE idą przez ukryte kolumny SELECT, co musi przejść przez stronicowanie).
     */
    public function testEveryListSortsByEveryColumn(): void
    {
        InvoiceFactory::createMany(2);
        ContractFactory::createOne();
        MachineFactory::createOne();
        $this->loginAs(UserFactory::owner());

        $lists = [
            '/faktury?rodzaj=zakupowe' => array_keys(InvoiceRepository::SORT),
            '/kontrahenci' => array_keys(ContractorRepository::sortFields(2026, true)),
            '/umowy' => array_keys(ContractRepository::SORT),
            '/maszyny' => array_keys(MachineRepository::SORT),
            '/towary' => array_keys(ProductRepository::SORT),
            '/magazyn/dokumenty' => array_keys(StockDocumentRepository::SORT),
            '/magazyn/stany' => array_keys(StockRepository::SORT),
            '/ustawienia/dziennik' => array_keys(AuditLogRepository::SORT),
        ];

        foreach ($lists as $url => $fields) {
            foreach ($fields as $i => $field) {
                $second = $fields[($i + 1) % \count($fields)];
                $this->client->request('GET', \sprintf('%s%ssort=%s&kierunek=desc&sort2=%s&kierunek2=asc', $url, str_contains($url, '?') ? '&' : '?', $field, $second));
                self::assertResponseIsSuccessful(\sprintf('%s sortowana po %s, potem %s', $url, $field, $second));
            }
        }
    }

    public function testListsHavePaginationAboveAndBelow(): void
    {
        InvoiceFactory::createMany(3);
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/faktury?rodzaj=zakupowe');
        self::assertSelectorCount(2, '.card span:contains("1–3 z 3")', 'Licznik i przyciski stron nad listą i pod nią');
    }
}
