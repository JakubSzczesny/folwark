<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Notification;
use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Module\Modules;
use App\Notification\Notifier;
use App\Tests\Factory\ContractorFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class ModuleTest extends FunctionalTestCase
{
    public function testDisabledModuleDisappearsAndComesBackWithData(): void
    {
        $owner = UserFactory::owner();
        $contractor = ContractorFactory::createOne(['name' => 'Petromex']);
        $this->loginAs($owner);

        $crawler = $this->client->request('GET', '/ustawienia/moduly');
        $form = $crawler->selectButton('Zapisz')->form();
        $values = $form->getPhpValues();
        $values['enabled'] = array_values(array_diff(array_keys(Modules::DEFINITIONS), ['contractors', 'machines']));
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects('/ustawienia/moduly');

        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.app-sidebar a[href="/kontrahenci"]');
        self::assertSelectorNotExists('.app-sidebar a[href="/umowy"]', 'Umowy są wyłączone razem z kontrahentami');
        self::assertSelectorNotExists('.app-sidebar a[href="/maszyny"]');
        self::assertSelectorExists('.app-sidebar a[href="/faktury"]');

        $this->client->request('GET', '/kontrahenci/'.$contractor->getId());
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.toast-warning', 'Moduł „Kontrahenci” jest wyłączony');
        $this->client->request('GET', '/umowy');
        self::assertResponseRedirects('/');
        $this->client->request('GET', '/szukaj?q=Petromex');
        self::assertSelectorNotExists('main a[href="/kontrahenci/'.$contractor->getId().'"]', 'Wyłączony moduł nie pokazuje się w wyszukiwarce');

        $this->client->request('GET', '/faktury?rodzaj=zakupowe');
        self::assertResponseIsSuccessful('Filtr kontrahenta na liście faktur działa dalej');

        $values['enabled'] = array_keys(Modules::DEFINITIONS);
        $this->client->request('POST', $form->getUri(), $values);
        $this->client->request('GET', '/kontrahenci/'.$contractor->getId());
        self::assertResponseIsSuccessful('Po włączeniu dane są na miejscu');
        self::assertSelectorTextContains('h1', 'Petromex');
    }

    public function testSidebarContextMenuDisablesModule(): void
    {
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/');
        $form = $crawler->filter('.app-sidebar form[action="/ustawienia/moduly/calendar/wylacz"]');
        self::assertCount(1, $form);
        // Formularz jest w <template> menu kontekstowego – przeglądarka go klonuje, DomCrawler nie widzi jego pól.
        $this->client->request('POST', '/ustawienia/moduly/calendar/wylacz', ['_token' => $form->filter('input[name="_token"]')->attr('value')]);
        self::assertResponseRedirects('/');

        $this->client->request('GET', '/kalendarz');
        self::assertResponseRedirects('/');
    }

    public function testOnlyOwnerManagesModules(): void
    {
        $this->loginAs(UserFactory::worker());

        $crawler = $this->client->request('GET', '/');
        self::assertCount(0, $crawler->filter('.app-sidebar template[data-context-menu]'));
        $this->client->request('GET', '/ustawienia/moduly');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDisabledModuleSendsNoNotifications(): void
    {
        $owner = UserFactory::owner();
        $this->useFarm();
        $this->service(Modules::class)->disable('machines');

        $notifier = $this->service(Notifier::class);
        self::assertSame(0, $notifier->notify(NotificationType::MachineInsurance, NotificationPriority::Warning, 'Ubezpieczenie'));
        self::assertSame(1, $notifier->notify(NotificationType::PaymentDue, NotificationPriority::Warning, 'Płatność'));

        $this->service(Modules::class)->disable('notifications');
        self::assertSame(0, $notifier->notify(NotificationType::PaymentDue, NotificationPriority::Warning, 'Inna płatność'));
        self::assertSame(1, $this->service(EntityManagerInterface::class)->getRepository(Notification::class)->count(['user' => $owner]));
    }
}
