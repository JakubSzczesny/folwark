<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\UserRole;
use App\Tests\Factory\UserFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityTest extends FunctionalTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/logowanie');
    }

    public function testLoginLeadsToFarmPicker(): void
    {
        $user = UserFactory::member(UserRole::Owner, null, ['email' => 'jan@folwark.test', 'password' => $this->hash('tajne-haslo-123')]);

        $this->client->request('GET', '/logowanie');
        $this->client->submitForm('Zaloguj', ['email' => 'jan@folwark.test', 'password' => 'tajne-haslo-123']);

        self::assertResponseRedirects('/gospodarstwa');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Witaj, '.$user->getFullName());
        self::assertSelectorTextContains('main', 'Gospodarstwo testowe');
        self::assertNotNull(UserFactory::repository()->find($user->getId())?->getLastLoginAt());
    }

    public function testInactiveUserCannotLogIn(): void
    {
        UserFactory::createOne(['email' => 'off@folwark.test', 'active' => false, 'password' => $this->hash('tajne-haslo-123')]);

        $this->client->request('GET', '/logowanie');
        $this->client->submitForm('Zaloguj', ['email' => 'off@folwark.test', 'password' => 'tajne-haslo-123']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Konto jest wyłączone');
    }

    public function testPinLoginOnTrustedDevice(): void
    {
        $user = UserFactory::owner();
        $this->loginAs($user);
        $this->client->request('GET', '/konto');
        $this->client->submitForm('Zaufaj temu urządzeniu', [
            'trust_device[name]' => 'Telefon',
            'trust_device[pin][first]' => '4321',
            'trust_device[pin][second]' => '4321',
        ]);
        self::assertResponseRedirects('/konto');
        $cookie = $this->client->getCookieJar()->get('folwark_device');
        self::assertNotNull($cookie);

        $this->client->request('GET', '/wyloguj');
        $this->client->getCookieJar()->set($cookie);

        $crawler = $this->client->request('GET', '/logowanie');
        self::assertSelectorTextContains('h1', 'Witaj, '.$user->getFullName());

        $this->client->submit($crawler->selectButton('Zaloguj')->form(['pin' => '0000']));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Nieprawidłowy PIN');

        $crawler = $this->client->request('GET', '/logowanie');
        $this->client->submit($crawler->selectButton('Zaloguj')->form(['pin' => '4321']));
        self::assertResponseRedirects('/gospodarstwa');
    }

    public function testRoleRestrictionsFollowFarmRole(): void
    {
        $this->loginAs(UserFactory::worker());
        $this->client->request('GET', '/ustawienia');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/ustawienia/slowniki');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/waga');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'RHEWA 82c');
    }

    public function testRegistrationCreatesAccountWithoutFarm(): void
    {
        $this->client->request('GET', '/rejestracja');
        $this->client->submitForm('Załóż konto', [
            'registration[fullName]' => 'Maria Nowa',
            'registration[email]' => 'Maria@Folwark.test',
            'registration[plainPassword][first]' => 'bezpieczne-haslo',
            'registration[plainPassword][second]' => 'bezpieczne-haslo',
        ]);

        self::assertResponseRedirects('/gospodarstwa');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Nie masz jeszcze dostępu do żadnego gospodarstwa');
        self::assertSelectorTextContains('main', 'maria@folwark.test');

        $this->client->request('GET', '/faktury');
        self::assertResponseRedirects('/gospodarstwa', null, 'Bez gospodarstwa strony modułów prowadzą do wyboru gospodarstwa');
    }

    private function hash(string $password): string
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword(new User(), $password);
    }
}
