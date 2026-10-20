<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Note;
use App\Entity\User;
use App\Machine\MachineManager;
use App\Tests\Factory\ContractorFactory;
use App\Tests\Factory\FarmFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\MachineFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class NoteTest extends FunctionalTestCase
{
    public function testNotesAreListedNewestFirstAndEditableByAuthor(): void
    {
        $machine = MachineFactory::createOne(['name' => 'John Deere 6R']);
        $worker = UserFactory::worker();
        $this->loginAs($worker);

        $crawler = $this->client->request('GET', \sprintf('/maszyny/%d/notatki', $machine->getId()));
        $this->client->submit($crawler->selectButton('Dodaj notatkę')->form(['body' => 'Wymienić pasek klinowy']));
        self::assertResponseRedirects(\sprintf('/maszyny/%d/notatki', $machine->getId()));
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Dodaj notatkę')->form(['body' => 'Olej 15W-40, 18 l']));
        $crawler = $this->client->followRedirect();

        $bodies = $crawler->filter('.note p')->each(static fn ($node): string => $node->text());
        self::assertSame(['Olej 15W-40, 18 l', 'Wymienić pasek klinowy'], $bodies, 'Od najnowszej');
        self::assertSelectorTextContains('.tabs', 'Notatki 2');
        self::assertSelectorTextContains('.note', $worker->getFullName());

        $note = $this->note('Wymienić pasek klinowy');
        $crawler = $this->client->request('GET', \sprintf('/notatki/%d/edycja', $note->getId()));
        $this->client->submit($crawler->selectButton('Zapisz')->form(['body' => 'Wymienić pasek klinowy (zamówiony)']));
        self::assertResponseRedirects(\sprintf('/maszyny/%d/notatki', $machine->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Wymienić pasek klinowy (zamówiony)');
        self::assertSelectorTextContains('main', '(edytowano)');
    }

    public function testOnlyAuthorOrManagerEditsAndDeletes(): void
    {
        $machine = MachineFactory::createOne();
        $author = UserFactory::worker();
        $colleague = UserFactory::worker();
        $note = $this->addNote('machine', (int) $machine->getId(), 'Sprawdzić hamulce', $author);

        $this->loginAs($colleague);
        $this->client->request('GET', \sprintf('/notatki/%d/edycja', $note->getId()));
        self::assertResponseStatusCodeSame(403, 'Cudzej notatki pracownik nie edytuje');
        $crawler = $this->client->request('GET', \sprintf('/maszyny/%d/notatki', $machine->getId()));
        self::assertCount(0, $crawler->filter('.note form[action$="/usun"]'));

        $this->loginAs(UserFactory::owner());
        $crawler = $this->client->request('GET', \sprintf('/maszyny/%d/notatki', $machine->getId()));
        $this->client->submit($crawler->filter('.note .ml-auto form[action$="/usun"]')->form());
        self::assertResponseRedirects(\sprintf('/maszyny/%d/notatki', $machine->getId()));
        self::assertNull($this->service(EntityManagerInterface::class)->getRepository(Note::class)->find($note->getId()), 'Właściciel usuwa każdą notatkę');
    }

    public function testContractorAndInvoiceNotesWithPermissions(): void
    {
        $contractor = ContractorFactory::createOne(['name' => 'Agro-Hurt']);
        $invoice = InvoiceFactory::createOne(['number' => 'FV/9/2026']);
        $this->addNote('invoice', (int) $invoice->getId(), 'Reklamacja – brak 2 worków', UserFactory::owner());

        $this->loginAs(UserFactory::accountant());
        $this->client->request('GET', '/faktury/'.$invoice->getId());
        self::assertSelectorTextContains('main', 'Reklamacja – brak 2 worków');
        self::assertSelectorNotExists('form[action="/notatki/invoice/'.$invoice->getId().'"]', 'Księgowa tylko czyta');
        $this->client->request('POST', '/notatki/invoice/'.$invoice->getId(), ['body' => 'x']);
        self::assertResponseStatusCodeSame(403);

        $this->loginAs(UserFactory::owner());
        $crawler = $this->client->request('GET', \sprintf('/kontrahenci/%d?zakladka=notatki', $contractor->getId()));
        $this->client->submit($crawler->selectButton('Dodaj notatkę')->form(['body' => 'Rabat 3% przy płatności w 7 dni']));
        self::assertResponseRedirects(\sprintf('/kontrahenci/%d?zakladka=notatki', $contractor->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.note', 'Rabat 3% przy płatności w 7 dni');
    }

    public function testDeletingMachineRemovesItsNotesAndOtherFarmNotesAreHidden(): void
    {
        $machine = MachineFactory::createOne();
        $this->addNote('machine', (int) $machine->getId(), 'Do usunięcia', UserFactory::owner());
        $this->useFarm();
        $this->service(MachineManager::class)->delete($machine);
        self::assertSame(0, $this->service(EntityManagerInterface::class)->getRepository(Note::class)->count(['subjectType' => 'machine']));

        $other = FarmFactory::createOne();
        $contractor = ContractorFactory::createOne(['farm' => $other]);
        $this->loginAs(UserFactory::owner());
        $this->client->request('POST', '/notatki/contractor/'.$contractor->getId(), ['body' => 'Cudze', '_token' => 'x']);
        self::assertResponseStatusCodeSame(403, 'Zły token albo obce gospodarstwo – nic nie powstaje');
        self::assertSame(0, $this->service(EntityManagerInterface::class)->getRepository(Note::class)->count(['subjectType' => 'contractor']));
    }

    private function addNote(string $type, int $id, string $body, User $author): Note
    {
        $em = $this->service(EntityManagerInterface::class);
        $note = (new Note($type, $id, $body, $author))->setFarm(FarmFactory::testFarm());
        $em->persist($note);
        $em->flush();

        return $note;
    }

    private function note(string $body): Note
    {
        $note = $this->service(EntityManagerInterface::class)->getRepository(Note::class)->findOneBy(['body' => $body]);
        self::assertNotNull($note);

        return $note;
    }
}
