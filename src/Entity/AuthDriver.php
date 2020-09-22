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

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'name' => array('trim' => 1, 'required' => 1, 'unique' => 1),
        'title' => array('trim' => 1, 'required' => 1, 'unique' => 1),
        'class' => array('required' => 1),
        'registration_explanation' => array('trim' => 1),
    );

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
            'name' => Rules::new()->required()->trim()->unique(),
            'title' => Rules::new()->required()->trim()->unique(),
            'class' => Rules::new()->required(),
            'registration_explanation' => Rules::new()->trim(),
        ];
    }
}