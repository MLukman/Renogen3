<?php

namespace MLukman\MultiAuthBundle\Authenticator;

use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use MLukman\MultiAuthBundle\Service\MultiAuth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

abstract class BaseGuardAuthenticator extends AbstractGuardAuthenticator
{

    use EntryPointTrait;
    /** @var MultiAuth */
    protected $multiauth;

    /** @var CsrfTokenManagerInterface */
    protected $csrfTokenManager;

    public function __construct(MultiAuth $multiauth,
                                CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->multiauth = $multiauth;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function supports(Request $request): bool
    {
        return $this->login_route === $request->attributes->get('_route');
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }

    public function logUserSuccessfulLogin(MultiAuthUserCredentialInterface $user_credential)
    {
        $this->multiauth->getAdapter()->logUserSuccessfulLogin($user_credential);
    }
}