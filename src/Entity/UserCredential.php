<?php

namespace App\Entity;

use App\Base\Entity;
use MLukman\MultiAuthBundle\Identity\MultiAuthUserCredentialInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity @ORM\Table(name="user_credentials")
 */
class UserCredential extends Entity implements MultiAuthUserCredentialInterface
{
    /**
     * @ORM\Id @ORM\ManyToOne(targetEntity="User",inversedBy="userCredentials")
     * @ORM\JoinColumn(name="username", referencedColumnName="username", onDelete="CASCADE")
     * @var User
     */
    public $user;

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=100)
     */
    public $driver_id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public $credential_value;

    public function getProject(): ?Project
    {
        return null;
    }

    public static function getValidationRules(): ?array
    {
        return [];
    }

    public function getCredentialValue(): ?string
    {
        return $this->credential_value;
    }

    public function getDriverId()
    {
        return $this->driver_id;
    }

    public function getUser(): \MLukman\MultiAuthBundle\Identity\MultiAuthUserInterface
    {
        return $this->user;
    }

    public function setCredentialValue(string $credential_value)
    {
        $this->credential_value = $credential_value;
    }

    public function setDriverId(string $driver_id)
    {
        $this->driver_id = $driver_id;
    }

    public function setUser(\MLukman\MultiAuthBundle\Identity\MultiAuthUserInterface $user)
    {
        $this->user = $user;
    }
}