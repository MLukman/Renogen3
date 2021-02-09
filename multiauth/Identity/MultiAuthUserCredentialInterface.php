<?php

namespace MLukman\MultiAuthBundle\Identity;

interface MultiAuthUserCredentialInterface
{

    public function getUser(): MultiAuthUserInterface;

    public function setUser(MultiAuthUserInterface $user);

    public function getDriverId();

    public function setDriverId(string $driver_id);

    public function getCredentialValue(): string;

    public function setCredentialValue(string $credential_value);
}