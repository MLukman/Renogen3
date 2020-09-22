<?php

namespace App\Entity;

use App\Base\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity @ORM\Table(name="user_projects")
 */
class UserProject extends Entity
{
    /**
     * @ORM\Id @ORM\ManyToOne(targetEntity="User",inversedBy="userProjects")
     * @ORM\JoinColumn(name="username", referencedColumnName="username", onDelete="CASCADE")
     * @var User
     */
    public $user;

    /**
     * @ORM\Id @ORM\ManyToOne(targetEntity="Project",inversedBy="userProjects")
     * @ORM\JoinColumn(name="project_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Project
     */
    public $project;

    /**
     * @ORM\Column(type="string", length=16)
     */
    public $role;

    public function __construct(Project $project, User $user)
    {
        $this->project = $project;
        $this->user = $user;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public static function getValidationRules(): ?array
    {
        return null;
    }
}