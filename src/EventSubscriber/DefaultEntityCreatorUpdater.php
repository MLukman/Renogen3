<?php

namespace App\EventSubscriber;

use App\Base\Entity;
use App\Service\DataStore;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class DefaultEntityCreatorUpdater implements EventSubscriberInterface
{
    /**
     * @var DataStore
     */
    protected $ds;

    public function __construct(DataStore $ds)
    {
        $this->ds = $ds;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        if (($entity = $args->getObject()) && $entity instanceof Entity) {
            if (empty($entity->created_by)) {
                $entity->created_by = $this->ds->currentUserEntity();
                $entity->created_date = new \DateTime();
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        if (($entity = $args->getObject()) && $entity instanceof Entity) {
            $entity->updated_by = $this->ds->currentUserEntity();
            $entity->updated_date = new \DateTime();
        }
    }
}