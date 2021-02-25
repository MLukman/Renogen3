<?php

namespace MLukman\MultiAuthBundle;

use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;

abstract class DriverClass
{
    protected $params = [];

    /** #var DriverInstance */
    protected $instance = null;

    public function __construct(array $params, DriverInstance $instance)
    {
        if (!empty($this->checkParams($params))) {
            throw new \Exception('Invalid parameters');
        }
        $this->params = $params;
        $this->instance = $instance;
    }

    /**
     * @return
     */
    abstract public function getLoginDisplay(): array;

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
     * @param MultiAuthUserCredentialInterface $user_credential The instance of user record
     */
    abstract public function prepareNewUser(MultiAuthUserCredentialInterface $user_credential);

    /**
     * Can this driver support resetting password?
     * @return boolean If this driver supports resetting password
     */
    public function canResetPassword(): bool
    {
        return false;
    }

    /**
     * Perform password reset on a specific user
     * @param MultiAuthUserCredentialInterface $user_credential
     * @return string|null Success message. Null if failed.
     */
    abstract public function resetPassword(MultiAuthUserCredentialInterface $user_credential);
}