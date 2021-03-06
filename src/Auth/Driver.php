<?php

namespace App\Auth;

use App\Entity\AuthDriver;
use App\Entity\User;
use App\Service\DataStore;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

abstract class Driver
{
    protected $params = array();

    public function __construct(array $params)
    {
        if (!empty($this->checkParams($params))) {
            throw new \Exception('Invalid parameters');
        }
        $this->params = $params;
    }

    /**
     * @return string Friendly title of the authentication method
     */
    abstract static public function getTitle();

    /**
     * @return array Parameters = array of array(id, label, placeholder)
     */
    abstract static public function getParamConfigs();

    /**
     * Check if params are valid.
     * Return array of error messages using param names as keys.
     * Return null if no error.
     * @param array $params
     * @return array|null
     */
    abstract static public function checkParams(array $params);

    abstract public function authenticate(Credentials $credentials, User $user,
                                          UserPasswordEncoderInterface $passwordEncoder,
                                          AuthDriver $driver, DataStore $ds): bool;

    /**
     * Prepare a newly created user record before saving
     * @param User $user The instance of user record
     */
    abstract public function prepareNewUser(User $user);

    /**
     * Can this driver support resetting password?
     * @return boolean If this driver supports resetting password
     */
    public function canResetPassword()
    {
        return false;
    }

    /**
     * Perform password reset on a specific user
     * @param User $user
     * @return string|null Success message. Null if failed.
     */
    public function resetPassword(User $user)
    {
        
    }
}