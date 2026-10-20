<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Notification;
use App\Entity\NotificationPreference;
use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Notification\Notifier;
use App\Tests\Factory\FarmFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationTest extends FunctionalTestCase
{
    public function testDefaultRecipientsAndDeduplication(): void
    {
        $owner = UserFactory::owner();
        $accountant = UserFactory::accountant();
        UserFactory::worker();
        UserFactory::owner(FarmFactory::createOne());
        $this->useFarm();

        $notifier = $this->service(Notifier::class);
        $created = $notifier->notify(NotificationType::NewInvoices, NotificationPriority::Info, '3 nowe faktury zakupowe', dedupKey: 'sync:1');
        $again = $notifier->notify(NotificationType::NewInvoices, NotificationPriority::Info, '3 nowe faktury zakupowe', dedupKey: 'sync:1');

        self::assertSame(2, $created, 'Właściciel i księgowa tego gospodarstwa – nie właściciel innego');
        self::assertSame(0, $again);

        $repo = $this->service(EntityManagerInterface::class)->getRepository(Notification::class);
        self::assertSame(1, $repo->count(['user' => $owner]));
        self::assertSame(1, $repo->count(['user' => $accountant]));
    }

    public function testDisabledPreferenceIsRespected(): void
    {
        $owner = UserFactory::owner();
        $em = $this->service(EntityManagerInterface::class);
        $em->persist(new NotificationPreference($owner, NotificationType::PaymentDue, enabled: false));
        $em->flush();
        $this->useFarm();

        $created = $this->service(Notifier::class)->notify(NotificationType::PaymentDue, NotificationPriority::Warning, 'Termin płatności');

        self::assertSame(0, $created);
    }

    public function testBellAndOpeningMarksAsRead(): void
    {
        $owner = UserFactory::owner();
        $this->useFarm();
        $this->service(Notifier::class)->notify(NotificationType::KsefError, NotificationPriority::Urgent, 'Błąd synchronizacji KSeF', link: '/waga');

        $this->loginAs($owner);
        $crawler = $this->client->request('GET', '/powiadomienia/dzwonek');
        self::assertSelectorTextContains('turbo-frame', 'Błąd synchronizacji KSeF');

        $this->client->click($crawler->filter('a[href$="/otworz"]')->link());
        self::assertResponseRedirects('/waga');

        $this->client->request('GET', '/powiadomienia/dzwonek');
        self::assertSelectorNotExists('.dot-new');
    }

    public function testBellLinksLeaveTheDropdown(): void
    {
        $this->loginAs(UserFactory::owner());
        $crawler = $this->client->request('GET', '/');

        self::assertSame('_top', $crawler->filter('turbo-frame#notification-bell')->attr('target'), 'Kliknięcie powiadomienia ma przejść na stronę, a nie ładować jej w dzwonku');
    }
}
