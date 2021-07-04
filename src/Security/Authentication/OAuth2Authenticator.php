<?php

namespace App\Security\Authentication;

use App\Entity\AuthDriver;
use App\Security\Authentication\Driver\OAuth2;
use App\Service\DataStore;
use App\Service\NavigationFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuth2Authenticator extends AbstractAuthenticator
{

    use TargetPathTrait;
    use EntryPointTrait;
    /** @var HttpClientInterface */
    protected $httpClient;

    /** @var RouterInterface */
    protected $router;

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var CsrfTokenManagerInterface */
    private $csrfTokenManager;

    /** @var NavigationFactory */
    private $nav;

    /** @var DataStore */
    private $ds;

    public function __construct(EntityManagerInterface $entityManager,
                                CsrfTokenManagerInterface $csrfTokenManager,
                                NavigationFactory $nav, DataStore $ds,
                                HttpClientInterface $httpClient,
                                RouterInterface $router)
    {
        $this->entityManager = $entityManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->nav = $nav;
        $this->ds = $ds;
        $this->httpClient = $httpClient;
        $this->router = $router;
    }

    public function supports(Request $request): bool
    {
        return in_array($request->attributes->get('_route'), ['app_login_oauth2'])
            && !empty($request->attributes->get('driver'));
    }

    public function authenticate(Request $request): PassportInterface
    {
        $driver_id = $request->attributes->get('driver');
        $authDriver = $this->ds->getAuthDriver($driver_id);
        if (!$authDriver) {
            throw new CustomUserMessageAuthenticationException('Invalid login method detected.');
        }

        if (!($oauth2_user = $this->process($request, $authDriver, $this->getRedirectUri($authDriver->name)))) {
            throw new CustomUserMessageAuthenticationException('Unable to authenticate you via the third party identity provider. Please try again.');
        }

        $user_auth = $this->ds->getUserAuthentication([
            'driver_id' => $authDriver->name,
            'credential' => $oauth2_user['username']
        ]);
        if ($user_auth) {
            if ($user_auth->user->last_login) {
                $welcome = sprintf('Welcome back, %s. Your last login was on %s.', $user_auth->user->getName(), $user_auth->user->last_login->format('d/m/Y h:i A'));
            } else {
                $welcome = sprintf('Welcome to Renogen, %s.', $user_auth->user->getName());
            }
            $request->getSession()->getFlashBag()->add('persistent', $welcome);
            $user_auth->user->last_login = new \DateTime();
            $this->ds->commit($user_auth->user);
            return new SelfValidatingPassport(
                new UserBadge($user_auth->username, function($id) use ($user_auth) {
                    return $user_auth;
                }));
        }

        throw new CustomUserMessageAuthenticationException('Please register first');
    }

    public function onAuthenticationSuccess(Request $request,
                                            TokenInterface $token,
                                            string $providerKey): ?Response
    {
        $redirect = $request->getSession()->get('redirect_after_login');
        if ($redirect) {
            $this->saveTargetPath($request->getSession(), $providerKey, $redirect);
            return new RedirectResponse($redirect);
        }
        return new RedirectResponse($this->nav->path('app_home'));
    }

    public function onAuthenticationFailure(Request $request,
                                            AuthenticationException $exception): ?Response
    {
        if ($exception instanceof OAuth2RedirectionRequiredException) {
            return $exception->generateRedirectResponse();
        }
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        return new RedirectResponse($this->getLoginUrl());
    }

    protected function getRedirectUri($driver_id)
    {
        return $this->urlGenerator->generate('app_login_oauth2', ['driver' => $driver_id], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function process(Request $request, AuthDriver $authDriver,
                            $redirect_uri): ?array
    {
        $driverClass = $authDriver->driverClass();
        if (!($driverClass instanceof OAuth2)) {
            return null;
        }
        // fresh request -> redirect to OAuth2 provider
        if ($request->query->count() === 0) {
            throw new OAuth2RedirectionRequiredException(
                $driverClass->generateRedirectToAuthorizeURL($redirect_uri, $request->getSession()));
        }

        // coming from OAuth2 provider
        if (empty($access_token = $driverClass->handleRedirectRequest($request, $this->httpClient, $request->getSession()))) {
            return null;
        }

        return $driverClass->fetchUserInfo($access_token, $this->httpClient, $request->getSession());
    }
}