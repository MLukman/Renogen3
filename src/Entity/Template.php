<?php

namespace App\Entity;

use App\Base\Entity;
use App\Entity\Activity;
use App\Service\DataStore;
use App\Validation\Rules;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="templates")
 */
class Template extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=Ramsey\Uuid\Doctrine\UuidGenerator::class)
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity="Project",inversedBy="templates")
     * @ORM\JoinColumn(name="project_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Project
     */
    public $project;

    /**
     * @ORM\Column(type="string", length=100)
     */
    public $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @ORM\Column(type="string", length=100)
     */
    public $class;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    public $stage = 0;

    /**
     * @ORM\Column(type="integer")
     */
    public $priority;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    public $parameters;

    /**
     * @ORM\Column(type="boolean", options={"default":"0"})
     */
    public $disabled = false;

    /**
     * @ORM\OneToMany(targetEntity="Activity", mappedBy="template", indexBy="id")
     * @var ArrayCollection|Activity[]
     */
    public $activities = null;

    public function __construct(Project $project)
    {
        $this->project    = $project;
        $this->activities = new ArrayCollection();
    }

    public function templateClass(DataStore $ds)
    {
        return $ds->getActivityTemplateClass($this->class);
    }

    public function cascadeDelete(): array
    {
        return $this->activities->toArray();
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public static function getValidationRules(): ?array
    {
        return [
            'class' => Rules::new()->required(),
            'title' => Rules::new()->trim()->required()->unique(array(
                'project',
                'disabled' => 0
            ))->maxlen(100),
        ];
    }

    public function isSafeToDelete(): bool
    {
        return $this->activities->count() == 0;
    }
}