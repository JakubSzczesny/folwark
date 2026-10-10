<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\UserRole;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityTest extends FunctionalTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/logowanie');
    }

    public function testLoginWithEmailAndPassword(): void
    {
        $user = UserFactory::createOne(['email' => 'jan@folwark.test', 'role' => UserRole::Owner, 'password' => $this->hash('tajne-haslo-123')]);

        $this->client->request('GET', '/logowanie');
        $this->client->submitForm('Zaloguj', ['email' => 'jan@folwark.test', 'password' => 'tajne-haslo-123']);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Pulpit');
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
        self::assertResponseRedirects('/');
    }

    public function testRoleRestrictions(): void
    {
        $this->loginAs(UserFactory::worker());
        $this->client->request('GET', '/ustawienia');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/waga');
        self::assertResponseIsSuccessful();
    }

    public function testOwnerCannotRemoveLastOwner(): void
    {
        $owner = UserFactory::owner();
        $this->loginAs($owner);

        $this->client->request('GET', '/ustawienia/uzytkownicy/'.$owner->getId());
        $this->client->submitForm('Zapisz', ['user[role]' => UserRole::Worker->value]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'co najmniej jeden aktywny właściciel');
        $this->service(EntityManagerInterface::class)->clear();
        self::assertSame(UserRole::Owner, UserFactory::repository()->find($owner->getId())?->getRole());
    }

    public function testOwnerCreatesUser(): void
    {
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', '/ustawienia/uzytkownicy/nowy');
        $this->client->submitForm('Zapisz', [
            'user[fullName]' => 'Maria Księgowa',
            'user[email]' => 'Maria@Folwark.test',
            'user[role]' => UserRole::Accountant->value,
            'user[plainPassword]' => 'bezpieczne-haslo',
        ]);

        self::assertResponseRedirects('/ustawienia/uzytkownicy');
        $created = UserFactory::repository()->findOneBy(['email' => 'maria@folwark.test']);
        self::assertInstanceOf(User::class, $created);
        self::assertSame(UserRole::Accountant, $created->getRole());
    }

    private function hash(string $password): string
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword(new User(), $password);
    }
}
