<?php

namespace App\Security\Authentication;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

trait EntryPointTrait
{
    protected $login_route = 'app_login';

    /** @var UrlGeneratorInterface */
    protected $urlGenerator;

    /**
     * @required
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): void
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function start(Request $request,
                          AuthenticationException $authException = null): Response
    {
        if ($this->login_route !== $request->attributes->get('_route')) {
            $request->getSession()->set('redirect_after_login', $request->getUri());
        }
        $url = $this->getLoginUrl();
        return new RedirectResponse($url);
    }

    protected function getLoginUrl(array $params = [],
                                   int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->urlGenerator->generate($this->login_route, $params, $referenceType);
    }

    protected function getWelcomMessageAndUserLastLogin(User $user): string
    {
        if ($user->last_login) {
            $welcome = sprintf('Welcome back, %s. Your last login was on %s.', $user->getName(), $user->last_login->format('d/m/Y h:i A'));
        } else {
            $welcome = sprintf('Welcome to Renogen, %s.', $user->getName());
        }
        $user->last_login = new \DateTime();
        return $welcome;
    }
}