<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity @ORM\Table(name="item_comments")
 */
class ItemComment extends Entity
{
    /**
     * @ORM\Id @ORM\Column(type="string") @ORM\GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="comments")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Item
     */
    public $item;

    /**
     * @ORM\Column(type="text")
     */
    public $text;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    public $deleted_date = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $event;

    public function __construct(Item $item)
    {
        $this->item = $item;
    }

    public function getProject(): ?Project
    {
        return $this->item->getProject();
    }

    public static function getValidationRules(): ?array
    {
        return [
            'text' => Rules::new()->trim()->required(),
        ];
    }
}