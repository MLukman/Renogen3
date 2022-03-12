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

        $result = $this->process($request, $authDriver);
        if (!$result) {
            throw new CustomUserMessageAuthenticationException('Unable to authenticate you via the third party identity provider. Please try again.');
        }
        if (($redirect = $result->getRedirectResponse())) {
            throw new OAuth2RedirectionRequiredException($redirect->getTargetUrl());
        }
        $user_info = $result->getUserInfo();

        $user_auth = $this->ds->getUserAuthentication([
            'driver_id' => $authDriver->name,
            'credential' => $user_info['username']]);

        if (!$user_auth) {
            throw new CustomUserMessageAuthenticationException("Please register first. Or you can login using another method and add {$authDriver->title} login from the profile page.");
        }

        // Update email address into UserAuthentication entity
        if (empty($user_auth->email)) {
            $user_auth->email = $user_info['email'];
            $this->ds->commit($user_auth);
        }

        return new SelfValidatingPassport(
            new UserBadge($user_auth->username, function ($id) use ($user_auth) {
                    return $user_auth;
                }));
    }

    public function onAuthenticationSuccess(Request $request,
                                            TokenInterface $token,
                                            string $providerKey): ?Response
    {
        $user = $this->ds->currentUserEntity();
        $welcome = $this->getWelcomMessageAndUserLastLogin($user);
        $this->ds->commit($user);
        $request->getSession()->getFlashBag()->add('persistent', $welcome);

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
                            $redirect_uri = null): ?OAuth2AuthenticatorResult
    {
        $driverClass = $authDriver->driverClass();
        if (!($driverClass instanceof OAuth2)) {
            return null;
        }

        if (!$redirect_uri) {
            $redirect_uri = $request->getUri();
        }

        // fresh request -> redirect to OAuth2 provider
        if (!$request->getSession()->get('oauth2.original_redirect.url') ||
            empty($request->getSession()->get('oauth2.params'))) {
            $request->getSession()->set('oauth2.original_redirect.url', $redirect_uri);
            $redirect_uri = $this->nav->url('app_oauth2');
            return OAuth2AuthenticatorResult::redirect($driverClass->generateRedirectToAuthorizeURL($redirect_uri, $request->getSession()));
        }
        $request->getSession()->remove('oauth2.original_redirect.url');
        $request->query->add(\json_decode($request->getSession()->get('oauth2.params'), true));

        // coming from OAuth2 provider
        if (empty($access_token = $driverClass->handleRedirectRequest($request, $this->httpClient, $request->getSession()))) {
            return null;
        }

        return OAuth2AuthenticatorResult::userInfo($driverClass->fetchUserInfo($access_token, $this->httpClient, $request->getSession()));
    }
}