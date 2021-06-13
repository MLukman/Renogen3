<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\User;
use App\Service\DataStore;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class SecurityController extends RenoController
{

    use TargetPathTrait;

    /**
     * @Route("/login/{reset_code}", name="app_login", priority=10)
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
            $user->auth = 'password';
            $user->password = '';
            $user->created_by = $user;
            $user->created_date = new \DateTime();
            $this->updateResetCode($user);
            $ds->commit($user);
            return $this->redirectToRoute('app_login', array('reset_code' => $user->reset_code));
        }

        $message = array();
        $username = '';
        if (($error = $authenticationUtils->getLastAuthenticationError())) {
            $message['text'] = $error->getMessage();
            $message['negative'] = true;
        } elseif (!empty($reset_code) && $request->request->count() == 0) {
            $reset_user = $ds->queryOne('\App\Entity\User', ['reset_code' => $reset_code]);
            if ($reset_user) {
                $username = $reset_user->username;
                $reset_user->setPassword('');
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
                'last_page' => $request->request->get('last_page') ?: $this->getTargetPath($session, 'main'),
                'bottom_message' => ($usersCount < 2 ? '' : "There are ${usersCount} users who logged in within the last ${count_last} minutes"),
                'self_register' => (count($ds->queryMany('\App\Entity\AuthDriver', array(
                        'allow_self_registration' => 1))) > 0),
                'can_reset_password' => $this->canResetPassword(),
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
        $recaptcha_keys = array(
            'sitekey' => $_ENV['GOOGLE_RECAPTCHA_SITE_KEY'] ?? null,
            'secretkey' => $_ENV['GOOGLE_RECAPTCHA_SECRET'] ?? null,
        );

        $post = $request->request;
        $selected_auth = null;
        $auths = $ds->queryMany('\App\Entity\AuthDriver', array(
            'allow_self_registration' => 1));

        if (count($auths) == 0) {
            throw new \Exception("Self-registration is disabled");
        }

        $user = new User();
        if (count($auths) == 1) {
            $selected_auth = $auths[0];
        }

        if ($post->has('auth')) {
            foreach ($auths as $auth) {
                if ($post->get('auth') == $auth->name) {
                    $selected_auth = $auth;
                    break;
                }
            }
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
            $ds->prepareValidateEntity($user, array('auth', 'username', 'shortname',
                'email'), $post);
            if ($ds->queryOne('\App\Entity\User', $user->username)) {
                $user->errors['username'] = ['Must be unique'];
            }
            if ($ds->prepareValidateEntity($user, array(), $post)) {
                $user->password = '';
                $user->created_by = $user;
                $user->created_date = new \DateTime();
                $this->updateResetCode($user);
                $ds->commit($user);
                return $this->redirectToRoute('app_login', ['reset_code' => $user->reset_code]);
            }
        }

        if ($selected_auth) {
            $user->auth = $selected_auth->name;
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
     * @Route("/login/resetpwd/", name="app_resetpwd", priority=100)
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
                if (!$reset_user) {
                    $errors['email'] = 'Not found';
                }
            }

            if (empty($errors)) {
                $this->updateResetCode($reset_user);
                $this->ds->commit($reset_user);
                $reset_url = $this->nav->url('app_login', ['reset_code' => $reset_user->reset_code]);
                $email = (new TemplatedEmail())
                    ->from('tm.unifi.dev@gmail.com')
                    ->to($reset_email)
                    ->subject("Hello {$reset_user->shortname}! It seems that you forgot your Renogen's password?")
                    ->htmlTemplate('security/resetpwd_email.html.twig')
                    ->context([
                    'reset_user' => $reset_user,
                    'reset_url' => $reset_url,
                ]);
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

    private function updateResetCode(User $user)
    {
        $user->setResetCode(md5($user->getEmail().':'.time()));
    }
}