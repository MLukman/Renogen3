<?php

namespace App\Security\Authentication;

use App\Entity\AuthDriver;
use App\Entity\User;
use App\Entity\UserAuthentication;

abstract class Driver
{
    protected $params = array();
    protected $instance;

    public function __construct(array $params, AuthDriver $instance)
    {
        if (!empty($this->checkParams($params))) {
            throw new \Exception('Invalid parameters');
        }
        $this->instance = $instance;
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

    /**
     * Prepare a newly created user record before saving
     * @param UserAuthentication $user_auth The instance of user record
     */
    abstract public function prepareNewUser(UserAuthentication $user_auth);

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
     * @param UserAuthentication $user_auth
     * @return string|null Success message. Null if failed.
     */
    public function resetPassword(UserAuthentication $user_auth)
    {
        
    }
}