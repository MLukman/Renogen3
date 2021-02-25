<?php

namespace MLukman\MultiAuthBundle\DriverClass;

use MLukman\MultiAuthBundle\Authenticator\Driver\FormDriverInterface;
use MLukman\MultiAuthBundle\DriverClass;
use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use MLukman\MultiAuthBundle\MultiAuthAdapterInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

class Internal extends DriverClass implements FormDriverInterface
{

    public function authenticate(array $credentials,
                                 MultiAuthUserCredentialInterface $user_credential,
                                 MultiAuthAdapterInterface $adapter,
                                 UserPasswordEncoderInterface $passwordEncoder): bool
    {
        $securityUser = $adapter->getSecurityUser($user_credential->getUser());
        if (empty($user_credential->getCredentialValue())) {
            $user_credential->setCredentialValue($this->encodePassword($passwordEncoder, $securityUser, $credentials['password']));
            $adapter->saveUserCredential($user_credential);
            return true;
        }

        if ($passwordEncoder->isPasswordValid($securityUser, $credentials['password'])) {
            return true;
        }

        throw new CustomUserMessageAuthenticationException('Invalid credentials');
    }

    public function encodePassword(UserPasswordEncoderInterface $passwordEncoder,
                                   UserInterface $securityUser, string $password): string
    {
        return $passwordEncoder->encodePassword($securityUser, $password);
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
        return [];
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
        return [
            'type' => 'form',
            'params' => [
                'label' => $this->instance->getTitle(),
                'value' => $this->instance->getId(),
            ],
        ];
    }
}