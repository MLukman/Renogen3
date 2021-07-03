<?php

namespace App\Security\Authorization;

/**
 * SecuredAccessInterface defines class signature for object that holds authorization
 * information such as which users and roles are allowed access to the object.
 */
interface SecuredAccessInterface
{

    /**
     * Allow user role to access
     * @param string $role User role to allow access
     * @param string $attribute Attribute
     * @return self $this object (to allow method chaining)
     */
    public function addAllowedRole($role, $attribute);

    /**
     * Check if a specific user role is allowed to access
     * @param string $role User role
     * @param string $attribute Attribute
     * @return bool
     */
    public function isRoleAllowed($role, $attribute);

    /**
     * Allow username to access
     * @param string $username Username to allow access
     * @param string $attribute Attribute
     * @return self $this object (to allow method chaining)
     */
    public function addAllowedUsername($username, $attribute);

    /**
     * Check if a specific username is allowed to access this context
     * @param string $username Username
     * @param string $attribute Attribute
     * @return bool
     */
    public function isUsernameAllowed($username, $attribute);
}