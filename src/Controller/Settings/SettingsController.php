<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Form\Settings\FarmSettingsType;
use App\Form\Settings\NotificationSettingsType;
use App\Repository\AuditLogRepository;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsForm;
use App\Ui\Pager;
use App\Util\Nip;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

#[Route('/ustawienia')]
final class SettingsController extends AbstractController
{
    private const array SECTIONS = [
        ['route' => 'app_settings_farm', 'title' => 'Gospodarstwo', 'text' => 'Nazwa i NIP gospodarstwa.'],
        ['route' => 'app_settings_users', 'title' => 'Użytkownicy i role', 'text' => 'Konta, role, blokowanie dostępu.'],
        ['route' => 'app_settings_ksef', 'title' => 'KSeF', 'text' => 'Token, środowisko, synchronizacja faktur, Biała lista VAT.'],
        ['route' => 'app_settings_orders', 'title' => 'Zamówienia', 'text' => 'Wyprzedzenie przypomnień, progi przewidywania.'],
        ['route' => 'app_settings_notifications', 'title' => 'Powiadomienia', 'text' => 'Progi dni, dzienny skrót e-mail.'],
        ['route' => 'app_settings_warehouse', 'title' => 'Magazyn', 'text' => 'Numeracja dokumentów, domyślne „Utwórz też PZ”.'],
        ['route' => 'app_settings_rules', 'title' => 'Reguły faktur', 'text' => 'Automatyczne „tylko koszt” dla wybranych dostawców.'],
        ['route' => 'admin', 'title' => 'Słowniki', 'text' => 'Kategorie, jednostki, magazyny, ośrodki kosztów, pola, rodzaje serwisu…'],
        ['route' => 'app_settings_audit', 'title' => 'Dziennik zdarzeń', 'text' => 'Kto, co i kiedy zmienił.'],
    ];

    public function __construct(
        private readonly Settings $settings,
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
    public function farm(Request $request): Response
    {
        return $this->handleSettings($request, FarmSettingsType::class, FarmSettingsType::KEYS, 'Gospodarstwo', 'app_settings_farm', function (): void {
            $nip = $this->settings->string(SettingKey::FARM_NIP);
            $this->settings->set(SettingKey::FARM_NIP, Nip::normalize($nip), false);
        });
    }

    #[Route('/powiadomienia', name: 'app_settings_notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request): Response
    {
        return $this->handleSettings($request, NotificationSettingsType::class, NotificationSettingsType::KEYS, 'Powiadomienia', 'app_settings_notifications');
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
