<?php

declare(strict_types=1);

namespace App\Farm;

use App\Entity\User;
use App\Repository\FarmMembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Ustawia gospodarstwo wybrane w sesji. Strony gospodarstwa bez wybranego gospodarstwa
 * (albo po utracie dostępu) przekierowują do wyboru gospodarstwa.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final class FarmContextSubscriber
{
    public const string SESSION_KEY = 'farm_id';

    /** Trasy, które działają bez wybranego gospodarstwa. */
    private const array FARMLESS_ROUTES = [
        'app_login', 'app_logout', 'app_pin', 'app_register',
        'app_farm_index', 'app_farm_new', 'app_farm_select',
        'app_account', 'app_account_device_revoke', 'app_account_notifications',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly FarmMembershipRepository $memberships,
        private readonly FarmContext $context,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (str_starts_with($route, '_wdt') || str_starts_with($route, '_profiler')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $farmId = $session?->get(self::SESSION_KEY);
        if (null !== $farmId) {
            $membership = $this->memberships->findOneForUser($user, (int) $farmId);
            if (null !== $membership) {
                $this->context->set($membership->getFarm(), $membership->getRole());

                return;
            }
            $session?->remove(self::SESSION_KEY);
        }

        if (\in_array($route, self::FARMLESS_ROUTES, true)) {
            return;
        }

        if (!$request->isMethod('GET') || $request->isXmlHttpRequest() || $request->headers->has('Turbo-Frame')) {
            throw new AccessDeniedHttpException('Najpierw wybierz gospodarstwo.');
        }

        $event->setResponse(new RedirectResponse($this->urls->generate('app_farm_index')));
    }
}
