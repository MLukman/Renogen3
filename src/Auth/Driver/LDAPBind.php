<?php

namespace App\Auth\Driver;

use App\Auth\Credentials;
use App\Auth\Driver;
use App\Entity\AuthDriver;
use App\Entity\User;
use App\Service\DataStore;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class LDAPBind extends Driver
{

    static public function getTitle()
    {
        return 'LDAP integration';
    }

    static public function getParamConfigs()
    {
        return array(
            array('connstr', 'Server Connection String', 'Using format protocol://host:port'),
            array('dn', 'DN String', 'DN String (use {username} where the username should be substituted)'),
            array('title', 'Server Title', 'The user-friendly title by which the LDAP server is called',
                'LDAP'),
        );
    }

    static public function checkParams(array $params)
    {
        $errors = array();
        if (!isset($params['connstr']) || empty($params['connstr'])) {
            $errors['connstr'] = 'Server connection string is required';
        } elseif (0 === preg_match('/(ldap|ldaps):\/\/([a-zA-Z][a-zA-Z0-9-.]+):([0-9]{2,5})/', $params['connstr'])
            && 0 === preg_match('/(ldap|ldaps):\/\/([0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}.[0-9]{1,3}):([0-9]{2,5})/', $params['connstr'])) {
            $errors['connstr'] = "Server connection string must be in the format of protocol://host:port where protocol is either 'ldap' or 'ldaps', host is either a hostname or an IP address while port must be an integer between 2 to 5 digits long.";
        }
        if (!isset($params['dn']) || empty($params['dn']) || strpos($params['dn'], '{username}')
            === false) {
            $errors['dn'] = 'DN string is required and must contain {username}';
        }
        return (empty($errors) ? null : $errors);
    }

    public function prepareNewUser(User $user)
    {
        $user->setPassword('-');
    }

    public function authenticate(Credentials $credentials, User $user,
                                 UserPasswordEncoderInterface $passwordEncoder,
                                 AuthDriver $driver, DataStore $ds): bool
    {
        $ldap = Ldap::create('ext_ldap', [
                'connection_string' => $driver->parameters['connstr'],
        ]);
        $username = $credentials->getUsername();
        $password = $credentials->getPassword();

        if ('' === $password) {
            throw new CustomUserMessageAuthenticationException('The presented password must not be empty.');
        }

        try {
            $dn = str_replace('{username}', $ldap->escape($username, '', LdapInterface::ESCAPE_DN), $driver->parameters['dn']);
            $ldap->bind($dn, $password);
        } catch (ConnectionException $e) {
            throw new CustomUserMessageAuthenticationException("Unable to login via {$driver->parameters['title']} using the password that you provided.");
        }
    }
}