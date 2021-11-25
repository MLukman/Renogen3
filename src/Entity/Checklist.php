<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="checklists")
 */
class Checklist extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=Ramsey\Uuid\Doctrine\UuidGenerator::class)
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity="Deployment", inversedBy="checklists")
     * @ORM\JoinColumn(name="deployment_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Deployment
     */
    public $deployment;

    /**
     * @ORM\Column(type="string", length=250)
     */
    public $title;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    public $start_datetime;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    public $end_datetime;

    /**
     * @ORM\Column(type="string", length=30)
     */
    public $status = 'Not Started';

    /**
     * @ORM\ManyToMany(targetEntity="User")
     * @ORM\JoinTable(
     *  name="checklist_pics",
     *  joinColumns={
     *      @ORM\JoinColumn(name="checklist_id", referencedColumnName="id")
     *  },
     *  inverseJoinColumns={
     *      @ORM\JoinColumn(name="pic_username", referencedColumnName="username")
     *  }
     * )
     * @var ArrayCollection|User[]
     */
    public $pics;

    /**
     * @ORM\OneToMany(targetEntity="ChecklistUpdate", mappedBy="checklist", indexBy="id", orphanRemoval=true, cascade={"persist"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"created_date" = "asc"})
     * @var ArrayCollection|ChecklistUpdate[]
     */
    public $updates;

    public function __construct(Deployment $deployment)
    {
        $this->deployment = $deployment;
        $this->pics       = new ArrayCollection();
        $this->updates    = new ArrayCollection();
    }

    public function isPending()
    {
        return $this->status == 'Not Started' || $this->status == 'In Progress';
    }

    public function isUsernameAllowed($username, $attribute)
    {
        switch ($attribute) {
            case 'edit':
                if ($this->created_by->username == $username) {
                    return true;
                }
                foreach ($this->pics as $user) {
                    if ($user->username == $username) {
                        return true;
                    }
                }
                break;

            case 'delete':
            case 'edit_title':
                if ($this->created_by->username == $username) {
                    return true;
                }
                break;
        }

        return $this->deployment->isUsernameAllowed($username, 'approval');
    }

    public function getProject(): ?Project
    {
        return $this->deployment->getProject();
    }

    public static function getValidationRules(): ?array
    {
        return [
            'title' => Rules::new()->trim()->required()->truncate(250)->unique('deployment'),
            'start_datetime' => Rules::new()->required(),
            'pics' => Rules::new()->trim()->required(),
        ];
    }
}