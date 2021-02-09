<?php

namespace MLukman\MultiAuthBundle\DriverClass;

use MLukman\MultiAuthBundle\Authenticator\Driver\FormDriverInterface;
use MLukman\MultiAuthBundle\DriverClass;
use MLukman\MultiAuthBundle\DriverInstance;
use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use MLukman\MultiAuthBundle\MultiAuthAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class Internal extends DriverClass implements FormDriverInterface
{

    public function authenticate(array $credentials,
                                 MultiAuthUserCredentialInterface $user_credential,
                                 UserPasswordEncoderInterface $passwordEncoder,
                                 DriverInstance $driver,
                                 MultiAuthAdapterInterface $adapter): bool
    {
        $securityUser = $adapter->getSecurityUser($user_credential->getUser());
        if (empty($user_credential->getCredentialValue())) {
            $user_credential->setCredentialValue($passwordEncoder->encodePassword($securityUser, $credentials['password']));
            $adapter->saveUserCredential($user_credential);
            return true;
        }

        if ($passwordEncoder->isPasswordValid($securityUser, $credentials['password'])) {
            return true;
        }

        throw new CustomUserMessageAuthenticationException('Invalid credentials');
    }

    public function prepareNewUser(MultiAuthUserCredentialInterface $user_credential)
    {
        $user_credential->setCredentialValue('');
    }

    public static function checkParams(array $params)
    {
        return null;
    }

    public static function getParamConfigs(): array
    {
        return array();
    }

    public static function getTitle(): string
    {
        return 'Internal User Database';
    }

    public function canResetPassword(): bool
    {
        return true;
    }

    public function resetPassword(MultiAuthUserCredentialInterface $user_credential)
    {
        $user_credential->setCredentialValue('');
        return 'User password has been reset. The first password used to login as this user will be the new password.';
    }

    public function getLoginDisplay(): array
    {
        return array(
            'type' => 'form',
            'params' => array(
                'label' => $this->instance->getTitle(),
                'value' => $this->instance->getId(),
            ),
        );
    }

    public function handleRequest(Request $request): ?Response
    {
        return null;
    }
}