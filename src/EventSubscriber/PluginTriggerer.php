<?php

namespace App\EventSubscriber;

use App\Entity\Deployment;
use App\Entity\Item;
use App\Service\DataStore;
use App\Service\NavigationFactory;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class PluginTriggerer implements EventSubscriberInterface
{
    /**
     * @var DataStore
     */
    protected $ds;

    /**
     *
     * @var NavigationFactory
     */
    protected $nav;

    public function __construct(DataStore $ds, NavigationFactory $nav)
    {
        $this->ds = $ds;
        $this->nav = $nav;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        if (!($entity = $args->getObject())) {
            return;
        }
        if ($entity instanceof Item) {
            foreach ($entity->deployment->project->plugins as $plugin) {
                $plugin->instance($this->ds, $this->nav)->onItemStatusUpdated($entity);
            }
        } elseif ($entity instanceof Deployment) {
            foreach ($entity->project->plugins as $plugin) {
                $plugin->instance($this->ds, $this->nav)->onDeploymentCreated($entity);
            }
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        if (!($entity = $args->getObject())) {
            return;
        }
        if ($entity instanceof Item) {
            if ($entity->old_values['deployment']->id != $entity->deployment->id) {
                foreach ($entity->deployment->project->plugins as $plugin) {
                    $plugin->instance($this->ds, $this->nav)->onItemMoved($entity, $entity->old_values['deployment']);
                }
            }
            if (isset($entity->old_values['status']) && $entity->status != $entity->old_values['status']) {
                foreach ($entity->deployment->project->plugins as $plugin) {
                    $plugin->instance($this->ds, $this->nav)->onItemStatusUpdated($entity, $entity->old_values['status']);
                }
            }
        } elseif ($entity instanceof Deployment) {
            if (isset($entity->old_values['execute_date']) &&
                $entity->datetimeString(false, $entity->execute_date) != $entity->datetimeString(false, $entity->old_values['execute_date'])) {
                foreach ($entity->project->plugins as $plugin) {
                    $plugin->instance($this->ds, $this->nav)->onDeploymentDateChanged($entity, $entity->old_values['execute_date']);
                }
            }
        }
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        if (!($entity = $args->getObject())) {
            return;
        }
        if ($entity instanceof Item) {
            foreach ($entity->deployment->project->plugins as $plugin) {
                $plugin->instance($this->ds, $this->nav)->onItemDeleted($entity);
            }
        } elseif ($entity instanceof Deployment) {
            foreach ($entity->project->plugins as $plugin) {
                $plugin->instance($this->ds, $this->nav)->onDeploymentDeleted($entity);
            }
        }
    }
}