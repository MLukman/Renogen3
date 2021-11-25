<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="users")
 */
class User extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=25)
     */
    protected $username;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    protected $shortname;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    protected $email;

    /**
     * @ORM\Column(type="json")
     */
    protected $roles = [];

    /**
     * @ORM\Column(type="boolean", options={"default":0})
     */
    protected $admin = 0;

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    protected $password = '';

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $auth;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $blocked;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $last_login;

    /**
     * @ORM\OneToMany(targetEntity="UserProject", mappedBy="user", orphanRemoval=true)
     * @var ArrayCollection|UserProject[]
     */
    public $userProjects = null;

    /**
     * @ORM\OneToMany(targetEntity="UserAuthentication", mappedBy="user", orphanRemoval=true, indexBy="driver_id")
     * @var ArrayCollection|UserAuthentication[]
     */
    public $authentications = null;

    public function __construct()
    {
        $this->userProjects = new ArrayCollection();
        $this->authentications = new ArrayCollection();
    }

    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        // guarantee every user at least has ROLE_USER
        $roles = ['ROLE_USER'];

        // add admin role
        if ($this->admin) {
            $roles[] = 'ROLE_ADMIN';
        }

        return $roles;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getShortname()
    {
        return $this->shortname;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getAuth()
    {
        return $this->auth;
    }

    public function getBlocked()
    {
        return $this->blocked;
    }

    public function getLast_login()
    {
        return $this->last_login;
    }

    public function setShortname($shortname)
    {
        $this->shortname = $shortname;
        return $this;
    }

    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    public function setAuth($auth)
    {
        $this->auth = $auth;
        return $this;
    }

    public function setBlocked($blocked)
    {
        $this->blocked = $blocked;
        return $this;
    }

    public function setLast_login($last_login)
    {
        $this->last_login = $last_login;
        return $this;
    }

    public function getProject(): ?Project
    {
        return null;
    }

    public function getName()
    {
        return $this->shortname ?: $this->username;
    }

    public function isAdmin()
    {
        return $this->admin;
    }

    public static function getValidationRules(): ?array
    {
        return [
            'username' => Rules::new()->trim()->required()->unique()->maxlen(25)
                ->pregmatch('/^[0-9a-zA-Z][0-9a-zA-Z\._-]*$/', 'Username must start with an alphanumerical character and contains only alphanumeric, underscores, dashes and dots'),
            'shortname' => Rules::new()->trim()->required()->unique()->truncate(100),
            'email' => Rules::new()->trim()->required()->unique()->maxlen(50)->email(),
            //'admin' => Rules::new()->default(0),
        ];
    }
}