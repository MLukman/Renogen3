<?php

namespace MLukman\MultiAuthBundle;

interface DriverInstance
{

    public function getId(): string;

    public function getTitle() : string;

    public function getClass(): DriverClass;

    public function getParameters(): array;

    public function setParameters(array $parameters): void;
}