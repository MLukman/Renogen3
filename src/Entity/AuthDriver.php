<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity @ORM\Table(name="auth_drivers")
 */
class AuthDriver extends Entity implements \MLukman\MultiAuthBundle\DriverInstance
{
    /**
     * @ORM\Id @ORM\Column(type="string", length=30)
     */
    public $name;

    /**
     * @ORM\Column(type="string", length=100)
     */
    public $title;

    /**
     * @ORM\Column(type="string", length=255)
     */
    public $description;

    /**
     * @ORM\Column(type="string", length=255)
     */
    public $class;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    public $parameters = array();

    /**
     * @ORM\Column(type="boolean", options={"default" : 0})
     */
    public $allow_self_registration = false;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public $registration_explanation;

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function driverClass(): \App\Auth\Driver
    {
        return new $this->class($this->parameters ?: array());
    }

    public function getProject(): ?Project
    {
        return null;
    }

    public static function getValidationRules(): ?array
    {
        return [
            'name' => Rules::new()->trim()->required()->unique()->maxlen(30),
            'title' => Rules::new()->trim()->required()->unique()->maxlen(100),
            'description' => Rules::new()->trim()->required()->truncate(255),
            'class' => Rules::new()->trim()->required(),
            'registration_explanation' => Rules::new()->trim(),
        ];
    }

    public function getClass(): \MLukman\MultiAuthBundle\DriverClass
    {
        return new $this->class($this->parameters ?: array(), $this);
    }

    public function getId(): string
    {
        return $this->name;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}