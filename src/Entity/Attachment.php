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
    }

    public function isUsernameAllowed($username, $attribute): bool
    {
        return parent::isUsernameAllowed($username, $attribute) ||
            $this->item->isUsernameAllowed($username, $attribute);
    }

    public function downloadUrl(\App\Service\NavigationFactory $nav): string
    {
        return $nav->entityPath('attachment_download', $this);
    }

    public function getProject(): ?Project
    {
        return $this->item->getProject();
    }

    public static function getValidationRules(): ?array
    {
        $from_parent = parent::getValidationRules();
        $from_parent['description']->required();
        return $from_parent;
    }
}