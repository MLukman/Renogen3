<?php

namespace MLukman\MultiAuthBundle\Authenticator;

use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use MLukman\MultiAuthBundle\Service\MultiAuth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class BaseGuardAuthenticator extends AbstractGuardAuthenticator
{

    use EntryPointTrait;
    /** @var CsrfTokenManagerInterface */
    protected $csrfTokenManager;

    /** @var UserPasswordEncoderInterface */
    protected $passwordEncoder;

    /** @var MultiAuth */
    protected $multiauth;

    /** @var HttpClientInterface */
    protected $httpClient;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager,
                                UserPasswordEncoderInterface $passwordEncoder,
                                MultiAuth $multiauth,
                                HttpClientInterface $httpClient)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->multiauth = $multiauth;
        $this->httpClient = $httpClient;
    }

    public function supports(Request $request): bool
    {
        return $this->login_route === $request->attributes->get('_route');
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }

    protected function logUserSuccessfulLogin(MultiAuthUserCredentialInterface $user_credential)
    {
        $this->multiauth->getAdapter()->logUserSuccessfulLogin($user_credential);
    }
}