<?php

namespace App\Security\Authentication;

use App\Security\Authentication\Driver\OAuth2;
use App\Service\DataStore;
use App\Service\NavigationFactory;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuth2GuardAuthenticator extends AbstractGuardAuthenticator
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

    /** @var UserPasswordEncoderInterface */
    protected $passwordEncoder;

    /** @var NavigationFactory */
    private $nav;

    /** @var DataStore */
    private $ds;

    public function __construct(EntityManagerInterface $entityManager,
                                CsrfTokenManagerInterface $csrfTokenManager,
                                UserPasswordEncoderInterface $passwordEncoder,
                                NavigationFactory $nav, DataStore $ds,
                                HttpClientInterface $httpClient,
                                RouterInterface $router)
    {
        $this->entityManager = $entityManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
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

    public function getCredentials(Request $request)
    {
        if ($request->query->count() === 0) {
            return [
                'stage' => 'INIT',
                'driver' => $request->attributes->get('driver'),
            ];
        }
        return [
            'stage' => 'HAS_CODE',
            'driver' => $request->attributes->get('driver'),
            'request' => $request,
            'query' => $request->query->all(),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if ($credentials['stage'] == 'INIT') {
            throw new AuthenticationException('', 401);
        }

        $access_token = null;
        $driver = $this->ds->getAuthDriver($credentials['driver']);
        if (!$driver) {
            return new RedirectResponse($this->getLoginUrl());
        }

        $driverClass = $driver->driverClass();
        if ($driverClass instanceof OAuth2) {
            $access_token = $driverClass->handleRedirectRequest(
                $credentials['request'],
                $this->httpClient,
                $this->session);
        }

        if (empty($access_token)) {
            throw new CustomUserMessageAuthenticationException('Unable to authenticate you via the third party identity provider. Please try again.');
        }

        $oauth2_user = $driverClass->fetchUserInfo($access_token, $this->httpClient, $this->session);
        $user_auth = $this->ds->getUserAuthentication([
            'driver_id' => $driver->name,
            'credential' => $oauth2_user['username']
        ]);
        if ($user_auth) {
            if ($user_auth->user->last_login) {
                $welcome = sprintf('Welcome back, %s. Your last login was on %s.', $user_auth->user->getName(), $user_auth->user->last_login->format('d/m/Y h:i A'));
            } else {
                $welcome = sprintf('Welcome to Renogen, %s.', $user_auth->user->getName());
            }
            $this->session->getFlashBag()->add('persistent', $welcome);
            $user_auth->user->last_login = new DateTime();
            $this->ds->commit($user_auth->user);
            return $user_auth;
        }

        throw new CustomUserMessageAuthenticationException('Please register first');
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        return true;
    }

    public function onAuthenticationSuccess(Request $request,
                                            TokenInterface $token,
                                            string $providerKey)
    {
        $redirect = $request->getSession()->get('redirect_after_login');
        if ($redirect) {
            $this->saveTargetPath($request->getSession(), $providerKey, $redirect);
            return new RedirectResponse($redirect);
        }
        return new RedirectResponse($this->nav->path('app_home'));
    }

    public function onAuthenticationFailure(Request $request,
                                            AuthenticationException $exception)
    {
        if ($exception->getCode() == 401) {
            $driver_id = $request->attributes->get('driver');
            $authDriver = $this->ds->getAuthDriver($driver_id);
            if (!$authDriver) {
                return new RedirectResponse($this->getLoginUrl());
            }
            $driverClass = $authDriver->driverClass();
            if ($driverClass instanceof OAuth2) {
                return $driverClass->redirectToAuthorize($this->getRedirectUri($driver_id), $this->session);
            }
        }
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        $request->getSession()->set(Security::LAST_USERNAME, $request->get('username'));
        return new RedirectResponse($this->getLoginUrl());
    }

    public function supportsRememberMe(): bool
    {
        return true;
    }

    protected function getRedirectUri($driver_id)
    {
        return $this->urlGenerator->generate('app_login_oauth2', ['driver' => $driver_id], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}