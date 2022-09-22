<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="activity", inversedBy="files")
 * })
 */
class ActivityFile extends FileLink
{

    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    public function downloadUrl(\App\Service\NavigationFactory $nav): string
    {
        return $nav->entityPath('app_activity_file_download', $this);
    }

    public function getProject(): ?Project
    {
        return $this->activity->getProject();
    }
}