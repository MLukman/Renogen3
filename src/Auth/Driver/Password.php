<?php

namespace App\Auth\Driver;

use App\Auth\Credentials;
use App\Auth\Driver;
use App\Entity\AuthDriver;
use App\Entity\User;
use App\Service\DataStore;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class Password extends Driver
{

    public function __construct(array $params)
    {
        parent::__construct($params);
    }

    static public function getTitle()
    {
        return 'Simple username & password';
    }

    static public function getParamConfigs()
    {
        return array();
    }

    static public function checkParams(array $params)
    {
        return null;
    }

    public function canResetPassword()
    {
        return true;
    }

    public function resetPassword(User $user)
    {
        $user->setPassword('');
        return 'User password has been reset. The first password used to login as this user will be the new password.';
    }

    public function authenticate(Credentials $credentials, User $user,
                                 UserPasswordEncoderInterface $passwordEncoder,
                                 AuthDriver $driver, DataStore $ds): bool
    {
        if (empty($user->getPassword())) {
            $user->setPassword($passwordEncoder->encodePassword($user, $credentials->getPassword()));
            $ds->commit($user);
            $ds->reloadEntity($user);
            return true;
        }

        if ($passwordEncoder->isPasswordValid($user, $credentials->getPassword())) {
            return true;
        }

        throw new CustomUserMessageAuthenticationException('Invalid credentials');
    }

    public function prepareNewUser(User $user)
    {
        $user->setPassword('');
    }
}