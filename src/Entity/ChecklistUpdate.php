<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="checklist_updates")
 */
class ChecklistUpdate extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=Ramsey\Uuid\Doctrine\UuidGenerator::class)
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity="Checklist", inversedBy="updates")
     * @ORM\JoinColumn(name="checklist_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Checklist
     */
    public $checklist;

    /**
     * @ORM\Column(type="string", length=150)
     */
    public $comment;

    public function __construct(Checklist $checklist)
    {
        $this->checklist = $checklist;
    }

    public function getProject(): ?Project
    {
        return $this->checklist->getProject();
    }

    public static function getValidationRules(): ?array
    {
        return [
            'comment' => Rules::new()->trim()->required()->maxlen(150),
        ];
    }
}