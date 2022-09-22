<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="runitem", inversedBy="files")
 * })
 */
class RunItemFile extends FileLink
{

    public function __construct(RunItem $runitem)
    {
        $this->runitem = $runitem;
    }

    public function downloadUrl(\App\Service\NavigationFactory $nav): string
    {
        return $nav->entityPath('app_runitem_file_download', $this);
    }

    public function getProject(): ?Project
    {
        return $this->runitem->getProject();
    }
}