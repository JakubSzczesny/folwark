<?php

declare(strict_types=1);

namespace App\Note;

use App\Entity\Contractor;
use App\Entity\Invoice;
use App\Entity\Machine;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Obiekty, przy których prowadzi się notatki, z uprawnieniami i adresem powrotu.
 * write – dodawanie i edycja własnych; manage – edycja i usuwanie cudzych.
 */
final class NoteSubjects
{
    public const array TYPES = [
        'contractor' => [
            'class' => Contractor::class,
            'view' => Permission::CONTRACTOR_VIEW,
            'write' => Permission::CONTRACTOR_EDIT,
            'manage' => Permission::CONTRACTOR_EDIT,
            'route' => 'app_contractor_show',
            'params' => ['zakladka' => 'notatki'],
        ],
        'machine' => [
            'class' => Machine::class,
            'view' => Permission::MACHINE_VIEW,
            'write' => Permission::MACHINE_EDIT,
            'manage' => Permission::MACHINE_MANAGE,
            'route' => 'app_machine_notes',
            'params' => [],
        ],
        'invoice' => [
            'class' => Invoice::class,
            'view' => Permission::INVOICE_VIEW,
            'write' => Permission::INVOICE_MANAGE,
            'manage' => Permission::INVOICE_MANAGE,
            'route' => 'app_invoice_show',
            'params' => [],
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * @param 'view'|'write'|'manage' $level
     */
    public static function permission(string $type, string $level): string
    {
        $definition = self::TYPES[$type] ?? throw new \InvalidArgumentException(\sprintf('Nieznany rodzaj notatek „%s”.', $type));

        return $definition[$level];
    }

    public static function typeOf(object $subject): ?string
    {
        foreach (self::TYPES as $type => $definition) {
            if ($subject instanceof $definition['class']) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Obiekt w bieżącym gospodarstwie (filtr gospodarstwa) albo null.
     */
    public function find(string $type, int $id): ?object
    {
        return self::exists($type) ? $this->em->find(self::TYPES[$type]['class'], $id) : null;
    }

    public function url(string $type, int $id): string
    {
        return $this->urls->generate(self::TYPES[$type]['route'], ['id' => $id] + self::TYPES[$type]['params']);
    }
}
