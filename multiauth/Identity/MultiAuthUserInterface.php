<?php

namespace MLukman\MultiAuthBundle\Identity;

interface MultiAuthUserInterface
{

    public function getUsername(): string;

    public function getFullname(): string;

    public function setFullname(string $fullname): void;

    public function getCredentials(): \ArrayAccess;

    public function addCredentials(string $driver_id,
                                   MultiAuthUserCredentialInterface $credential): void;

    public function getCredentialByDriverId(string $driver_id): MultiAuthUserCredentialInterface;

    public function getRoles(): array;
}