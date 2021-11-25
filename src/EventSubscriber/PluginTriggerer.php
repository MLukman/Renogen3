<?php

namespace App\EventSubscriber;

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

    public function getSubscribedEvents() : array
    {
        return [
            Events::postPersist,
            Events::preUpdate,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        if (!($entity = $args->getObject()) || !($entity->getProject())) {
            return;
        }

        foreach ($entity->getProject()->plugins as $plugin) {
            $plugin->instance($this->ds, $this->nav)->onEntityCreated($entity);
        }
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        if (!($entity = $args->getObject()) || !($entity->getProject())) {
            return;
        }
        $changed = [];
        foreach ($args->getEntityManager()->getUnitOfWork()->getEntityChangeSet($entity) as $field => $before_after) {
            if ($before_after[0] != $before_after[1]) {
                $changed[$field] = $before_after[0];
            }
        }
        $entity->old_values = $changed;
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        if (!($entity = $args->getObject()) || !($entity->getProject())) {
            return;
        }

        foreach ($entity->getProject()->plugins as $plugin) {
            $plugin->instance($this->ds, $this->nav)->onEntityUpdated($entity, $entity->old_values);
        }
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        if (!($entity = $args->getObject()) || !($entity->getProject())) {
            return;
        }

        foreach ($entity->getProject()->plugins as $plugin) {
            $plugin->instance($this->ds, $this->nav)->onEntityDeleted($entity);
        }
    }
}