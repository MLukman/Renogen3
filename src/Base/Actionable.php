<?php

namespace App\Base;

use App\Entity\Template;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 * @ORM\HasLifecycleCallbacks
 */
abstract class Actionable extends Entity
{
    /**
     * @ORM\Id @ORM\Column(type="string") @ORM\GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    public $signature;

    /**
     * @ORM\ManyToOne(targetEntity="Template", inversedBy="activities")
     * @ORM\JoinColumn(name="template_id", referencedColumnName="id", onDelete="RESTRICT")
     * @var Template
     */
    public $template;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    public $stage;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    public $priority;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    public $parameters;

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function calculateSignature()
    {
        $this->signature = sha1(($this->template ? $this->template->id : '?').'|'.$this->stage.'|'.json_encode($this->parameters));
    }
}