<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Security\Authentication\Driver\OAuth2;
use App\Security\Authentication\OAuth2Authenticator;
use App\Service\DataStore;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SecurityController extends RenoController
{

    use TargetPathTrait;

    /**
     * @Route("/.login/{reset_code}", name="app_login", priority=10)
     */
    public function login(AuthenticationUtils $authenticationUtils,
                          DataStore $ds, SessionInterface $session,
                          Request $request, $reset_code = null): Response
    {
        $this->title = 'Login';
        if ($this->getUser()) {
            return $this->redirect($request->request->get('last_page') ?:
                    $this->getTargetPath($session, 'main') ?:
                    $this->nav->path('app_home'));
        }

        if (($user_auth = $ds->createAdminUserIfNotExists())) {
            return $this->redirectToRoute('app_login', ['reset_code' => $user_auth->reset_code]);
        }

        $context = [
            'message' => [],
            'last_username' => $authenticationUtils->getLastUsername(),
            'oauth2_drivers' => $ds->queryMany('\App\Entity\AuthDriver', [
                'class' => 'App\Security\Authentication\Driver\OAuth2']),
            'last_page' => $request->request->get('last_page') ?: $this->getTargetPath($session, 'main'),
            'self_register' => ($ds->count(
                '\App\Entity\AuthDriver', ['allow_self_registration' => 1]) > 0),
            'can_reset_password' => $this->canResetPassword(),
        ];
        if (($error = $authenticationUtils->getLastAuthenticationError())) {
            $context['message'] = ['negative' => true, 'text' => $error->getMessage()];
        } elseif (!empty($reset_code) && $request->request->count() == 0) {
            $reset_user = $ds->getUserAuthentication(['reset_code' => $reset_code]);
            if ($reset_user) {
                $context['last_username'] = $reset_user->username;
                $reset_user->credential = '';
                $reset_user->setResetCode('');
                $ds->commit($reset_user);
                $context['message'] = ['negative' => false, 'text' => 'Please login now to set your password.'];
            } else {
                $context['message'] = ['negative' => true, 'text' => 'Invalid password reset code'];
            }
        }

        $count_last = 30;
        $usersCount = $ds->em()->
            createQuery("SELECT COUNT(u) FROM \App\Entity\User u WHERE u.last_login > ?1")->
            setParameter(1, new \DateTime("- $count_last minute"))->
            getSingleScalarResult();
        $context['bottom_message'] = ($usersCount < 2 ? '' : "There are ${usersCount} users who logged in within the last ${count_last} minutes");
        return $this->render('security/login.html.twig', $context);
    }

    /**
     * @Route("/.logout", name="app_logout", priority=10)
     */
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @Route("/.register/{driver}", name="app_register", priority=10)
     */
    public function register(HttpClientInterface $httpClient,
                             UserAuthenticatorInterface $authenticator,
                             OAuth2Authenticator $oauth2auth, Request $request,
                             DataStore $ds, $driver = null): Response
    {
        $auths = $ds->queryMany('\App\Entity\AuthDriver', ['allow_self_registration' => 1]);
        if (count($auths) == 0) {
            throw new \Exception("Self-registration is disabled");
        }

        $this->title = 'Register';
        $recaptcha_keys = array(
            'sitekey' => $_ENV['GOOGLE_RECAPTCHA_SITE_KEY'] ?? null,
            'secretkey' => $_ENV['GOOGLE_RECAPTCHA_SECRET'] ?? null,
        );

        if (!$driver) {
            if (count($auths) == 1) {
                return $this->redirectToRoute('app_register', [
                        'driver' => $auths[0]->name
                ]);
            } else {
                return $this->render('security/register_form.html.twig', [
                        'auths' => $auths,
                ]);
            }
        }

        $authDriver = null;
        foreach ($auths as $auth) {
            if ($driver == $auth->name) {
                $authDriver = $auth;
                break;
            }
        }
        if (!$authDriver) {
            return $this->redirectToRoute('app_register');
        }

        $user = new User();
        $user_auth = new UserAuthentication($user, $authDriver);
        $post = $request->request;
        $session = $request->getSession();
        $authClass = $authDriver->driverClass();
        if ($authClass instanceof OAuth2) {
            if (empty($user_info = $session->get("register.${driver}.userinfo"))) {
                $result = $oauth2auth->process($request, $authDriver, $request->getUri());
                if (!$result) {
                    throw new \Exception('Unable to authenticate you via the third party identity provider. Please try again.');
                }
                if (($redirect = $result->getRedirectResponse())) {
                    return $redirect;
                }
                $user_info = $result->getUserInfo();
                $session->set("register.${driver}.userinfo", $user_info);
                return $this->redirectToRoute('app_register', ['driver' => $driver]);
            }
            if (($existing = $ds->getUserAuthentication(['driver_id' => $driver,
                'credential' => $user_info['username']]))) {
                $this->addFlash('error', "You have previously registered using the same {$auth->title} account. You have been logged in instead.");
                $authenticator->authenticateUser($existing, $oauth2auth, $request);
                $session->remove("register.${driver}.userinfo");
                return $this->redirectAfterAuthenticate($request);
            }

            $user->setUsername($user_info['username']);
            $user->setShortname($user_info['shortname']);
            $user->setEmail($user_info['email']);
            $user_auth->setPassword($user_info['username']);
        }

        if ($post->get('_action') == 'Proceed to register') {
            if (!empty($recaptcha_keys['secretkey'])) {
                $recaptcha = new \ReCaptcha\ReCaptcha($recaptcha_keys['secretkey']);
                $resp = $recaptcha->verify($post->get('g-recaptcha-response'));
                if (!$resp->isSuccess()) {
                    $user->errors['recaptcha'] = 'Invalid response: '.join(", ", $resp->getErrorCodes());
                }
            }

            $user->roles = array('ROLE_USER');
            if ($ds->prepareValidateEntity($user, array('username', 'shortname',
                    'email'), $post)) {
                $user->created_by = $user;
                $user->created_date = new \DateTime();
                $user->last_login = new \DateTime();
                $user_auth->created_by = $user;
                $user_auth->created_date = new \DateTime();

                if ($authClass instanceof OAuth2) {
                    $ds->commit($user);
                    $ds->commit($user_auth);
                    $authenticator->authenticateUser($ds->getUserAuthentication([
                            'driver_id' => $driver, 'username' => $user->getUsername()
                        ]), $oauth2auth, $request);
                    $welcome = sprintf('Welcome to Renogen, %s.', $user->getName());
                    $session->getFlashBag()->add('persistent', $welcome);
                    $session->remove("register.${driver}.userinfo");
                    return $this->redirectAfterAuthenticate($request);
                } else {
                    $this->updateResetCode($user_auth);
                    $ds->commit($user);
                    $ds->commit($user_auth);
                    return $this->redirectToRoute('app_login', ['reset_code' => $user_auth->reset_code]);
                }
            }
        }

        return $this->render('security/register_form.html.twig', [
                'auths' => $auths,
                'auth' => $authDriver,
                'user' => $user,
                'errors' => $user->errors,
                'recaptcha' => $recaptcha_keys,
        ]);
    }

    /**
     * @Route("/.login/resetpwd/", name="app_resetpwd", priority=100)
     */
    public function resetpwd(Request $request, MailerInterface $mailer)
    {
        if (!$this->canResetPassword()) {
            return $this->redirectToRoute('app_login');
        }

        $recaptcha_keys = array(
            'sitekey' => $_ENV['GOOGLE_RECAPTCHA_SITE_KEY'] ?? null,
            'secretkey' => $_ENV['GOOGLE_RECAPTCHA_SECRET'] ?? null,
        );

        $post = $request->request;
        $errors = [];
        $reset_email = '';
        if ($post->count() > 0) {
            if (!empty($recaptcha_keys['secretkey'])) {
                $recaptcha = new \ReCaptcha\ReCaptcha($recaptcha_keys['secretkey']);
                $resp = $recaptcha->verify($post->get('g-recaptcha-response'));
                if (!$resp->isSuccess()) {
                    $errors['recaptcha'] = 'Invalid response: '.join(", ", $resp->getErrorCodes());
                }
            }
            if (empty($post->get('email'))) {
                $errors['email'] = 'Required';
            } else {
                $reset_email = $post->get('email');
                $reset_user = $this->ds->queryOne('\\App\\Entity\\User', ['email' => $reset_email]);
                if (!$reset_user && $reset_user->authentications['password']) {
                    $errors['email'] = 'Not found';
                }
            }

            if (empty($errors)) {
                $this->updateResetCode($reset_user->authentications['password']);
                $this->ds->commit($reset_user->authentications['password']);
                $reset_url = $this->nav->url('app_login', ['reset_code' => $reset_user->authentications['password']->reset_code]);
                $email = (new TemplatedEmail())
                    ->to($reset_email)
                    ->subject("Hello {$reset_user->shortname}! It seems that you forgot your Renogen's password?")
                    ->htmlTemplate('security/resetpwd_email.html.twig')
                    ->text("To reset your Renogen password, go to $reset_url.")
                    ->context([
                    'reset_user' => $reset_user,
                    'reset_url' => $reset_url,
                    'base_url' => $this->nav->url(),
                ]);
                if ($_ENV['MAILER_FROM']) {
                    $mailer_from = $_ENV['MAILER_FROM'];
                    if (!preg_match('/^([^<]*) <([^>]+)>$/', $mailer_from)) {
                        $mailer_from = "Renogen <$mailer_from>";
                    }
                    $email->from($mailer_from);
                }
                $mailer->send($email);
                return $this->renderMessagePage(
                        "Reset password link has been emailed!",
                        "Renogen has sent you an email with the link to reset your password.\nPlease check you email inbox.",
                );
            }
        }

        return $this->render('security/resetpwd_form.html.twig', [
                'email' => $reset_email,
                'errors' => $errors,
                'recaptcha' => $recaptcha_keys,
        ]);
    }

    /**
     * @Route("/.login/oauth2/{driver}", name="app_login_oauth2", priority=20)
     */
    public function loginOAuth2(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirect($request->request->get('last_page') ?:
                    $this->getTargetPath($request->getSession(), 'main') ?:
                    $this->nav->path('app_home'));
        }
        return $this->redirectToRoute('app_login');
    }

    /**
     * @Route("/.oauth2/callback", name="app_oauth2", priority=20)
     */
    public function oauth2_callback(Request $request)
    {
        $original_redirect = $request->getSession()->get('oauth2.original_redirect.url');
        $request->getSession()->set('oauth2.params', json_encode($request->query->all()));
        return new RedirectResponse($original_redirect);
    }

    private function canResetPassword()
    {
        return !empty($_ENV['MAILER_DSN']);
    }

    private function updateResetCode(UserAuthentication $user_auth)
    {
        $user_auth->setResetCode(md5($user_auth->user->getEmail().':'.time()));
    }

    private function redirectAfterAuthenticate(Request $request)
    {
        return $this->redirect(
                $request->request->get('last_page') ?:
                $this->getTargetPath($request->getSession(), 'main') ?:
                $this->nav->path('app_home'));
    }
}