<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Audit\AuditLogger;
use App\Entity\Farm;
use App\Farm\FarmContext;
use App\Form\FarmType;
use App\Form\Settings\NotificationSettingsType;
use App\Form\Settings\OrderSettingsType;
use App\Form\Settings\WarehouseSettingsType;
use App\Message\RecalculateOrders;
use App\Repository\AuditLogRepository;
use App\Security\Permission;
use App\Settings\SettingsForm;
use App\Ui\Pager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ustawienia')]
#[IsGranted(Permission::SETTINGS)]
final class SettingsController extends AbstractController
{
    private const array SECTIONS = [
        ['route' => 'app_settings_farm', 'title' => 'Gospodarstwo', 'text' => 'Nazwa i NIP gospodarstwa.'],
        ['route' => 'app_settings_members', 'title' => 'Członkowie gospodarstwa', 'text' => 'Kto ma dostęp i z jaką rolą.'],
        ['route' => 'app_settings_modules', 'title' => 'Moduły', 'text' => 'Włączanie i wyłączanie modułów z menu (bez usuwania danych).'],
        ['route' => 'app_settings_ksef', 'title' => 'KSeF', 'text' => 'Token, środowisko, synchronizacja faktur, Biała lista VAT.'],
        ['route' => 'app_settings_orders', 'title' => 'Zamówienia', 'text' => 'Wyprzedzenie przypomnień, progi przewidywania.'],
        ['route' => 'app_settings_notifications', 'title' => 'Powiadomienia', 'text' => 'Progi dni, dzienny skrót e-mail.'],
        ['route' => 'app_settings_warehouse', 'title' => 'Magazyn', 'text' => 'Numeracja dokumentów, domyślne „Utwórz też PZ”.'],
        ['route' => 'app_settings_bspay', 'title' => 'BS Pay', 'text' => 'Przelewy za faktury w bankowości BS Bydgoszcz.'],
        ['route' => 'app_settings_rules', 'title' => 'Reguły faktur', 'text' => 'Automatyczne „tylko koszt” dla wybranych dostawców.'],
        ['route' => 'admin', 'title' => 'Słowniki', 'text' => 'Kategorie, jednostki, magazyny, ośrodki kosztów, pola, rodzaje serwisu…'],
        ['route' => 'app_settings_audit', 'title' => 'Dziennik zdarzeń', 'text' => 'Kto, co i kiedy zmienił.'],
        ['route' => 'app_settings_danger', 'title' => 'Strefa niebezpieczna', 'text' => 'Wyczyszczenie wszystkich danych albo usunięcie gospodarstwa.', 'danger' => true],
    ];

    public function __construct(
        private readonly SettingsForm $settingsForm,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'app_settings', methods: ['GET'])]
    public function index(): Response
    {
        $sections = [];
        foreach (self::SECTIONS as $section) {
            try {
                $sections[] = $section + ['url' => $this->generateUrl($section['route'])];
            } catch (RouteNotFoundException) {
            }
        }

        return $this->render('settings/index.html.twig', ['sections' => $sections]);
    }

    #[Route('/gospodarstwo', name: 'app_settings_farm', methods: ['GET', 'POST'])]
    public function farm(Request $request, FarmContext $context, AuditLogger $audit): Response
    {
        $farm = $this->em->find(Farm::class, $context->farmId()) ?? throw $this->createNotFoundException();
        $before = ['name' => $farm->getName(), 'nip' => $farm->getNip()];
        $form = $this->createForm(FarmType::class, $farm);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $audit->log('Farm', $farm->getId(), 'update', ['old' => $before, 'new' => ['name' => $farm->getName(), 'nip' => $farm->getNip()]]);
            $this->em->flush();
            $this->addFlash('success', 'Zapisano dane gospodarstwa.');

            return $this->redirectToRoute('app_settings_farm', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('settings/form.html.twig', ['form' => $form, 'title' => 'Gospodarstwo']);
    }

    #[Route('/powiadomienia', name: 'app_settings_notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request): Response
    {
        return $this->handleSettings($request, NotificationSettingsType::class, NotificationSettingsType::KEYS, 'Powiadomienia', 'app_settings_notifications');
    }

    #[Route('/zamowienia', name: 'app_settings_orders', methods: ['GET', 'POST'])]
    public function orders(Request $request, MessageBusInterface $bus, FarmContext $context): Response
    {
        return $this->handleSettings($request, OrderSettingsType::class, OrderSettingsType::KEYS, 'Zamówienia', 'app_settings_orders', static fn () => $bus->dispatch(new RecalculateOrders($context->farmId())));
    }

    #[Route('/magazyn', name: 'app_settings_warehouse', methods: ['GET', 'POST'])]
    public function warehouse(Request $request): Response
    {
        return $this->handleSettings($request, WarehouseSettingsType::class, WarehouseSettingsType::KEYS, 'Magazyn', 'app_settings_warehouse');
    }

    #[Route('/dziennik', name: 'app_settings_audit', methods: ['GET'])]
    public function audit(Request $request, AuditLogRepository $logs): Response
    {
        $entity = $request->query->getString('obiekt') ?: null;
        $pager = Pager::fromQuery($logs->createListQuery($entity), $request->query->getInt('strona', 1));

        return $this->render('settings/audit.html.twig', [
            'pager' => $pager,
            'entity' => $entity,
            'entities' => $logs->entityNames(),
        ]);
    }

    /**
     * @param class-string<\Symfony\Component\Form\FormTypeInterface<array<string, mixed>>> $type
     * @param list<string>                                                                  $keys
     */
    private function handleSettings(Request $request, string $type, array $keys, string $title, string $route, ?callable $afterSave = null): Response
    {
        $form = $this->createForm($type, $this->settingsForm->data($keys));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsForm->save($form, $keys);
            if (null !== $afterSave) {
                $afterSave();
            }
            $this->em->flush();
            $this->addFlash('success', 'Zapisano ustawienia.');

            return $this->redirectToRoute($route, [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('settings/form.html.twig', ['form' => $form, 'title' => $title]);
    }
}
