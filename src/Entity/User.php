<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=\App\Repository\UserRepository::class) @ORM\Table(name="users")
 */
class User extends Entity implements UserInterface
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
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    protected $password = '';

    /**
     * @ORM\Column(type="string", length=64)
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
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'username' => array('trim' => 1, 'required' => 1, 'maxlen' => 25, 'unique' => 1,
            'preg_match' => array('/^[0-9a-zA-Z][0-9a-zA-Z\._-]*$/', 'Username must start with an alphanumerical character and contains only alphanumeric, underscores, dashes and dots')),
        'shortname' => array('trim' => 1, 'required' => 1, 'truncate' => 100, 'unique' => 1),
        'email' => array('required' => 1, 'maxlen' => 50, 'unique' => 1, 'email' => 1),
        'auth' => array('required' => 1),
        'roles' => array('required' => 1),
    );

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
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
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
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

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public static function getValidationRules(): ?array
    {
        return [
            'username' => Rules::new()->required()->trim()->unique()->maxlen(25)
                ->pregmatch('/^[0-9a-zA-Z][0-9a-zA-Z\._-]*$/', 'Username must start with an alphanumerical character and contains only alphanumeric, underscores, dashes and dots'),
            'shortname' => Rules::new()->required()->trim()->unique()->truncate(100),
            'email' => Rules::new()->required()->trim()->unique()->maxlen(50)->email(),
            'auth' => Rules::new()->required(),
            'roles' => Rules::new()->required(),
        ];
    }
}