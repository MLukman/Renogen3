<?php

namespace App\Entity;

use App\Base\Entity;
use App\Security\Authentication\Driver;
use App\Validation\Rules;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="auth_drivers")
 */
class AuthDriver extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=30)
     */
    public $name;

    /**
     * @ORM\Column(type="string", length=50)
     */
    public $title;

    /**
     * @ORM\Column(type="string")
     */
    public $class;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    public $parameters = [];

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

    public function driverClass(): Driver
    {
        return new $this->class($this->parameters ?: [], $this);
    }

    public function getProject(): ?Project
    {
        return null;
    }

    public static function getValidationRules(): ?array
    {
        return [
            'name' => Rules::new()->trim()->required()->maxlen(30)->unique(),
            'title' => Rules::new()->trim()->required()->maxlen(50)->unique(),
            'class' => Rules::new()->trim()->required(),
            'registration_explanation' => Rules::new()->trim(),
        ];
    }
}