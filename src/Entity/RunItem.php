<?php

namespace App\Entity;

use App\Base\Actionable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="runitems")
 * @ORM\HasLifecycleCallbacks
 */
class RunItem extends Actionable
{
    /**
     * @ORM\ManyToOne(targetEntity="Deployment", inversedBy="runitems")
     * @ORM\JoinColumn(name="deployment_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Deployment
     */
    public $deployment;

    /**
     * @ORM\OneToMany(targetEntity="Activity", mappedBy="runitem", indexBy="id", fetch="EXTRA_LAZY")
     * @var ArrayCollection|Activity[]
     */
    public $activities = null;

    /**
     * @ORM\OneToMany(targetEntity="RunItemFile", mappedBy="runitem", indexBy="classifier", orphanRemoval=true, cascade={"persist", "remove"}, fetch="EXTRA_LAZY")
     * @var ArrayCollection|RunItemFile[]
     */
    public $files = null;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    public $status = 'New';
    public $fileClass = '\App\Entity\RunItemFile';
    public $actionableType = 'runitem';

    public function __construct(Deployment $deployment)
    {
        $this->deployment = $deployment;
        $this->activities = new ArrayCollection();
        $this->files = new ArrayCollection();
    }

    public function isUsernameAllowed($username, $attribute): bool
    {
        return parent::isUsernameAllowed($username, $attribute) ||
            $this->deployment->isUsernameAllowed($username, $attribute);
    }

    public function getProject(): ?Project
    {
        return $this->deployment->getProject();
    }

    public static function getValidationRules(): ?array
    {
        return null;
    }
}