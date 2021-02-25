<?php

namespace MLukman\MultiAuthBundle\DriverClass;

use MLukman\MultiAuthBundle\Authenticator\Driver\FormDriverInterface;
use MLukman\MultiAuthBundle\DriverClass;
use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use MLukman\MultiAuthBundle\MultiAuthAdapterInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class LDAPBind extends DriverClass implements FormDriverInterface
{

    static public function getTitle()
    {
        return 'LDAP integration';
    }

    static public function getParamConfigs()
    {
        return [
            ['connstr', 'Server Connection String', 'Using format protocol://host:port'],
            ['dn', 'DN String', 'DN String (use {username} where the username should be substituted)'],
            ['title', 'Server Title', 'The user-friendly title by which the LDAP server is called',
                'LDAP'],
        ];
    }

    static public function checkParams(array $params)
    {
        $errors = [];
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

    public function prepareNewUser(MultiAuthUserCredentialInterface $user_credential)
    {
        $user_credential->setCredentialValue('-');
    }

    public function authenticate(array $credentials,
                                 MultiAuthUserCredentialInterface $user_credential,
                                 MultiAuthAdapterInterface $adapter,
                                 UserPasswordEncoderInterface $passwordEncoder): bool
    {
        $ldap = Ldap::create('ext_ldap', [
                'connection_string' => $this->instance->parameters['connstr'],
        ]);
        $username = $credentials['username'];
        $password = $credentials['password'];

        if ('' === $password) {
            throw new CustomUserMessageAuthenticationException('The presented password must not be empty.');
        }

        try {
            $dn = str_replace('{username}', $ldap->escape($username, '', LdapInterface::ESCAPE_DN), $this->instance->parameters['dn']);
            $ldap->bind($dn, $password);
        } catch (ConnectionException $e) {
            throw new CustomUserMessageAuthenticationException("Unable to login via {$this->instance->parameters['title']} using the password that you provided.");
        }

        return true;
    }

    public function resetPassword(MultiAuthUserCredentialInterface $user_credential)
    {

    }

    public function getLoginDisplay(): array
    {
        return [
            'type' => 'form',
            'params' => [
                'label' => $this->instance->getTitle(),
                'value' => $this->instance->getId(),
            ],
        ];
    }

    public function encodePassword(UserPasswordEncoderInterface $passwordEncoder,
                                   \Symfony\Component\Security\Core\User\UserInterface $securityUser,
                                   string $password): string
    {
        return $password;
    }
}