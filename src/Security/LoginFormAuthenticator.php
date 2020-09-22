<?php

namespace App\Security;

use App\Auth\Credentials;
use App\Entity\User;
use App\Service\DataStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Guard\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractFormLoginAuthenticator implements PasswordAuthenticatedInterface
{

    use TargetPathTrait;
    public const LOGIN_ROUTE = 'app_login';

    private $entityManager;
    private $urlGenerator;
    private $csrfTokenManager;
    private $passwordEncoder;
    private $ds;
    private $session;

    public function __construct(EntityManagerInterface $entityManager,
                                UrlGeneratorInterface $urlGenerator,
                                CsrfTokenManagerInterface $csrfTokenManager,
                                UserPasswordEncoderInterface $passwordEncoder,
                                DataStore $ds, SessionInterface $session)
    {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->ds = $ds;
        $this->session = $session;
    }

    public function supports(Request $request)
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function getCredentials(Request $request)
    {
        $credentials = [
            'username' => $request->request->get('username'),
            'password' => $request->request->get('password'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];
        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['username']
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $credentials['username']]);

        if (!$user) {
            // User not found
            throw new CustomUserMessageAuthenticationException('Username could not be found.');
        } elseif ($user->blocked) {
            // User blocked
            throw new CustomUserMessageAuthenticationException('Sorry but you have been blocked from logging into Renogen. Please contact an administrator if you think it is a mistake.');
        }

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        $authDriver = $this->ds->getAuthDriver($user->auth);
        if (!$authDriver) {
            // Authentication method missing
            throw new CustomUserMessageAuthenticationException("Your account requires authentication method '$user->auth' which has been disabled. Please contact an administrator to request for access.");
        }
        if ($authDriver->driverClass()->authenticate(new Credentials($credentials['username'], $credentials['password']),
                $user, $this->passwordEncoder, $authDriver, $this->ds)) {
            if ($user->last_login) {
                $welcome = sprintf('Welcome back, %s. Your last login was on %s.', $user->getName(), $user->last_login->format('d/m/Y h:i A'));
            } else {
                $welcome = sprintf('Welcome to Renogen, %s.', $user->getName());
            }
            $this->session->getFlashBag()->add('persistent', $welcome);
            $user->last_login = new \DateTime();
            $this->ds->commit($user);
            return true;
        }
        return false;
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function getPassword($credentials): ?string
    {
        return $credentials['password'];
    }

    public function onAuthenticationSuccess(Request $request,
                                            TokenInterface $token,
                                            string $providerKey)
    {
        if (($targetPath = $this->getTargetPath($request->getSession(), $providerKey))) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl()
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}