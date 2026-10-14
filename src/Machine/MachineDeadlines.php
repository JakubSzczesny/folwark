<?php

declare(strict_types=1);

namespace App\Machine;

use App\Dashboard\AttentionAction;
use App\Dashboard\AttentionItem;
use App\Dashboard\AttentionSource;
use App\Entity\Machine;
use App\Entity\MachineService;
use App\Enum\NotificationPriority;
use App\Enum\NotificationType;
use App\Notification\DeadlineSource;
use App\Notification\Notifier;
use App\Repository\MachineRepository;
use App\Repository\MachineServiceRepository;
use App\Security\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Twig\FormatExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Ubezpieczenia, przeglądy techniczne i zaplanowane serwisy: powiadomienia o 6:00 i pozycje na pulpicie.
 */
final class MachineDeadlines implements DeadlineSource, AttentionSource
{
    private const array DEADLINES = [
        'insurance' => ['field' => 'insuranceUntil', 'label' => 'Ubezpieczenie', 'type' => NotificationType::MachineInsurance, 'setting' => SettingKey::NOTIFY_INSURANCE_DAYS],
        'inspection' => ['field' => 'inspectionUntil', 'label' => 'Przegląd techniczny', 'type' => NotificationType::MachineInspection, 'setting' => SettingKey::NOTIFY_INSPECTION_DAYS],
    ];

