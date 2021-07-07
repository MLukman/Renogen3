<?php

namespace App\Security\Authentication;

use App\Service\DataStore;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class FormAuthenticator extends AbstractAuthenticator
{

    use TargetPathTrait;
    use EntryPointTrait;
    /** the route for login */
    public const LOGIN_ROUTE = 'app_login';

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var CsrfTokenManagerInterface */
    private $csrfTokenManager;

    /** @var UserPasswordHasherInterface */
    protected $passwordEncoder;

    /** @var DataStore */
    private $ds;

    public function __construct(EntityManagerInterface $entityManager,
                                CsrfTokenManagerInterface $csrfTokenManager,
                                UserPasswordHasherInterface $passwordEncoder,
                                DataStore $ds)
    {
        $this->entityManager = $entityManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->ds = $ds;
    }

    public function supports(Request $request): bool
    {
        return $this->login_route === $request->attributes->get('_route') && $request->isMethod('POST')
            && $request->request->get('method') == 'form';
    }

    public function authenticate(Request $request): PassportInterface
    {
        $credentials = [
            'username' => $request->request->get('username'),
            'password' => $request->request->get('password'),
            'driver' => $request->request->get('driver'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];

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
        $authDriver = $user_auth->driver;
        if (!$authDriver) {
            // Authentication method missing
            throw new CustomUserMessageAuthenticationException("Your account requires authentication method '$user_auth->auth' which has been disabled. Please contact an administrator to request for access.");
        }

        if (!$authDriver->driverClass()->authenticate(new Credentials($credentials['username'], $credentials['password']),
                $user_auth, $this->passwordEncoder, $authDriver, $this->ds)) {
            throw new CustomUserMessageAuthenticationException("Invalid credentials");
        }

        return new SelfValidatingPassport(
            new UserBadge($user_auth->username, function($id) use ($user_auth) {
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
        }
        return null;
    }

    public function onAuthenticationFailure(Request $request,
                                            AuthenticationException $exception): ?Response
    {
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        $request->getSession()->set(Security::LAST_USERNAME, $request->get('username'));
        return null;
    }
}