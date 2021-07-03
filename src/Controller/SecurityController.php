<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Security\Authentication\Driver\OAuth2;
use App\Security\Authentication\OAuth2GuardAuthenticator;
use App\Service\DataStore;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SecurityController extends RenoController
{

    use TargetPathTrait;

    /**
     * @Route("/_login/oauth2/{driver}", name="app_login_oauth2", priority=20)
     */
    public function loginOAuth2(AuthenticationUtils $authenticationUtils,
                                DataStore $ds, SessionInterface $session,
                                UserPasswordEncoderInterface $passwordEncoder,
                                Request $request, $driver): Response
    {
        if ($this->getUser()) {
            return $this->redirect($request->request->get('last_page') ?:
                    $this->getTargetPath($session, 'main') ?:
                    $this->nav->path('app_home'));
        }
        return $this->redirectToRoute('app_login');
    }

    /**
     * @Route("/_login/{reset_code}", name="app_login", priority=10)
     */
    public function login(AuthenticationUtils $authenticationUtils,
                          DataStore $ds, SessionInterface $session,
                          UserPasswordEncoderInterface $passwordEncoder,
                          Request $request, $reset_code = null): Response
    {
        $this->title = 'Login';
        if ($this->getUser()) {
            return $this->redirect($request->request->get('last_page') ?:
                    $this->getTargetPath($session, 'main') ?:
                    $this->nav->path('app_home'));
        }

        if (count($ds->queryMany('\App\Entity\User')) == 0) {
            // register admin user
            $user = new User();
            $user->username = 'admin';
            $user->shortname = 'Administrator';
            $user->roles = array('ROLE_ADMIN');
            $user->created_by = $user;
            $user->created_date = new \DateTime();
            $user_auth = new UserAuthentication($user);
            $user_auth->driver_id = 'password';
            $this->updateResetCode($user_auth);
            $ds->commit($user);
            $ds->commit($user_auth);
            return $this->redirectToRoute('app_login', array('reset_code' => $user_auth->reset_code));
        }

        $message = array();
        $username = '';
        if (($error = $authenticationUtils->getLastAuthenticationError())) {
            $message['text'] = $error->getMessage();
            $message['negative'] = true;
        } elseif (!empty($reset_code) && $request->request->count() == 0) {
            $reset_user = $ds->queryOne('\App\Entity\UserAuthentication', ['reset_code' => $reset_code]);
            if ($reset_user) {
                $username = $reset_user->username;
                $reset_user->credential = '';
                $reset_user->setResetCode('');
                $ds->commit($reset_user);
                $message['text'] = 'Please login now to set your password.';
                $message['negative'] = false;
            } else {
                $message['text'] = 'Invalid password reset code';
                $message['negative'] = true;
            }
        }

        $lastUsername = $authenticationUtils->getLastUsername();
        $count_last = 30;
        $query = $ds->em()->createQuery("SELECT COUNT(u) FROM \App\Entity\User u WHERE u.last_login > ?1");
        $query->setParameter(1, new \DateTime("- $count_last minute"));
        $usersCount = $query->getSingleScalarResult();

        return $this->render('security/login.html.twig', [
                'last_username' => $username ?: $lastUsername,
                'message' => $message,
                'oauth2_drivers' => $ds->em()->getRepository('\App\Entity\AuthDriver')->findBy([
                    'class' => 'App\Security\Authentication\Driver\OAuth2']),
                'last_page' => $request->request->get('last_page') ?: $this->getTargetPath($session, 'main'),
                'bottom_message' => ($usersCount < 2 ? '' : "There are ${usersCount} users who logged in within the last ${count_last} minutes"),
                'self_register' => (count($ds->queryMany('\App\Entity\AuthDriver', array(
                        'allow_self_registration' => 1))) > 0),
                'can_reset_password' => $this->canResetPassword(),
        ]);
    }

    /**
     * @Route("/_logout", name="app_logout", priority=10)
     */
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @Route("/_register/{driver}", name="app_register", priority=10)
     */
    public function register(HttpClientInterface $httpClient,
                             SessionInterface $session,
                             GuardAuthenticatorHandler $guardHandler,
                             OAuth2GuardAuthenticator $oauth2auth,
                             Request $request, DataStore $ds, $driver = null): Response
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

        $user = new User();
        $user_auth = new UserAuthentication($user);
        $selected_auth = null;
        $post = $request->request;

        if (!$driver && count($auths) == 1) {
            return $this->redirectToRoute('app_register', [
                    'driver' => $auths[0]->name
            ]);
        }

        if ($driver) {
            foreach ($auths as $auth) {
                if ($driver == $auth->name) {
                    $selected_auth = $auth;
                    break;
                }
            }
            if (!$selected_auth) {
                return $this->redirectToRoute('app_register');
            }

            $user_auth->driver_id = $driver;
            $authClass = $selected_auth->driverClass();
            if ($authClass instanceof OAuth2) {
                if (empty($access_token = $session->get("register.${driver}.token"))) {
                    $redirect_uri = $request->getUri();
                    if ($request->query->count() === 0) {
                        return $authClass->redirectToAuthorize($redirect_uri, $session);
                    }
                    if (!($access_token = $authClass->handleRedirectRequest($request, $httpClient, $session))) {
                        $this->addFlash('error', "Unable to authenticate with {$auth->title}. Please try again.");
                        return $this->redirectToRoute('app_register');
                    }
                    $session->set("register.${driver}.token", $access_token);
                    return $this->redirectToRoute('app_register', ['driver' => $driver]);
                }
                $user_info = $authClass->fetchUserInfo($access_token, $httpClient, $session);
                if (($existing = $ds->getUserAuthentication(['driver_id' => $driver,
                    'credential' => $user_info['username']]))) {
                    $this->addFlash('error', "You have previously registered using the same {$auth->title} account. You have been logged in instead.");
                    $guardHandler->authenticateUserAndHandleSuccess($existing, $request, $oauth2auth, 'main');
                    return $this->redirectAfterAuthenticate($request, $session);
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
                    $user_auth->created_by = $user;
                    $user_auth->created_date = new \DateTime();

                    $redirectTo = $this->redirectToRoute('app_home');
                    if ($authClass instanceof OAuth2) {
                        $ds->commit($user);
                        $ds->commit($user_auth);
                        return $guardHandler->authenticateUserAndHandleSuccess(
                                $ds->getUserAuthentication([
                                    'driver_id' => $driver, 'username' => $user->getUsername()
                                ]),
                                $request, $oauth2auth, 'main');
                    } else {
                        $this->updateResetCode($user_auth);
                        $ds->commit($user);
                        $ds->commit($user_auth);
                        return $this->redirectToRoute('app_login', ['reset_code' => $user_auth->reset_code]);
                    }
                }
            }
        }

        return $this->render('security/register_form.html.twig', [
                'auths' => $auths,
                'auth' => $selected_auth,
                'user' => $user,
                'errors' => $user->errors,
                'recaptcha' => $recaptcha_keys,
        ]);
    }

    /**
     * @Route("/_login/resetpwd/", name="app_resetpwd", priority=100)
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

    private function canResetPassword()
    {
        return !empty($_ENV['MAILER_DSN']);
    }

    private function updateResetCode(UserAuthentication $user_auth)
    {
        $user_auth->setResetCode(md5($user_auth->user->getEmail().':'.time()));
    }

    private function redirectAfterAuthenticate(Request $request,
                                               SessionInterface $session)
    {
        return $this->redirect(
                $request->request->get('last_page') ?:
                $this->getTargetPath($session, 'main') ?:
                $this->nav->path('app_home'));
    }
}