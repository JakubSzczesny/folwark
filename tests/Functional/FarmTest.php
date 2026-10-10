<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Contractor;
use App\Entity\CostCategory;
use App\Entity\Farm;
use App\Entity\FarmMembership;
use App\Entity\Invoice;
use App\Entity\Unit;
use App\Entity\User;
use App\Enum\UserRole;
use App\Farm\FarmContext;
use App\Farm\FarmWiper;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Tests\Factory\ContractorFactory;
use App\Tests\Factory\FarmFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

use function Zenstruck\Foundry\Persistence\persist;

final class FarmTest extends FunctionalTestCase
{
    public function testPickerShowsUsersFarmsAndSwitchesContext(): void
    {
        $other = FarmFactory::createOne(['name' => 'Gospodarstwo Łąkowe']);
        FarmFactory::createOne(['name' => 'Cudze gospodarstwo']);
        $user = UserFactory::owner();
        persist(FarmMembership::class, ['farm' => $other, 'user' => $user, 'role' => UserRole::Worker]);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/gospodarstwa');

        self::assertSelectorTextContains('main', 'Gospodarstwo testowe');
        self::assertSelectorTextContains('main', 'Gospodarstwo Łąkowe');
        self::assertSelectorTextNotContains('main', 'Cudze gospodarstwo');
        self::assertSelectorTextContains('main', 'Nowe gospodarstwo');

        $this->client->submit($crawler->filter(\sprintf('form[action="/gospodarstwa/%d/wybierz"]', $other->getId()))->form());
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.app-topbar', 'Gospodarstwo Łąkowe');
        self::assertSelectorTextContains('.app-topbar', 'Pracownik');
    }

    public function testCannotSelectFarmWithoutMembership(): void
    {
        $foreign = FarmFactory::createOne();
        $this->loginAs(UserFactory::owner());

        $crawler = $this->client->request('GET', '/gospodarstwa');
        $token = $crawler->filter('form[action$="/wybierz"] input[name=_token]')->attr('value');
        $this->client->request('POST', '/gospodarstwa/'.$foreign->getId().'/wybierz', ['_token' => $token]);

        self::assertResponseRedirects('/gospodarstwa');
    }

    public function testDataIsIsolatedBetweenFarms(): void
    {
        $other = FarmFactory::createOne(['name' => 'Drugie gospodarstwo']);
        $mine = InvoiceFactory::createOne(['number' => 'MOJA/1']);
        $foreignContractor = ContractorFactory::createOne(['farm' => $other, 'name' => 'Obcy dostawca', 'nip' => '7311000000']);
        $foreign = InvoiceFactory::createOne(['number' => 'OBCA/1', 'farm' => $other, 'contractor' => $foreignContractor]);
        ContractorFactory::createOne(['name' => 'Mój dostawca', 'nip' => '7311000000']);

        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/faktury?rodzaj=wszystkie');
        self::assertSelectorTextContains('tbody', 'MOJA/1');
        self::assertSelectorTextNotContains('tbody', 'OBCA/1');

        $this->client->request('GET', '/faktury/'.$foreign->getId());
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/kontrahenci/'.$foreignContractor->getId());
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/szukaj?q=dostawca');
        self::assertSelectorTextContains('main', 'Mój dostawca');
        self::assertSelectorTextNotContains('main', 'Obcy dostawca');

        self::assertNotSame($mine->getId(), $foreign->getId());
    }

    public function testCreatingFarmMakesCreatorOwnerAndSeedsDictionaries(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user);

        $this->client->request('GET', '/gospodarstwa/nowe');
        $this->client->submitForm('Utwórz gospodarstwo', ['farm[name]' => 'Ferma Pod Lasem', 'farm[nip]' => '526-100-00-07']);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.app-topbar', 'Ferma Pod Lasem');

        $em = $this->service(EntityManagerInterface::class);
        $farm = $em->getRepository(Farm::class)->findOneBy(['name' => 'Ferma Pod Lasem']);
        self::assertNotNull($farm);
        self::assertSame('5261000007', $farm->getNip());
        $membership = $em->getRepository(FarmMembership::class)->findOneBy(['farm' => $farm, 'user' => $user]);
        self::assertSame(UserRole::Owner, $membership?->getRole());

