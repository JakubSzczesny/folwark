<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\TrustedDevice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class PinAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    private const string DEVICE_ATTRIBUTE = '_trusted_device';

    public function __construct(
        private readonly TrustedDeviceManager $devices,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function supports(Request $request): bool
    {
        return 'app_pin' === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $device = $this->devices->findForRequest($request);
        if (null === $device) {
            throw new CustomUserMessageAuthenticationException('To urządzenie nie jest już zaufane. Zaloguj się e-mailem i hasłem.');
        }
        $request->attributes->set(self::DEVICE_ATTRIBUTE, $device);

        $pin = (string) $request->getPayload()->get('pin', '');
        $user = $device->getUser();

        return new Passport(
            new UserBadge($user->getUserIdentifier()),
            new CustomCredentials(fn (string $pin): bool => $this->devices->verifyPin($device, $pin), $pin),
            [new CsrfTokenBadge('pin', (string) $request->getPayload()->get('_csrf_token'))],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $device = $request->attributes->get(self::DEVICE_ATTRIBUTE);
        if ($device instanceof TrustedDevice) {
            $device->registerSuccess();
            $this->em->flush();
        }

        $target = $this->getTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse($target ?? $this->urls->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $response = new RedirectResponse($this->urls->generate('app_login'));
        $device = $request->attributes->get(self::DEVICE_ATTRIBUTE);

        if ($device instanceof TrustedDevice) {
            $device->registerFailure();
            if ($device->isLocked()) {
                $this->em->remove($device);
                $response->headers->setCookie(TrustedDeviceManager::clearCookie());
                $exception = new CustomUserMessageAuthenticationException('Zbyt wiele błędnych prób. Zaloguj się e-mailem i hasłem.');
            } elseif (!$exception instanceof CustomUserMessageAuthenticationException) {
                $exception = new CustomUserMessageAuthenticationException('Nieprawidłowy PIN.');
            }
            $this->em->flush();
        }

        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return $response;
    }
}
