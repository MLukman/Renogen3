<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity @ORM\Table(name="auth_drivers")
 */
class AuthDriver extends Entity
{
    /**
     * @ORM\Id @ORM\Column(type="string")
     */
    public $name;

    /**
     * @ORM\Column(type="string")
     */
    public $title;

    /**
     * @ORM\Column(type="string")
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
            'name' => Rules::new()->trim()->required()->unique(),
            'title' => Rules::new()->trim()->required()->unique(),
            'class' => Rules::new()->trim()->required(),
            'registration_explanation' => Rules::new()->trim(),
        ];
    }
}