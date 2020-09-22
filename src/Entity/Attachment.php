<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="item", inversedBy="attachments")
 * })
 */
class Attachment extends FileLink
{

    public function __construct(Item $item)
    {
        $this->item = $item;
        $this->validation_rules['description'] = array('required' => 1, 'trim' => 1);
    }

    public function isUsernameAllowed($username, $attribute)
    {
        return parent::isUsernameAllowed($username, $attribute) ||
            $this->item->isUsernameAllowed($username, $attribute);
    }

    public function downloadUrl(\App\Service\NavigationFactory $nav)
    {
        return $nav->entityPath('attachment_download', $this);
    }

    public function getProject(): ?Project
    {
        return $this->item->getProject();
    }
}