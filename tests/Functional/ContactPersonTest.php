<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ContactPerson;
use App\Entity\Contractor;
use App\Tests\Factory\ContractorFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class ContactPersonTest extends FunctionalTestCase
{
    public function testAddEditAndDeletePerson(): void
    {
        $contractor = ContractorFactory::createOne(['name' => 'Agro Kowalski', 'email' => null]);
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', \sprintf('/kontrahenci/%d?zakladka=osoby', $contractor->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('a.tab-active', 'Osoby');
        self::assertSelectorTextContains('[data-contacts-tab]', 'Brak osób');

        $this->client->request('GET', \sprintf('/kontrahenci/%d/osoby/nowa', $contractor->getId()));
        $this->client->submitForm('Zapisz', ['name' => 'Jan Nowak', 'position' => 'handlowiec', 'phone' => '600 123 456', 'email' => 'zly-adres']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'To nie jest poprawny adres e-mail.');

        $this->client->submitForm('Zapisz', ['name' => 'Jan Nowak', 'position' => 'handlowiec', 'phone' => '600 123 456', 'email' => 'Jan.Nowak@Gmail.com']);
        self::assertResponseRedirects(\sprintf('/kontrahenci/%d?zakladka=osoby', $contractor->getId()));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('a.tab-active', 'Osoby (1)');
        $row = $crawler->filter('[data-contact-person]');
        self::assertCount(1, $row);
        self::assertStringContainsString('Jan Nowak', $row->text());
        self::assertStringContainsString('handlowiec', $row->text());
        self::assertCount(1, $row->filter('a[href="tel:600123456"]'));
        self::assertCount(1, $row->filter('a[href="mailto:jan.nowak@gmail.com"]'));

        $em = $this->service(EntityManagerInterface::class);
        $person = $em->getRepository(ContactPerson::class)->findOneBy(['name' => 'Jan Nowak']);
        self::assertNotNull($person);

        // Adres osoby przypisuje maile do kontrahenta, a wyszukiwarka znajduje kontrahenta po osobie.
        $em->clear();
        self::assertSame(['jan.nowak@gmail.com'], $em->find(Contractor::class, $contractor->getId())?->getEmailAddresses());
        $crawler = $this->client->request('GET', '/szukaj?q=nowak', [], [], ['HTTP_X_REQUESTED_WITH' => 'fetch']);
        self::assertCount(1, $crawler->filter(\sprintf('a[href="/kontrahenci/%d"]', $contractor->getId())));

        $this->client->request('GET', \sprintf('/kontrahenci/%d/osoby/%d', $contractor->getId(), $person->getId()));
        $this->client->submitForm('Zapisz', ['name' => 'Jan Nowak', 'position' => '', 'phone' => '', 'email' => '', 'note' => 'dzwonić po 15']);
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('dzwonić po 15', $crawler->filter('[data-contact-person]')->text());
        self::assertCount(0, $crawler->filter('[data-contact-person] a[href^="tel:"]'));

        $this->client->request('GET', \sprintf('/kontrahenci/%d/osoby/%d', $contractor->getId(), $person->getId()));
        $this->client->submitForm('Usuń');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-contacts-tab]', 'Brak osób');
    }

    public function testPersonOfAnotherContractorIsNotFound(): void
    {
        $contractor = ContractorFactory::createOne();
        $other = ContractorFactory::createOne();
        $this->loginAs(UserFactory::owner());

        $this->client->request('GET', \sprintf('/kontrahenci/%d/osoby/nowa', $other->getId()));
        $this->client->submitForm('Zapisz', ['name' => 'Anna Inna']);
        $person = $this->service(EntityManagerInterface::class)->getRepository(ContactPerson::class)->findOneBy(['name' => 'Anna Inna']);
        self::assertNotNull($person);

        $this->client->request('GET', \sprintf('/kontrahenci/%d/osoby/%d', $contractor->getId(), $person->getId()));
        self::assertResponseStatusCodeSame(404);
    }

    public function testAccountantCanSeeButNotEdit(): void
    {
        $contractor = ContractorFactory::createOne();
        $this->loginAs(UserFactory::accountant());

        $this->client->request('GET', \sprintf('/kontrahenci/%d?zakladka=osoby', $contractor->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href$="/osoby/nowa"]');

        $this->client->request('GET', \sprintf('/kontrahenci/%d/osoby/nowa', $contractor->getId()));
        self::assertResponseStatusCodeSame(403);
    }
}