    public function __construct(
        private readonly MachineRepository $machines,
        private readonly MachineServiceRepository $services,
        private readonly Notifier $notifier,
        private readonly Settings $settings,
        private readonly UrlGeneratorInterface $urls,
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    public function generate(\DateTimeImmutable $today): void
    {
        foreach (self::DEADLINES as $key => $deadline) {
            $thresholds = $this->settings->intList($deadline['setting']);
            if ([] === $thresholds) {
                continue;
            }

            foreach ($this->machines->findDueBefore($deadline['field'], $today->modify(\sprintf('+%d days', max($thresholds)))) as $machine) {
                $date = self::date($machine, $deadline['field']);
                $days = FormatExtension::daysLeft($date, $today) ?? 0;
                $stage = self::stage($days, $thresholds);

                $this->notifier->notify(
                    $deadline['type'],
                    $days < 0 ? NotificationPriority::Urgent : NotificationPriority::Warning,
                    \sprintf('%s: %s %s', $machine->getLabel(), mb_strtolower($deadline['label']), self::when($days)),
                    \sprintf('%s do %s', $deadline['label'], FormatExtension::date($date)),
                    $this->urls->generate('app_machine_show', ['id' => $machine->getId()]),
                    \sprintf('machine:%d:%s:%s:%s', $machine->getId(), $key, $date->format('Ymd'), $stage),
                );
            }
        }

        $before = $this->settings->int(SettingKey::NOTIFY_SERVICE_DAYS);
        foreach ($this->services->findOpenPlans(until: $today->modify(\sprintf('+%d days', $before))) as $plan) {
            $date = $plan->getNextServiceDate();
            if (null === $date) {
                continue;
            }
            $days = FormatExtension::daysLeft($date, $today) ?? 0;
            $machine = $plan->getMachine();

            $this->notifier->notify(
                NotificationType::MachineService,
                $days < 0 ? NotificationPriority::Urgent : NotificationPriority::Warning,
                \sprintf('%s: %s %s', $machine->getLabel(), mb_strtolower($plan->getType()?->getName() ?? 'serwis'), self::when($days)),
                \sprintf('Zaplanowany na %s', FormatExtension::date($date)),
                $this->urls->generate('app_machine_service', ['id' => $machine->getId()]),
                \sprintf('service:%d:%s', $plan->getId(), $days < 0 ? 'overdue' : 'before'),
            );
        }
    }

    public function permission(): string
    {
        return Permission::MACHINE_VIEW;
    }

    public function items(\DateTimeImmutable $today): array
    {
        $horizon = $today->modify(\sprintf('+%d days', $this->settings->int(SettingKey::ATTENTION_DAYS)));
        $items = [];

        foreach (self::DEADLINES as $deadline) {
            foreach ($this->machines->findDueBefore($deadline['field'], $horizon) as $machine) {
                $date = self::date($machine, $deadline['field']);
                $days = FormatExtension::daysLeft($date, $today) ?? 0;
                $items[] = new AttentionItem(
                    $days,
                    self::tone($days),
                    $deadline['label'],
                    $machine->getLabel(),
                    $this->urls->generate('app_machine_show', ['id' => $machine->getId()]),
                    \sprintf('Do %s · %s', FormatExtension::date($date), FormatExtension::daysLabel($days)),
                    $this->auth->isGranted(Permission::MACHINE_EDIT) ? [AttentionAction::link('Zmień datę – edytuj maszynę', $this->urls->generate('app_machine_edit', ['id' => $machine->getId()]), 'pencil')] : [],
                );
            }
        }

        foreach ($this->services->findOpenPlans() as $plan) {
            $item = $this->planItem($plan, $today, $horizon);
            if (null !== $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Plan serwisu jest pilny, gdy zbliża się jego data albo licznik maszyny doszedł do zaplanowanego stanu.
     */
    private function planItem(MachineService $plan, \DateTimeImmutable $today, \DateTimeImmutable $horizon): ?AttentionItem
    {
        $machine = $plan->getMachine();
        $url = $this->urls->generate('app_machine_service', ['id' => $machine->getId()]);
        $name = $plan->getType()?->getName() ?? 'Serwis';

        $meterDue = null !== $plan->getNextServiceMeter() && null !== $machine->getMeterValue()
            && (float) $machine->getMeterValue() >= (float) $plan->getNextServiceMeter();
        if ($meterDue) {
            $unit = $machine->getMeterUnit()?->label() ?? '';

            return new AttentionItem(0, 'warning', 'Serwis', $machine->getLabel().' – '.mb_strtolower($name), $url, \sprintf(
                'Licznik %s %s, serwis zaplanowany przy %s %s',
                FormatExtension::quantity($machine->getMeterValue(), 1),
                $unit,
                FormatExtension::quantity($plan->getNextServiceMeter(), 1),
                $unit,
            ), $this->serviceActions($plan));
        }

        $date = $plan->getNextServiceDate();
        if (null === $date || $date > $horizon) {
            return null;
        }
        $days = FormatExtension::daysLeft($date, $today) ?? 0;

        return new AttentionItem($days, self::tone($days), 'Serwis', $machine->getLabel().' – '.mb_strtolower($name), $url, \sprintf('Zaplanowany na %s · %s', FormatExtension::date($date), FormatExtension::daysLabel($days)), $this->serviceActions($plan));
    }

    /**
     * @return list<AttentionAction>
     */
    private function serviceActions(MachineService $plan): array
    {
        if (!$this->auth->isGranted(Permission::MACHINE_EDIT)) {
            return [];
        }

        return [
            AttentionAction::link('Wpisz wykonany serwis', $this->urls->generate('app_machine_service_new', ['id' => $plan->getMachine()->getId()]), 'wrench'),
            AttentionAction::link('Zmień plan serwisu', $this->urls->generate('app_machine_service_edit', ['id' => $plan->getId()]), 'pencil'),
        ];
    }

    /**
     * Etap przypomnienia: najmniejszy próg, w który mieści się liczba dni, albo „po terminie”.
     *
     * @param list<int> $thresholds
     */
    public static function stage(int $days, array $thresholds): string
    {
        if ($days < 0) {
            return 'overdue';
        }

        $matching = array_filter($thresholds, static fn (int $threshold): bool => $days <= $threshold);

        return [] === $matching ? 'early' : 'd'.min($matching);
    }

    private static function when(int $days): string
    {
        return match (true) {
            $days < 0 => 'po terminie',
            0 === $days => 'kończy się dziś',
            default => FormatExtension::daysLabel($days),
        };
    }

    private static function tone(int $days): string
    {
        return match (true) {
            $days < 0 => 'danger',
            $days <= 14 => 'warning',
            default => 'neutral',
        };
    }

    private static function date(Machine $machine, string $field): \DateTimeImmutable
    {
        $date = 'insuranceUntil' === $field ? $machine->getInsuranceUntil() : $machine->getInspectionUntil();

        return $date ?? throw new \LogicException('Maszyna bez terminu.');
    }
}