        $this->useFarm($farm);
        self::assertGreaterThan(0, $em->getRepository(Unit::class)->count([]));
        self::assertGreaterThan(0, $em->getRepository(CostCategory::class)->count([]));
    }

    public function testOwnerManagesMembers(): void
    {
        $owner = UserFactory::owner();
        $existing = UserFactory::createOne(['email' => 'piotr@folwark.test']);
        $this->loginAs($owner);

        $this->client->request('GET', '/ustawienia/czlonkowie');
        $this->client->submitForm('Dodaj do gospodarstwa', ['member[email]' => 'Piotr@Folwark.test', 'member[role]' => UserRole::Worker->value]);
        self::assertResponseRedirects('/ustawienia/czlonkowie');

        $this->client->request('GET', '/ustawienia/czlonkowie');
        $this->client->submitForm('Dodaj do gospodarstwa', ['member[email]' => 'nowy@folwark.test', 'member[role]' => UserRole::Accountant->value]);
        self::assertResponseStatusCodeSame(422, 'Bez danych nowego konta nie da się go założyć');

        $this->client->submitForm('Dodaj do gospodarstwa', [
            'member[email]' => 'nowy@folwark.test',
            'member[role]' => UserRole::Accountant->value,
            'member[fullName]' => 'Nowa Księgowa',
            'member[password]' => 'haslo-startowe-1',
        ]);
        self::assertResponseRedirects('/ustawienia/czlonkowie');

        $em = $this->service(EntityManagerInterface::class);
        $farm = FarmFactory::testFarm();
        self::assertSame(UserRole::Worker, $em->getRepository(FarmMembership::class)->findOneBy(['farm' => $farm, 'user' => $existing])?->getRole());
        $created = $em->getRepository(User::class)->findOneBy(['email' => 'nowy@folwark.test']);
        self::assertNotNull($created);
        self::assertSame(UserRole::Accountant, $em->getRepository(FarmMembership::class)->findOneBy(['farm' => $farm, 'user' => $created])?->getRole());
    }

    public function testLastOwnerCannotBeDemotedOrRemoved(): void
    {
        $owner = UserFactory::owner();
        $this->loginAs($owner);

        $crawler = $this->client->request('GET', '/ustawienia/czlonkowie');
        $this->client->submit($crawler->filter('form[action$="/rola"]')->form(['role' => UserRole::Worker->value]));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'co najmniej jednego właściciela');

        $crawler = $this->client->request('GET', '/ustawienia/czlonkowie');
        $this->client->submit($crawler->filter('form[action$="/usun"]')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'jedynego właściciela');
    }

    public function testWorkerCannotManageMembers(): void
    {
        $this->loginAs(UserFactory::worker());

        $this->client->request('GET', '/ustawienia/czlonkowie');

        self::assertResponseStatusCodeSame(403);
    }

    public function testWipeClearsOnlyCurrentFarm(): void
    {
        $other = FarmFactory::createOne();
        InvoiceFactory::createMany(2);
        ContractorFactory::createOne();
        InvoiceFactory::createOne(['farm' => $other]);
        $owner = UserFactory::owner();
        $this->loginAs($owner);

        $crawler = $this->client->request('GET', '/ustawienia/strefa-niebezpieczna');
        $this->client->submit($crawler->filter('form[action$="/wyczysc"]')->form(['confirm' => 'zła nazwa']));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'nic nie zostało usunięte');

        $crawler = $this->client->request('GET', '/ustawienia/strefa-niebezpieczna');
        $this->client->submit($crawler->filter('form[action$="/wyczysc"]')->form(['confirm' => FarmFactory::TEST_NAME]));
        self::assertResponseRedirects('/');

        $em = $this->service(EntityManagerInterface::class);
        $context = $this->service(FarmContext::class);
        $context->clear();
        $testFarm = FarmFactory::testFarm();
        self::assertSame(0, $em->getRepository(Invoice::class)->count(['farm' => $testFarm]));
        self::assertSame(0, $em->getRepository(Contractor::class)->count(['farm' => $testFarm]));
        self::assertSame(1, $em->getRepository(Invoice::class)->count(['farm' => $other]));
        self::assertGreaterThan(0, $em->getRepository(Unit::class)->count(['farm' => $testFarm]), 'Słowniki startowe wracają');
        self::assertNotNull($em->getRepository(FarmMembership::class)->findOneBy(['farm' => $testFarm, 'user' => $owner]));
    }

    public function testWiperCoversEveryFarmScopedEntity(): void
    {
        $em = $this->service(EntityManagerInterface::class);
        $scoped = [];
        foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (is_subclass_of($metadata->getName(), \App\Farm\FarmScoped::class)) {
                $scoped[] = $metadata->getName();
            }
        }

        self::assertEqualsCanonicalizing($scoped, FarmWiper::WIPE_ORDER, 'Nowa encja gospodarstwa musi trafić do FarmWiper::WIPE_ORDER');
    }

    public function testDeleteFarm(): void
    {
        $owner = UserFactory::owner();
        InvoiceFactory::createOne();
        $this->loginAs($owner);

        $crawler = $this->client->request('GET', '/ustawienia/strefa-niebezpieczna');
        $this->client->submit($crawler->filter('form[action$="/usun"]')->form(['confirm' => FarmFactory::TEST_NAME]));

        self::assertResponseRedirects('/gospodarstwa');
        $em = $this->service(EntityManagerInterface::class);
        $em->clear();
        self::assertNull($em->getRepository(Farm::class)->findOneBy(['name' => FarmFactory::TEST_NAME]));
        self::assertSame(0, $em->getRepository(Invoice::class)->count([]));
        self::assertNotNull($em->find(User::class, $owner->getId()), 'Konto użytkownika zostaje');
    }

    public function testChangingKsefEnvironmentResetsDownloadProgress(): void
    {
        $owner = UserFactory::owner();
        $this->useFarm();
        $this->service(Settings::class)->set(SettingKey::KSEF_CURSORS, ['Subject2' => '2026-10-01T00:00:00+02:00']);

        $this->loginAs($owner);
        $this->client->request('GET', '/ustawienia/ksef');
        self::assertSelectorTextContains('main', 'symulator');
        $this->client->submitForm('Zapisz', ['ksef_settings[ksef__environment]' => 'prod']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-warning', 'Zmieniono środowisko');

        $this->useFarm();
        $this->service(Settings::class)->reset();
        self::assertSame([], $this->service(Settings::class)->get(SettingKey::KSEF_CURSORS));
    }
}
