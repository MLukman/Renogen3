<?php

namespace App\Validation;

class ValidationException extends \RuntimeException
{
    private $entity;
    private $errors = [];

    public function __construct($entity, array $errors)
    {
        $this->entity = $entity;
        $this->errors = $errors;
        $entityClass = new \ReflectionClass($entity);
        parent::__construct(sprintf("The %s entity failed validations.", $entityClass->getShortName()));
    }

    function getEntity()
    {
        return $this->entity;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}