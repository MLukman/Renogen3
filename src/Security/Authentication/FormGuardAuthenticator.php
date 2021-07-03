<?php

namespace App\Security\Authentication;

use App\Service\DataStore;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class FormGuardAuthenticator extends AbstractGuardAuthenticator
{

    use TargetPathTrait;
    use EntryPointTrait;
    /** the route for login */
    public const LOGIN_ROUTE = 'app_login';

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var CsrfTokenManagerInterface */
    private $csrfTokenManager;

    /** @var UserPasswordEncoderInterface */
    protected $passwordEncoder;

    /** @var DataStore */
    private $ds;

    public function __construct(EntityManagerInterface $entityManager,
                                CsrfTokenManagerInterface $csrfTokenManager,
                                UserPasswordEncoderInterface $passwordEncoder,
                                DataStore $ds)
    {
        $this->entityManager = $entityManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->ds = $ds;
    }

    /**
     * @required
     */
    public function setPasswordEncoder(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function supports(Request $request): bool
    {
        return $this->login_route === $request->attributes->get('_route') && $request->isMethod('POST')
            && $request->request->get('method') == 'form';
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
            $user_auth = $this->ds->getUserAuthentication([
                'username' => $credentials['username'],
                'driver_id' => $credentials['driver']
            ]);
        } catch (\Exception $ex) {
            throw new CustomUserMessageAuthenticationException($ex->getMessage());
        }

        if (!$user_auth) {
            // User not found
            throw new CustomUserMessageAuthenticationException('Username could not be found.');
        } elseif ($user_auth->user->blocked) {
            // User blocked
            throw new CustomUserMessageAuthenticationException('Sorry but you have been blocked from logging in. Please contact an administrator if you think it is a mistake.');
        }

        return $user_auth;
    }

    public function checkCredentials($credentials, UserInterface $user_auth): bool
    {
        $authDriver = $this->ds->getAuthDriver($user_auth->driver_id);
        if (!$authDriver) {
            // Authentication method missing
            throw new CustomUserMessageAuthenticationException("Your account requires authentication method '$user_auth->auth' which has been disabled. Please contact an administrator to request for access.");
        }

        if ($authDriver->driverClass()->authenticate(new Credentials($credentials['username'], $credentials['password']),
                $user_auth, $this->passwordEncoder, $authDriver, $this->ds)) {
            if ($user_auth->user->last_login) {
                $welcome = sprintf('Welcome back, %s. Your last login was on %s.', $user_auth->user->getName(), $user_auth->user->last_login->format('d/m/Y h:i A'));
            } else {
                $welcome = sprintf('Welcome to Renogen, %s.', $user_auth->user->getName());
            }
            $this->session->getFlashBag()->add('persistent', $welcome);
            $user_auth->user->last_login = new DateTime();
            $this->ds->commit($user_auth->user);
            return true;
        }
        return false;
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
        $redirect = $this->session->get('redirect_after_login');
        if ($redirect) {
            $this->saveTargetPath($this->session, $providerKey, $redirect);
        }
        return null;
    }
}