<?php

namespace MLukman\MultiAuthBundle\Authenticator;

use MLukman\MultiAuthBundle\DriverInstance;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class FormGuardAuthenticator extends BaseGuardAuthenticator
{
    /** @var UserPasswordEncoderInterface */
    protected $passwordEncoder;

    /**
     * @required
     */
    public function setPasswordEncoder(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function supports(Request $request): bool
    {
        return parent::supports($request) && $request->isMethod('POST') && $request->request->get('method')
            == 'form';
    }

    public function getCredentials(Request $request)
    {
        return [
            'username' => $request->request->get('username'),
            'password' => $request->request->get('password'),
            'driver' => $request->request->get('driver'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException('Invalid CSRF');
        }

        try {
            $user = $userProvider->loadUserByUsername($credentials['username']);
        } catch (\Exception $ex) {
            throw new CustomUserMessageAuthenticationException($ex->getMessage());
        }

        if (!$user) {
            // User not found
            throw new CustomUserMessageAuthenticationException('Username could not be found.');
        } elseif ($this->multiauth->getAdapter()->isUsernameBlocked($credentials['username'])) {
            // User blocked
            throw new CustomUserMessageAuthenticationException('Sorry but you have been blocked from logging in. Please contact an administrator if you think it is a mistake.');
        }

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        $multi_auth_user = $this->multiauth->getAdapter()->loadUserByUsername($user->getUsername());
        $stored_credential = $multi_auth_user->getCredentialByDriverId($credentials['driver']);
        if (!$stored_credential) {
            return false;
        }

        /** @var DriverInstance $authDriver */
        $authDriver = $this->multiauth->getAdapter()->loadDriverInstance($stored_credential->driver_id);
        if (!$authDriver) {
            // Authentication method missing
            throw new CustomUserMessageAuthenticationException("Your account requires authentication method '$stored_credential->driver_id' which has been disabled. Please contact an administrator to request for access.");
        }

        if ($authDriver->getClass()->authenticate($credentials, $stored_credential, $this->multiauth->getAdapter(), $this->passwordEncoder)) {
            $this->logUserSuccessfulLogin($stored_credential);
            return true;
        }

        return false;
    }

    public function encodePasswordUsingDriver(Driver\FormDriverInterface $driver,
                                              UserInterface $securityUser,
                                              string $password): string
    {
        return $driver->encodePassword($this->passwordEncoder, $securityUser, $password);
    }

    public function onAuthenticationFailure(Request $request,
                                            AuthenticationException $exception)
    {
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        $request->getSession()->set(Security::LAST_USERNAME, $request->get('username'));
    }

    public function supportsRememberMe(): bool
    {
        return true;
    }

    public function onAuthenticationSuccess(Request $request,
                                            TokenInterface $token,
                                            string $providerKey)
    {

    }
}