<?php

namespace MLukman\MultiAuthBundle;

use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use MLukman\MultiAuthBundle\Identity\MultiAuthUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Traversable;

interface MultiAuthAdapterInterface
{

    public function loadAllDriverInstances(): Traversable;

    public function loadDriverInstance(string $driver_id): ?DriverInstance;

    public function loadAllUserCredentialsForDriverId(string $key): Traversable;

    public function loadUserByUsername(string $username): ?MultiAuthUserInterface;

    public function saveUser(MultiAuthUserInterface $user): void;

    public function saveUserCredential(MultiAuthUserCredentialInterface $user_credential): void;

    public function logUserSuccessfulLogin(MultiAuthUserCredentialInterface $user_credential);

    public function isUsernameBlocked(string $username): bool;

    public function getSecurityUser(MultiAuthUserInterface $user): ?UserInterface;
}