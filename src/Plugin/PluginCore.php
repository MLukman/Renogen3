<?php

namespace App\Plugin;

use App\Entity\Deployment;
use App\Entity\Item;
use App\Entity\Plugin;
use App\Entity\Project;
use App\Service\DataStore;
use App\Service\NavigationFactory;

abstract class PluginCore
{
    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var DataStore
     */
    protected $ds;

    /**
     *
     * @var NavigationFactory
     */
    protected $nav;

    /**
     * @var Project
     */
    protected $project;

    /**
     * @var Plugin
     */
    protected $entity;

    public function __construct(DataStore $ds, NavigationFactory $nav,
                                Project $project, ?Plugin $entity = null)
    {
        $this->ds = $ds;
        $this->nav = $nav;
        $this->project = $project;
        if ($entity) {
            $this->setPluginEntity($entity);
        }
    }

    abstract static function getIcon();

    abstract static function getTitle();

    abstract public function onDeploymentCreated(Deployment $deployment);

    abstract public function onDeploymentDateChanged(Deployment $deployment,
                                                     \DateTime $old_date);

    abstract public function onDeploymentDeleted(Deployment $deployment);

    abstract public function onItemStatusUpdated(Item $item, $old_status = null);

    abstract public function onItemMoved(Item $item, Deployment $old_deployment);

    abstract public function onItemDeleted(Item $item);

    abstract static public function availableActions(): array;

    abstract public function handleConfigure(PluginAction $action);

    abstract public function handleAction(PluginAction $action);

    public function getName()
    {
        $reflection = new \ReflectionClass($this);
        return basename(dirname($reflection->getFileName()));
    }

    public function getTemplateFileBasePath()
    {
        return '@plugin/'.$this->getName().'/';
    }

    public function getOptions($key = null)
    {
        if ($key) {
            return isset($this->options[$key]) ? $this->options[$key] : null;
        }
        return $this->options;
    }

    public function setOptions(array $options, $replace = false)
    {
        $this->options = ($replace ? $options : array_merge($this->options, $options));
    }

    protected function setPluginEntity(Plugin $entity)
    {
        $this->entity = $entity;
        $this->setOptions($this->entity->options);
    }

    public function getPluginEntity(DataStore $ds, $create = false): ?Plugin
    {
        if (!$this->entity) {
            if (($entity = $ds->queryOne('\App\Entity\Plugin', array(
                'project' => $this->project,
                'name' => $this->getName(),
                )))) {
                $this->setPluginEntity($entity);
            }
        }
        return $this->entity ?: ($create ? new Plugin($this->project, $this) : null);
    }

    public function savePluginEntity(DataStore $ds, array $options = null)
    {
        $entity = $this->getPluginEntity($ds, true);
        if (!empty($options)) {
            $this->options = $options;
        }
        $entity->options = $this->options;
        $ds->commit($entity);
    }

    public function deletePluginEntity(DataStore $ds): bool
    {
        if (($entity = $this->getPluginEntity($ds))) {
            $ds->deleteEntity($entity);
            $ds->commit();
            $this->entity = null;
            return true;
        }
        return false;
    }
}