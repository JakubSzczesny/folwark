<?php

declare(strict_types=1);

namespace App\Module;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Ekrany wyłączonego modułu są niedostępne: zwykłe wejście wraca na pulpit z komunikatem,
 * pozostałe żądania (formularze, ramki, fetch) dostają 404. Działa po ustawieniu gospodarstwa.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
final class ModuleAccessSubscriber
{
    public function __construct(
        private readonly Modules $modules,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $module = $this->modules->disabledModuleOfRoute((string) $request->attributes->get('_route'));
        if (null === $module) {
            return;
        }

        $message = \sprintf('Moduł „%s” jest wyłączony. Można go włączyć w Ustawienia → Moduły.', Modules::label($module));
        if (!$request->isMethod('GET') || $request->isXmlHttpRequest() || $request->headers->has('Turbo-Frame')) {
            throw new NotFoundHttpException($message);
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('warning', $message);
        }
        $event->setResponse(new RedirectResponse($this->urls->generate('app_dashboard')));
    }
}
