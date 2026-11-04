<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Invoice;
use App\Ksef\KsefException;
use App\Ksef\Xml\InvoiceXmlParser;
use App\Storage\FileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Uzupełnia kwotę do zapłaty (obciążenia i odliczenia z sekcji Rozliczenie) w fakturach, których XML
 * pobrano przed wprowadzeniem tego pola. Czyta tylko zapisane lokalnie pliki, nie łączy się z KSeF.
 * Można uruchamiać wielokrotnie: zmienia tylko faktury, w których kwota się różni.
 */
#[AsCommand(name: 'app:faktury:rozliczenia', description: 'Uzupełnia kwotę do zapłaty (obciążenia/odliczenia) z zapisanych plików XML faktur')]
final class InvoiceSettlementCommand extends Command
{
    private const int BATCH = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceXmlParser $parser,
        private readonly FileStorage $storage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $changed = 0;
        $failed = 0;
        $lastId = 0;

        do {
            /** @var list<Invoice> $invoices */
            $invoices = $this->em->createQueryBuilder()
                ->select('i')->from(Invoice::class, 'i')
                ->where('i.xmlPath IS NOT NULL')->andWhere('i.id > :last')
                ->setParameter('last', $lastId)
                ->orderBy('i.id')->setMaxResults(self::BATCH)
                ->getQuery()->getResult();

            foreach ($invoices as $invoice) {
                $lastId = (int) $invoice->getId();
                $path = (string) $invoice->getXmlPath();
                if (!$this->storage->exists($path)) {
                    continue;
                }
                try {
                    $amountDue = $this->parser->parse($this->storage->read($path))->amountDue;
                } catch (KsefException) {
                    ++$failed;
                    continue;
                }
                if ($invoice->getAmountDueRaw() !== $amountDue) {
                    $invoice->setAmountDue($amountDue);
                    ++$changed;
                }
            }

            $this->em->flush();
            $this->em->clear();
        } while (self::BATCH === \count($invoices));

        $io->writeln(\sprintf('Kwota do zapłaty uzupełniona w %d fakturach%s.', $changed, $failed > 0 ? \sprintf(', nieczytelny XML: %d', $failed) : ''));

        return Command::SUCCESS;
    }
}
