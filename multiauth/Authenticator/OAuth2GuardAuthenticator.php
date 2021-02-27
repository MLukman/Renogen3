<?php

namespace MLukman\MultiAuthBundle\Authenticator;

use App\Entity\UserCredential;
use MLukman\MultiAuthBundle\Authenticator\Driver\OAuth2DriverInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuth2GuardAuthenticator extends BaseGuardAuthenticator
{

    use TargetPathTrait;
    /** @var HttpClientInterface */
    protected $httpClient;

    /** @var RouterInterface */
    protected $router;

    /**
     * @required
     */
    public function setHttpClient(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @required
     */
    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function supports(Request $request): bool
    {
        return parent::supports($request) && !empty($request->attributes->get('driver'));
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
            'query' => $request->query->all(),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if ($credentials['stage'] == 'INIT') {
            throw new AuthenticationException('', 401);
        }

        $access_token = null;
        $driver = $this->multiauth->getAdapter()->loadDriverInstance($credentials['driver']);
        if (!$driver) {
            return new RedirectResponse($this->getLoginUrl());
        }
        $driverClass = $driver->getClass();
        if ($driverClass instanceof OAuth2DriverInterface) {
            if (isset($credentials['query']['token'])) {
                // implicit flow
                $access_token = $credentials['query']['token'];
            } elseif (isset($credentials['query']['code'])) {
                // auth token flow
                $access_token = $driverClass->fetchAccessToken($this->httpClient, $credentials['query']['code'], $this->getRedirectUri($credentials['driver']));
            }
        }

        if (empty($access_token)) {
            throw new CustomUserMessageAuthenticationException('Unable to authenticate you via the third party identity provider. Please try again.');
        }

        $oauth2_user = $driverClass->fetchUserInfo($this->httpClient, $access_token);
        foreach ($this->multiauth->getAdapter()->loadAllUserCredentialsForDriverId($credentials['driver']) as $cred) {
            /** @var UserCredential $cred */
            if ($cred->credential_value == $oauth2_user['username']) {
                $this->logUserSuccessfulLogin($cred);
                return $cred->getUser();
            }
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
        $redirect = $this->session->get('redirect_after_login');
        if ($redirect) {
            $this->saveTargetPath($this->session, $providerKey, $redirect);
        }
        return null;
    }

    public function onAuthenticationFailure(Request $request,
                                            AuthenticationException $exception)
    {
        if ($exception->getCode() == 401) {
            $driver_id = $request->attributes->get('driver');
            $driver = $this->multiauth->getAdapter()->loadDriverInstance($driver_id);
            if (!$driver) {
                return new RedirectResponse($this->getLoginUrl());
            }
            $driverClass = $driver->getClass();
            if ($driverClass instanceof OAuth2DriverInterface) {
                return $driverClass->redirectToAuthorize($this->getRedirectUri($driver_id));
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
        return $this->getLoginUrl(['driver' => $driver_id], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}