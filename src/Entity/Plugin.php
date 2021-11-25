<?php

namespace App\Entity;

use App\Base\Entity;
use App\Plugin\PluginCore;
use App\Service\DataStore;
use App\Service\NavigationFactory;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="plugins")
 */
class Plugin extends Entity
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Project",inversedBy="plugins")
     * @ORM\JoinColumn(name="project_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Project
     */
    public $project;

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=100)
     */
    public $name;

    /**
     * @ORM\Column(type="string", length=250)
     */
    public $class;

    /**
     * @var PluginCore
     */
    protected $instance;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    public $options;

    public function __construct(Project $project,
                                PluginCore $pluginInstance = null)
    {
        $this->project = $project;
        if ($pluginInstance) {
            $this->setInstance($pluginInstance);
        }
    }

    /**
     *
     * @return PluginCore
     */
    public function instance(DataStore $ds, NavigationFactory $nav)
    {
        if (!$this->instance) {
            $cls = $this->class;
            $this->setInstance(new $cls($ds, $nav, $this->project, $this));
        }
        return $this->instance;
    }

    public function setInstance(PluginCore $pluginInstance)
    {
        $this->instance = $pluginInstance;
        $this->class = get_class($this->instance);
        $this->name = $this->instance->getName();
        $this->options = $this->instance->getOptions();
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