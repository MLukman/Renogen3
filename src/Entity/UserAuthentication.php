<?php

namespace App\Entity;

use App\Base\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=\App\Repository\UserAuthenticationRepository::class)
 * @ORM\Table(name="user_authentications")
 */
class UserAuthentication extends Entity implements UserInterface
{
    /**
     * @ORM\ManyToOne(targetEntity="User",inversedBy="authentications")
     * @ORM\JoinColumn(name="username", referencedColumnName="username", onDelete="CASCADE")
     * @var User
     */
    public $user;

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=25)
     */
    public $username;

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=100)
     */
    public $driver_id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public $credential;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    protected $reset_code;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->username = $user->getUsername();
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    public function setPassword($password)
    {
        $this->credential = $password;
    }

    public function getPassword()
    {
        return $this->credential;
    }

    public function getRoles(): array
    {
        return $this->user->getRoles();
    }

    public function getSalt()
    {

    }

    public function getResetCode()
    {
        return $this->reset_code;
    }

    public function setResetCode($reset_code)
    {
        $this->reset_code = $reset_code;
        return $this;
    }

    public function eraseCredentials()
    {
        
    }

    public function getProject(): ?Project
    {
        return $this->user->getProject();
    }

    public static function getValidationRules(): ?array
    {

    }
}