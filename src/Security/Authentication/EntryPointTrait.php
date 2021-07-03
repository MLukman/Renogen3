<?php

namespace App\Security\Authentication;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

trait EntryPointTrait
{
    protected $login_route = 'app_login';

    /** @var UrlGeneratorInterface */
    protected $urlGenerator;

    /** @var SessionInterface */
    protected $session;

    /**
     * @required
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): void
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @required
     */
    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
    }

    public function start(Request $request,
                          AuthenticationException $authException = null): Response
    {
        if ($this->login_route !== $request->attributes->get('_route')) {
            $this->session->set('redirect_after_login', $request->getUri());
        }
        $url = $this->getLoginUrl();
        return new RedirectResponse($url);
    }

    protected function getLoginUrl(array $params = [],
                                   int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->urlGenerator->generate($this->login_route, $params, $referenceType);
    }
}