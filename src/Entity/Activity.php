<?php

namespace App\Entity;

use App\Base\Actionable;
use App\Validation\Rules;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="activities")
 */
class Activity extends Actionable
{
    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    public $title;

    /**
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="activities", fetch="EAGER")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Item
     */
    public $item;

    /**
     * @ORM\OneToMany(targetEntity="ActivityFile", mappedBy="activity", indexBy="classifier", orphanRemoval=true, cascade={"persist", "remove"}, fetch="EXTRA_LAZY")
     * @var ArrayCollection|ActivityFile[]
     */
    public $files = null;

    /**
     * @ORM\ManyToOne(targetEntity="RunItem", inversedBy="activities")
     * @ORM\JoinColumn(name="runitem_id", referencedColumnName="id", onDelete="SET NULL")
     * @var RunItem
     */
    public $runitem;
    public $fileClass      = '\App\Entity\ActivityFile';
    public $actionableType = 'activity';

    public function __construct(Item $item)
    {
        $this->item  = $item;
        $this->files = new ArrayCollection();
    }

    public function displayTitle()
    {
        return $this->title ?: $this->template->title;
    }

    public function isUsernameAllowed($username, $attribute):bool
    {
        return parent::isUsernameAllowed($username, $attribute) ||
            $this->item->isUsernameAllowed($username, $attribute);
    }

    public function getProject(): ?Project
    {
        return $this->item->getProject();
    }

    public static function getValidationRules(): ?array
    {
        return [
            'title' => Rules::new()->trim()->required()->maxlen(100),
            'template' => Rules::new()->trim()->required(),
        ];
    }
}