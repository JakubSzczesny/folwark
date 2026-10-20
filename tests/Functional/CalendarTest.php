<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Calendar\CalendarReminders;
use App\Entity\CalendarEvent;
use App\Entity\Farm;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\CalendarCategory;
use App\Enum\CalendarRecurrence;
use App\Enum\InvoiceKind;
use App\Tests\Factory\FarmFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class CalendarTest extends FunctionalTestCase
{
    public function testWorkerAddsEventAndItShowsInMonth(): void
    {
        $this->loginAs(UserFactory::worker());

        $this->client->request('GET', '/kalendarz/nowe?dzien=2026-10-22');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Dodaj', [
            'calendar_event[title]' => 'Siew rzepaku',
            'calendar_event[category]' => CalendarCategory::Field->value,
            'calendar_event[startDate]' => '2026-10-22',
            'calendar_event[startTime]' => '08:00',
            'calendar_event[endTime]' => '14:30',
            'calendar_event[location]' => 'Pole za stodołą',
            'calendar_event[reminderDays]' => '1',
        ]);
        self::assertResponseRedirects('/kalendarz?miesiac=2026-10');

        $event = $this->events()->findOneBy(['title' => 'Siew rzepaku']);
        self::assertNotNull($event);
        self::assertSame('8:00–14:30', $event->getTimeLabel());
        self::assertSame(1, $event->getReminderDays());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.calendar-grid [data-date="2026-10-22"]', 'Siew rzepaku');

        $this->client->request('GET', '/kalendarz/'.$event->getId());
        self::assertSelectorTextContains('main', 'Pole za stodołą');
        self::assertSelectorTextContains('main', '1 dzień wcześniej');

        foreach (['/kalendarz/'.$event->getId(), \sprintf('/kalendarz/%d/edycja', $event->getId()), '/kalendarz/nowe'] as $url) {
            $this->client->request('GET', $url, [], [], ['HTTP_TURBO_FRAME' => 'modal']);
            self::assertResponseIsSuccessful($url);
            self::assertSelectorExists('turbo-frame#modal', 'W oknie tylko ramka, bez układu strony');
            self::assertSelectorNotExists('.app-sidebar');
        }
    }

    public function testInvalidTimesAreRejected(): void
    {
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/kalendarz/nowe?dzien=2026-10-22');
        $this->client->submitForm('Dodaj', [
            'calendar_event[title]' => 'Spotkanie',
            'calendar_event[startDate]' => '2026-10-22',
            'calendar_event[startTime]' => '10:00',
            'calendar_event[endTime]' => '09:00',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'Godzina zakończenia nie może być przed godziną rozpoczęcia');
    }

    public function testPermissionsOnOtherPeoplesEvents(): void
    {
        $owner = UserFactory::owner();
        $worker = UserFactory::worker();
        $colleague = UserFactory::worker();
        $ownerEvent = $this->event('Przegląd opryskiwacza', '2026-10-05', $owner);
        $workerEvent = $this->event('Szczepienie cieląt', '2026-10-06', $worker);
        $private = $this->event('Lekarz', '2026-10-07', $worker, private: true);

        $this->loginAs($colleague);
        $this->client->request('GET', '/kalendarz/'.$ownerEvent->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href$="/edycja"]');
        $this->client->request('GET', \sprintf('/kalendarz/%d/edycja', $ownerEvent->getId()));
        self::assertResponseStatusCodeSame(403, 'Pracownik nie edytuje cudzych wydarzeń');
        $this->client->request('GET', '/kalendarz/'.$private->getId());
        self::assertResponseStatusCodeSame(403, 'Cudze prywatne wydarzenie jest niewidoczne');
        $this->client->request('GET', '/kalendarz?miesiac=2026-10');
        self::assertSelectorTextContains('.calendar-grid', 'Szczepienie cieląt');
        self::assertSelectorTextNotContains('.calendar-grid', 'Lekarz');

        $this->loginAs($owner);
        $this->client->request('GET', \sprintf('/kalendarz/%d/edycja', $workerEvent->getId()));
        self::assertResponseIsSuccessful('Właściciel zarządza wydarzeniami innych');
        $this->client->request('GET', \sprintf('/kalendarz/%d/edycja', $private->getId()));
        self::assertResponseStatusCodeSame(403, 'Prywatne wydarzenie edytuje tylko autor – także nie właściciel');

        $this->loginAs($worker);
        $this->client->request('GET', '/kalendarz?miesiac=2026-10');
        self::assertSelectorTextContains('.calendar-grid', 'Lekarz');
    }

    public function testRecurringEventMoveAndDelete(): void
    {
        $owner = UserFactory::owner();
        $event = $this->event('Odbiór mleka', '2026-10-01', $owner, CalendarRecurrence::Weekly);

        $this->loginAs($owner);
        $crawler = $this->client->request('GET', '/kalendarz?miesiac=2026-10');
        self::assertCount(5, $crawler->filter('.calendar-grid .cal-chip:contains("Odbiór mleka")'), '1, 8, 15, 22 i 29 października');

        $token = $crawler->filter('[data-calendar-token-value]')->attr('data-calendar-token-value');
        $this->client->request('POST', \sprintf('/kalendarz/%d/przesun', $event->getId()), [], [], ['HTTP_X-CSRF-Token' => $token, 'CONTENT_TYPE' => 'application/json'], (string) json_encode(['from' => '2026-10-15', 'to' => '2026-10-16']));
        self::assertResponseIsSuccessful();
        $this->service(EntityManagerInterface::class)->clear();
        self::assertSame('2026-10-02', $this->events()->find($event->getId())?->getStartDate()?->format('Y-m-d'), 'Przeniesienie jednego terminu przesuwa całą serię');

        $crawler = $this->client->request('GET', '/kalendarz/'.$event->getId());
        $this->client->submit($crawler->filter('form[action*="/usun"]')->form());
        self::assertResponseRedirects();
        self::assertNull($this->events()->find($event->getId()));
    }

    public function testDeadlinesFromModulesFollowPermissions(): void
    {
        $owner = UserFactory::owner();
        $worker = UserFactory::worker();
        InvoiceFactory::createOne(['kind' => InvoiceKind::Cost, 'contractorName' => 'Agro-Hurt', 'paymentDue' => new \DateTimeImmutable('2026-10-14')]);

        $this->loginAs($owner);
        $this->client->request('GET', '/kalendarz?miesiac=2026-10');
        self::assertSelectorTextContains('.calendar-grid [data-date="2026-10-14"]', 'Płatność: Agro-Hurt');
        $this->client->request('GET', '/kalendarz?miesiac=2026-10&terminy=0');
        self::assertSelectorTextNotContains('.calendar-grid', 'Płatność: Agro-Hurt');

        $this->loginAs($worker);
        $this->client->request('GET', '/kalendarz?miesiac=2026-10');
        self::assertSelectorTextNotContains('.calendar-grid', 'Płatność: Agro-Hurt', 'Pracownik nie widzi faktur, więc i ich terminów');
    }

    public function testRemindersGoToParticipants(): void
    {
        $owner = UserFactory::owner();
        $worker = UserFactory::worker();
        $accountant = UserFactory::accountant();
        $event = $this->event('Kontrola ARiMR', '2026-10-23', $owner);
        $event->setReminderDays(2)->addParticipant($worker);
        $this->service(EntityManagerInterface::class)->flush();

        $this->useFarm();
        $reminders = $this->service(CalendarReminders::class);
        $reminders->generate(new \DateTimeImmutable('2026-10-20'));
        self::assertSame(0, $this->notifications($worker), 'Za wcześnie – przypomnienie 2 dni przed');

        $reminders->generate(new \DateTimeImmutable('2026-10-21'));
        $reminders->generate(new \DateTimeImmutable('2026-10-22'));
        self::assertSame(1, $this->notifications($worker), 'Jedno przypomnienie na termin');
        self::assertSame(0, $this->notifications($accountant), 'Tylko uczestnicy');
    }

    public function testOtherFarmEventsAreInvisible(): void
    {
        $other = FarmFactory::createOne();
        $event = $this->event('Zebranie spółdzielni', '2026-10-09', UserFactory::owner($other), farm: $other);

        $this->loginAs(UserFactory::owner());
        $this->client->request('GET', '/kalendarz/'.$event->getId());
        self::assertResponseStatusCodeSame(404);
    }

    private function event(string $title, string $date, User $author, CalendarRecurrence $recurrence = CalendarRecurrence::None, bool $private = false, ?Farm $farm = null): CalendarEvent
    {
        $em = $this->service(EntityManagerInterface::class);
        $event = (new CalendarEvent($author, new \DateTimeImmutable($date)))
            ->setTitle($title)
            ->setRecurrence($recurrence)
            ->setPrivate($private)
            ->setFarm($farm ?? FarmFactory::testFarm());
        $em->persist($event);
        $em->flush();

        return $event;
    }

    /**
     * @return EntityRepository<CalendarEvent>
     */
    private function events(): EntityRepository
    {
        return $this->service(EntityManagerInterface::class)->getRepository(CalendarEvent::class);
    }

    private function notifications(User $user): int
    {
        return $this->service(EntityManagerInterface::class)->getRepository(Notification::class)->count(['user' => $user]);
    }
}
