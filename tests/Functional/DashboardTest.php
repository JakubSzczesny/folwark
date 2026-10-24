<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\UserFactory;

final class DashboardTest extends FunctionalTestCase
{
    public function testDefaultLayoutAddMoveRemoveAndReset(): void
    {
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/?edycja=1');
        self::assertSame(['attention'], $this->widgets($crawler));

        foreach (['tiles', 'chart'] as $key) {
            $crawler = $this->client->submit($crawler->filter(\sprintf('.dashboard-panel form[action="/pulpit/widzety/%s/dodaj"]', $key))->form());
            self::assertResponseRedirects('/?edycja=1');
            $crawler = $this->client->followRedirect();
        }
        self::assertSame(['attention', 'tiles', 'chart'], $this->widgets($crawler));

        $crawler = $this->client->submit($crawler->filter('form[action="/pulpit/widzety/chart/przesun/w-gore"]')->form());
        $crawler = $this->client->followRedirect();
        self::assertSame(['attention', 'chart', 'tiles'], $this->widgets($crawler));

        $crawler = $this->client->submit($crawler->filter('[data-widget="attention"] form[action="/pulpit/widzety/attention/usun"]')->form());
        $crawler = $this->client->followRedirect();
        self::assertSame(['chart', 'tiles'], $this->widgets($crawler));

        $this->client->submit($crawler->filter('form[action="/pulpit/widzety/przywroc"]')->form());
        $crawler = $this->client->followRedirect();
        self::assertSame(['attention'], $this->widgets($crawler));
    }

    public function testWorkerCannotAddWidgetWithoutPermission(): void
    {
        $this->loginAs(UserFactory::worker());

        $crawler = $this->client->request('GET', '/?edycja=1');
        self::assertCount(0, $crawler->filter('form[action="/pulpit/widzety/invoices/dodaj"]'), 'Pracownik nie widzi faktur');

        $token = $crawler->filter('form[action="/pulpit/widzety/tiles/dodaj"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/pulpit/widzety/invoices/dodaj', ['_token' => $token]);
        $crawler = $this->client->followRedirect();
        self::assertSame(['attention'], $this->widgets($crawler));
    }

    public function testRegularVisitShowsSkeletonUntilWidgetsLoad(): void
    {
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-widget]'), 'Widżety doczytują się po wyświetleniu strony');
        self::assertGreaterThan(0, $crawler->filter('main .skeleton')->count());
    }

    /**
     * @return list<string>
     */
    private function widgets(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        return array_values($crawler->filter('[data-widget]')->each(static fn ($node): string => (string) $node->attr('data-widget')));
    }
}
