<?php

namespace MLukman\MultiAuthBundle\Authenticator\Driver;

use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use MLukman\MultiAuthBundle\MultiAuthAdapterInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

interface FormDriverInterface
{

    public function authenticate(array $credentials,
                                 MultiAuthUserCredentialInterface $user_credential,
                                 MultiAuthAdapterInterface $adapter,
                                 UserPasswordEncoderInterface $passwordEncoder): bool;

    public function encodePassword(UserPasswordEncoderInterface $passwordEncoder,
                                   UserInterface $securityUser, string $password): string;
}