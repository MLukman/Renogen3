<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\AuthDriver;
use App\Entity\User;
use App\Entity\UserCredential;
use App\Service\DataStore;
use MLukman\MultiAuthBundle\Authenticator\Driver\FormDriverInterface;
use MLukman\MultiAuthBundle\Authenticator\Driver\OAuth2DriverInterface;
use MLukman\MultiAuthBundle\Authenticator\FormGuardAuthenticator;
use MLukman\MultiAuthBundle\Authenticator\OAuth2GuardAuthenticator;
use ReCaptcha\ReCaptcha;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SecurityController extends RenoController
{

    use TargetPathTrait;

    /**
     * @Route("/login/{driver}", name="app_login", priority=10, defaults={"driver"=null})
     */
    public function login(AuthenticationUtils $authenticationUtils,
                          DataStore $ds, SessionInterface $session,
                          Request $request, $driver): Response
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
            $user->auth = 'password';
            $user->password = '';
            $user->created_by = $user;
            $user->created_date = new \DateTime();
            $ds->commit($user);
            return $this->redirectToRoute('app_login', ['username' => $user->username]);
        }

        $username = $request->query->get('username');
        $message = array();
        if (($error = $authenticationUtils->getLastAuthenticationError())) {
            $message['text'] = $error->getMessage();
            $message['negative'] = true;
        } elseif (!empty($username)) {
            $message['text'] = 'You have been successfully registered. Please login now to set your password.';
            $message['negative'] = false;
        }

        $lastUsername = $authenticationUtils->getLastUsername();
        $count_last = 30;
        $query = $ds->em()->createQuery("SELECT COUNT(u) FROM \App\Entity\User u WHERE u.last_login > ?1");
        $query->setParameter(1, new \DateTime("- $count_last minute"));
        $usersCount = $query->getSingleScalarResult();

        return $this->render('security/login.html.twig', [
                'last_username' => $username ?: $lastUsername,
                'message' => $message,
                'last_page' => $request->request->get('last_page') ?: $this->getTargetPath($session, 'main'),
                'bottom_message' => ($usersCount < 2 ? '' : "There are ${usersCount} users who logged in within the last ${count_last} minutes"),
                'self_register' => (count($ds->queryMany('\App\Entity\AuthDriver', array(
                        'allow_self_registration' => 1))) > 0),
        ]);
    }

    /**
     * @Route("/logout", name="app_logout", priority=10)
     */
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * @Route("/register", name="app_register", priority=10)
     */
    public function register(Request $request, DataStore $ds): Response
    {
        $this->title = 'Register';
        $selected_auth = null;
        $auths = $ds->queryMany('\App\Entity\AuthDriver', ['allow_self_registration' => 1], [
            'created_date' => 'ASC']);

        if (count($auths) == 0) {
            throw new \Exception("Self-registration is disabled");
        }

        if (count($auths) == 1) {
            $selected_auth = $auths[0];
        }

        $post = $request->request;
        if ($post->has('auth')) {
            foreach ($auths as $auth) {
                if ($post->get('auth') == $auth->name) {
                    $selected_auth = $auth;
                    break;
                }
            }
        }

        if ($selected_auth) {
            return $this->redirectToRoute('app_register_driver', ['driver' => $selected_auth->getId()]);
        }

        return $this->render('security/register_form.html.twig', [
                'auths' => $auths,
        ]);
    }

    /**
     * @Route("/register/{driver}", name="app_register_driver", priority=10)
     */
    public function register_driver(HttpClientInterface $httpClient,
                                    GuardAuthenticatorHandler $guardHandler,
                                    OAuth2GuardAuthenticator $oauth2auth,
                                    FormGuardAuthenticator $formauth,
                                    SessionInterface $session, Request $request,
                                    DataStore $ds, $driver): Response
    {
        /** @var AuthDriver $auth */
        $auth = $ds->queryOne('\App\Entity\AuthDriver', ['name' => $driver, 'allow_self_registration' => 1]);
        if (!$auth) {
            return $this->redirectToRoute('app_register');
        }
        $authClass = $auth->getClass();
        $post = $request->request;
        $user = new User();
        $credential = [
            'username' => null,
            'value' => null,
        ];

        if ($post->has('username')) {
            $credential['username'] = $post->get('username');
            $credential['value'] = $post->get('credential');
        } elseif ($authClass instanceof OAuth2DriverInterface) {
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
                return $this->redirectToRoute('app_register_driver', ['driver' => $driver]);
            }
            $user_info = $authClass->fetchUserInfo($access_token, $httpClient, $session);
            $credential['username'] = $user_info['username'];
            $credential['value'] = $user_info['username'];
            $user->setShortname($user_info['username']);
            $user->setEmail($user_info['email']);
            if (($usercred = $ds->queryOne('\App\Entity\UserCredential', ['driver_id' => $driver,
                'credential_value' => $credential['value']]))) {
                $this->addFlash('error', "You have previously registered using the same {$auth->title} account. You have been logged in instead.");
                $guardHandler->authenticateUserAndHandleSuccess($usercred->getUser(), $request, $oauth2auth, 'main');
                return $this->redirectToRoute('app_home');
            }
        }

        if ($credential['username'] && $ds->queryOne('\App\Entity\User', $credential['username'])) {
            $user->errors['username'] = ['Must be unique'];
        }

        $recaptcha_keys = [
            'sitekey' => $_ENV['GOOGLE_RECAPTCHA_SITE_KEY'] ?? null,
            'secretkey' => $_ENV['GOOGLE_RECAPTCHA_SECRET'] ?? null,
        ];
        if ($post->get('_action') == 'Proceed to register') {
            if (!empty($recaptcha_keys['secretkey'])) {
                $recaptcha = new ReCaptcha($recaptcha_keys['secretkey']);
                $resp = $recaptcha->verify($post->get('g-recaptcha-response'));
                if (!$resp->isSuccess()) {
                    $user->errors['recaptcha'] = 'Invalid response: '.join(", ", $resp->getErrorCodes());
                }
            }

            $user->roles = array('ROLE_USER');
            $ds->prepareValidateEntity($user, array('username', 'shortname',
                'email'), $post);
            if ($ds->prepareValidateEntity($user, array(), $post)) {
                $user->password = '';
                $user->created_by = $user;
                $user->created_date = new \DateTime();
                $ds->commit($user);
                $usercred = new UserCredential();
                $usercred->setUser($user);
                $usercred->setDriverId($driver);
                if ($authClass instanceof FormDriverInterface) {
                    $credential['value'] = $formauth->encodePasswordUsingDriver($authClass, $user, $credential['value']);
                }
                $usercred->setCredentialValue($credential['value']);
                $ds->commit($usercred);

                if ($authClass instanceof OAuth2DriverInterface) {
                    $guardHandler->authenticateUserAndHandleSuccess($user, $request, $oauth2auth, 'main');
                    return $this->redirectToRoute('app_home');
                }
                return $this->redirectToRoute('app_login', array('username' => $user->username));
            }
        }

        $this->title = 'Register';
        return $this->render('security/register_form.html.twig', [
                'auth' => $auth,
                'credential' => $credential,
                'user' => $user,
                'errors' => $user->errors,
                'recaptcha' => $recaptcha_keys,
        ]);
    }
}