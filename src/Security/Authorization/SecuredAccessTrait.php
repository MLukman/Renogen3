<?php

namespace App\Security\Authorization;

/**
 * SecuredAccessTrait implements methods needed by SecuredAccessInterface
 */
trait SecuredAccessTrait
{
    /**
     * The list of user roles who can access
     * @var string[]
     */
    protected $allowedRoles = array('ROLE_ADMIN' => array('any' => true));

    /**
     * The list of usernames who can manage this context
     * @var string[]
     */
    protected $allowedUsernames = array();

    /**
     * Allow user role to access
     * @param string $role User role to allow access
     * @param string $attribute Attribute
     * @return self $this object (to allow method chaining)
     */
    public function addAllowedRole($role, $attribute = 'access')
    {
        $roleStr = (string) $role;
        if (!isset($this->allowedRoles[$roleStr])) {
            $this->allowedRoles[$roleStr] = array();
        }
        $this->allowedRoles[$roleStr][(string) $attribute] = true;
        return $this;
    }

    /**
     * Check if a specific user role is allowed to access
     * @param string $role User role
     * @param string $attribute Attribute
     * @return bool
     */
    public function isRoleAllowed($role, $attribute = 'access')
    {
        if (is_array($role)) {
            foreach ($role as $r) {
                if ($this->isRoleAllowed($r, $attribute)) {
                    return true;
                }
            }
            return false;
        } elseif (is_array($attribute)) {
            foreach ($attribute as $a) {
                if ($this->isRoleAllowed($role, $a)) {
                    return true;
                }
            }
            return false;
        } else {
            $roleStr = (string) $role;
            $attrStr = (string) $attribute;
            if (isset($this->allowedRoles[$roleStr])) {
                return
                    (isset($this->allowedRoles[$roleStr][$attrStr]) ?
                    $this->allowedRoles[$roleStr][$attrStr] : false) ||
                    (isset($this->allowedRoles[$roleStr]['any']) ?
                    $this->allowedRoles[$roleStr]['any'] : false);
            }
            return false;
        }
    }

    /**
     * Allow username to access
     * @param string $username Username to allow access
     * @param string $attribute Attribute
     * @return self $this object (to allow method chaining)
     */
    public function addAllowedUsername($username, $attribute = 'access')
    {
        $usernameStr = (string) $username;
        if (!isset($this->allowedUsernames[$usernameStr])) {
            $this->allowedUsernames[$usernameStr] = array();
        }
        $this->allowedUsernames[$usernameStr][(string) $attribute] = true;
        return $this;
    }

    /**
     * Check if a specific username is allowed to access this context
     * @param string $username Username
     * @param string $attribute Attribute
     * @return bool
     */
    public function isUsernameAllowed($username, $attribute = 'access')
    {
        $usernameStr = (string) $username;
        $attrStr = (string) $attribute;
        if (isset($this->allowedUsernames[$usernameStr])) {
            return
                (isset($this->allowedUsernames[$usernameStr][$attrStr]) ?
                $this->allowedUsernames[$usernameStr][$attrStr] : false) ||
                (isset($this->allowedUsernames[$usernameStr]['any']) ?
                $this->allowedUsernames[$usernameStr]['any'] : false);
        }
        return false;
    }
}