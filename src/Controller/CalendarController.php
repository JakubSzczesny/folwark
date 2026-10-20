<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Calendar\CalendarFeed;
use App\Calendar\CalendarOccurrences;
use App\Calendar\CalendarReminders;
use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Enum\CalendarCategory;
use App\Enum\UserRole;
use App\Farm\FarmContext;
use App\Form\CalendarEventType;
use App\Repository\ContractorRepository;
use App\Repository\FarmMembershipRepository;
use App\Security\CalendarEventVoter;
use App\Security\Permission;
use App\Ui\QueryParam;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Kalendarz gospodarstwa: widok miesiąca, wydarzenia (także powtarzane i prywatne)
 * oraz terminy z innych modułów, do których użytkownik ma dostęp.
 */
#[Route('/kalendarz')]
#[IsGranted(Permission::CALENDAR_VIEW)]
final class CalendarController extends AbstractController
{
    use ModalResponses;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $audit,
        private readonly CalendarReminders $reminders,
    ) {
    }

    #[Route('', name: 'app_calendar_index', methods: ['GET'])]
    public function index(Request $request, CalendarFeed $feed, #[CurrentUser] User $user): Response
    {
        $today = new \DateTimeImmutable('today');
        $month = self::month($request->query->getString('miesiac')) ?? $today->modify('first day of this month');
        $from = $month->modify('monday this week');
        $to = $month->modify('last day of this month')->modify('sunday this week');

        $filters = [
            'category' => CalendarCategory::tryFrom($request->query->getString('kategoria')),
            'mine' => $request->query->getBoolean('moje'),
            'deadlines' => '0' !== $request->query->getString('terminy'),
        ];
        $entries = $feed->entries($from, $to, $user, $filters['category'], $filters['mine'], $filters['deadlines']);

        $days = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $days[] = $day;
        }

        return $this->render('calendar/index.html.twig', [
            'month' => $month,
            'today' => $today,
            'weeks' => array_chunk($days, 7),
            'days' => CalendarFeed::byDay($entries, $from, $to),
            'filters' => $filters,
            'categories' => CalendarCategory::cases(),
            'source_labels' => $filters['deadlines'] ? $feed->sourceLabels() : [],
        ]);
    }

    #[Route('/nowe', name: 'app_calendar_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::CALENDAR_EDIT)]
    public function new(Request $request, #[CurrentUser] User $user, FarmMembershipRepository $memberships, FarmContext $context, ContractorRepository $contractors): Response
    {
        $day = self::day($request->query->getString('dzien')) ?? new \DateTimeImmutable('today');
        $event = new CalendarEvent($user, $day);
        if ($category = CalendarCategory::tryFrom($request->query->getString('kategoria'))) {
            $event->setCategory($category);
        }
        if ($contractorId = QueryParam::id($request->query, 'kontrahent')) {
            $event->setContractor($contractors->find($contractorId));
        }

        return $this->handleForm($event, $request, $user, $this->members($memberships, $context), 'Nowe wydarzenie');
    }

    #[Route('/{id}', name: 'app_calendar_event', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(CalendarEventVoter::VIEW, 'event')]
    public function show(CalendarEvent $event, Request $request): Response
    {
        $day = self::day($request->query->getString('dzien'));
        $occurrence = null !== $day && [] !== CalendarOccurrences::starts($event, $day, $day) ? $day : null;

        return $this->render('calendar/show.html.twig', [
            'event' => $event,
            'occurrence' => $occurrence ?? CalendarOccurrences::next($event, new \DateTimeImmutable('today')) ?? $event->getStartDate(),
            'modal' => $this->isModal($request),
        ]);
    }

    #[Route('/{id}/edycja', name: 'app_calendar_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(CalendarEventVoter::EDIT, 'event')]
    public function edit(CalendarEvent $event, Request $request, #[CurrentUser] User $user, FarmMembershipRepository $memberships, FarmContext $context): Response
    {
        return $this->handleForm($event, $request, $user, $this->members($memberships, $context), 'Edycja wydarzenia');
    }

    #[Route('/{id}/duplikuj', name: 'app_calendar_duplicate', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(CalendarEventVoter::VIEW, 'event')]
    #[IsGranted(Permission::CALENDAR_EDIT)]
    public function duplicate(CalendarEvent $event, Request $request, #[CurrentUser] User $user, FarmMembershipRepository $memberships, FarmContext $context): Response
    {
        $copy = $event->duplicate($user);
        if ($day = self::day($request->query->getString('dzien'))) {
            $copy->setStartDate($day)->setEndDate(null !== $event->getEndDate() ? $day->modify(\sprintf('+%d days', $event->getSpanDays())) : null);
        }

        return $this->handleForm($copy, $request, $user, $this->members($memberships, $context), 'Kopia wydarzenia');
    }

    #[Route('/{id}/usun', name: 'app_calendar_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(CalendarEventVoter::EDIT, 'event')]
    public function delete(CalendarEvent $event, Request $request): Response
    {
        $this->assertToken($request);
        $month = $event->getStartDate()?->format('Y-m');
        $this->audit->log('CalendarEvent', $event->getId(), 'delete', ['title' => $event->getTitle(), 'date' => $event->getStartDate()?->format('Y-m-d')]);
        $this->em->remove($event);
        $this->em->flush();
        $this->addFlash('success', \sprintf('Usunięto wydarzenie „%s”.', $event->getTitle()));

        return $this->visit($request, $this->generateUrl('app_calendar_index', ['miesiac' => $request->query->getString('miesiac') ?: $month]));
    }

    /**
     * Przeciągnięcie na inny dzień: przesuwa wydarzenie (powtarzane – całą serię) o tyle samo dni.
     */
    #[Route('/{id}/przesun', name: 'app_calendar_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(CalendarEventVoter::EDIT, 'event')]
    public function move(CalendarEvent $event, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('calendar', $request->headers->get('X-CSRF-Token', ''))) {
            throw $this->createAccessDeniedException();
        }

        $payload = $request->toArray();
        $from = self::day((string) ($payload['from'] ?? ''));
        $to = self::day((string) ($payload['to'] ?? ''));
        $start = $event->getStartDate();
        if (null === $from || null === $to || null === $start) {
            return new JsonResponse(['error' => 'Nieprawidłowa data.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $shift = static fn (?\DateTimeImmutable $date): ?\DateTimeImmutable => $date?->modify(\sprintf('%+d days', (int) $from->diff($to)->format('%r%a')));
        $event->setStartDate($shift($start))
            ->setEndDate($shift($event->getEndDate()))
            ->setRecurrenceUntil($shift($event->getRecurrenceUntil()));
        $this->audit->log('CalendarEvent', $event->getId(), 'move', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);
        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * @param list<User> $members
     */
    private function handleForm(CalendarEvent $event, Request $request, User $user, array $members, string $title): Response
    {
        $isNew = null === $event->getId();
        $before = $event->getParticipants()->toArray();
        $form = $this->createForm(CalendarEventType::class, $event, ['members' => $members]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$event->isRecurring()) {
                $event->setRecurrenceUntil(null);
            }
            if (null !== $event->getEndDate() && $event->getEndDate() == $event->getStartDate()) {
                $event->setEndDate(null);
            }
            if ($event->isPrivate() && !$event->isCreatedBy($user)) {
                // Prywatne może być tylko wydarzenie samego autora – inaczej autor straciłby do niego dostęp.
                $event->setPrivate(false);
            }

            $this->em->persist($event);
            $this->em->flush();
            $this->audit->log('CalendarEvent', $event->getId(), $isNew ? 'create' : 'update', ['title' => $event->getTitle(), 'date' => $event->getStartDate()?->format('Y-m-d')]);
            $this->em->flush();

            $added = array_values(array_filter($event->getParticipants()->toArray(), static fn (User $u): bool => !\in_array($u, $before, true)));
            $this->reminders->notifyAdded($event, $added, $user);
            $this->addFlash('success', $isNew ? 'Dodano wydarzenie.' : 'Zapisano wydarzenie.');

            // Dodane z karty kontrahenta – wracamy na jego zakładkę „Kalendarz”.
            $contractor = $event->getContractor();
            if (null !== $contractor && $request->query->has('kontrahent') && $this->isGranted(Permission::CONTRACTOR_VIEW)) {
                return $this->visit($request, $this->generateUrl('app_contractor_show', ['id' => $contractor->getId(), 'zakladka' => 'kalendarz']));
            }

            return $this->visit($request, $this->generateUrl('app_calendar_index', ['miesiac' => $event->getStartDate()?->format('Y-m')]));
        }

        return $this->render('calendar/form.html.twig', [
            'form' => $form,
            'event' => $event,
            'title' => $title,
            'modal' => $this->isModal($request),
        ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * @return list<User>
     */
    private function members(FarmMembershipRepository $memberships, FarmContext $context): array
    {
        $farm = $context->getFarm() ?? throw $this->createNotFoundException();

        return $memberships->findActiveUsers($farm, UserRole::cases());
    }

    private function isModal(Request $request): bool
    {
        return 'modal' === $request->headers->get('Turbo-Frame');
    }

    private function assertToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('calendar', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private static function month(string $value): ?\DateTimeImmutable
    {
        $date = '' !== $value ? \DateTimeImmutable::createFromFormat('!Y-m', $value) : false;

        return false !== $date && $date->format('Y-m') === $value ? $date : null;
    }

    private static function day(string $value): ?\DateTimeImmutable
    {
        $date = '' !== $value ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
