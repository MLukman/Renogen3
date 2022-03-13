<?php

namespace App\Security\Authentication\Driver;

use App\Entity\AuthDriver;
use App\Entity\UserAuthentication;
use App\Security\Authentication\Credentials;
use App\Security\Authentication\Driver;
use App\Service\DataStore;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class Password extends Driver
{

    static public function getTitle()
    {
        return 'Simple username & password';
    }

    static public function getParamConfigs()
    {
        return [];
    }

    static public function checkParams(array $params)
    {
        return null;
    }

    public function canResetPassword()
    {
        return true;
    }

    public function resetPassword(UserAuthentication $user_auth)
    {
        $user_auth->setPassword('');
        return 'User password has been reset. The first password used to login as this user will be the new password.';
    }

    public function authenticate(Credentials $credentials,
                                 UserAuthentication $user_auth,
                                 UserPasswordHasherInterface $passwordEncoder,
                                 AuthDriver $driver, DataStore $ds): bool
    {
        if (!($driver->driverClass() instanceof Password)) {
            return false;
        }

        if (empty($user_auth->getPassword())) {
            $user_auth->credential = $passwordEncoder->hashPassword($user_auth, $credentials->getPassword());
            $ds->commit($user_auth);
            $ds->reloadEntity($user_auth);
            return true;
        }

        if ($passwordEncoder->isPasswordValid($user_auth, $credentials->getPassword())) {
            return true;
        }

        throw new CustomUserMessageAuthenticationException('Invalid credentials');
    }

    public function prepareNewUser(UserAuthentication $user_auth)
    {
        $user_auth->setPassword('');
    }
}