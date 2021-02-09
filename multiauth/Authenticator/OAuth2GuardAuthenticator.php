<?php

namespace MLukman\MultiAuthBundle\Authenticator;

use App\Entity\UserCredential;
use MLukman\MultiAuthBundle\Authenticator\Driver\OAuth2DriverInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class OAuth2GuardAuthenticator extends BaseGuardAuthenticator
{

    use TargetPathTrait;

    public function supports(Request $request): bool
    {
        return parent::supports($request) && !empty($request->attributes->get('driver'));
    }

    public function getCredentials(Request $request)
    {
        $code = $request->query->get('code');
        if (empty($code)) {
            return [
                'stage' => 'INIT',
                'driver' => $request->attributes->get('driver'),
            ];
        }
        return [
            'stage' => 'HAS_CODE',
            'driver' => $request->attributes->get('driver'),
            'code' => $code,
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if ($credentials['stage'] == 'INIT') {
            throw new AuthenticationException('', 401);
        }

        $access_token = null;
        $driver = $this->multiauth->getAdapter()->loadDriverInstance($credentials['driver']);
        $driverClass = $driver->getClass();
        if ($driverClass instanceof OAuth2DriverInterface) {
            $access_token = $driverClass->fetchAccessToken($this->httpClient, $credentials['code'], $this->getRedirectUri($credentials['driver']));
        }

        if (empty($access_token)) {
            throw new CustomUserMessageAuthenticationException('Unable to authenticate you via the third party identity provider. Please try again.');
        }

        $oauth2_user = $driverClass->fetchUserInfo($this->httpClient, $access_token);
        foreach ($this->multiauth->getAdapter()->loadAllUserCredentialsForDriverId($credentials['driver']) as $cred) {
            /** @var UserCredential $cred */
            if ($cred->credential_value == $oauth2_user['username']) {
                $this->logUserSuccessfulLogin($cred);
                return $cred->user;
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
        $this->saveTargetPath($this->session, $providerKey, $this->session->get('redirect_after_login'));
        return null;
    }

    public function onAuthenticationFailure(Request $request,
                                            AuthenticationException $exception)
    {
        if ($exception->getCode() == 401) {
            $driver_id = $request->attributes->get('driver');
            $driver = $this->multiauth->getAdapter()->loadDriverInstance($driver_id);
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
        return 'http://localhost/Renogen3/public/login/'.$driver_id;
    }
}