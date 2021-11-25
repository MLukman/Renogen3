<?php

namespace App\Entity;

use App\Base\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="item_status_log")
 */
class ItemStatusLog extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=Ramsey\Uuid\Doctrine\UuidGenerator::class)
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity="Item", inversedBy="status_logs")
     * @ORM\JoinColumn(name="item_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Item
     */
    public $item;

    /**
     * @ORM\Column(type="string", length=100)
     */
    public $status;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $remark;

    public function __construct(Item $item, $status, User $user = null,
                                \DateTime $datetime = null)
    {
        $this->item         = $item;
        $this->created_date = $datetime ?: new \DateTime();
        $this->created_by   = $user;
        $this->status       = $status;
    }

    public function getProject(): ?Project
    {
        return $this->item->getProject();
    }

    public static function getValidationRules(): ?array
    {
        return null;
    }
}