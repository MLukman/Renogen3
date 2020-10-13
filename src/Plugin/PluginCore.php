<?php

namespace App\Plugin;

use App\Base\Entity;
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

    abstract public function onEntityCreated(Entity $entity);

    abstract public function onEntityUpdated(Entity $entity, array $old_values);

    abstract public function onEntityDeleted(Entity $entity);

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

    public function getPluginEntity($create = false): ?Plugin
    {
        $ds = $this->ds;
        if (!$this->entity) {
            if (($entity = $ds->queryOne('\App\Entity\Plugin', array(
                'project' => $this->project,
                'name' => $this->getName(),
                )))) {
                $this->setPluginEntity($entity);
            } elseif ($create) {
                $this->setPluginEntity(new Plugin($this->project, $this));
            }
        }
        return $this->entity;
    }

    public function savePluginEntity(array $options = null)
    {
        $ds = $this->ds;
        $entity = $this->getPluginEntity(true);
        if (!empty($options)) {
            $this->options = $options;
        }
        $entity->options = $this->options;
        $ds->commit($entity);
    }

    public function deletePluginEntity(): bool
    {
        $ds = $this->ds;
        if (($entity = $this->getPluginEntity())) {
            $ds->deleteEntity($entity);
            $ds->commit();
            $this->entity = null;
            return true;
        }
        return false;
    }
}