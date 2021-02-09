<?php

namespace MLukman\MultiAuthBundle\Authenticator\Driver;

use MLukman\MultiAuthBundle\DriverInstance;
use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use MLukman\MultiAuthBundle\MultiAuthAdapterInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

interface FormDriverInterface
{

    public function authenticate(array $credentials,
                                 MultiAuthUserCredentialInterface $user_credential,
                                 UserPasswordEncoderInterface $passwordEncoder,
                                 DriverInstance $driver,
                                 MultiAuthAdapterInterface $adapter): bool;
}