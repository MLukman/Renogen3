<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="items", indexes={@ORM\Index(name="status_idx", columns={"deployment_id","status"})})
 * @ORM\HasLifecycleCallbacks
 */
class Item extends Entity
{
    /**
     * @ORM\Id @ORM\Column(type="string") @ORM\GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity="Deployment", inversedBy="items")
     * @ORM\JoinColumn(name="deployment_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Deployment
     */
    public $deployment;

    /**
     * @ORM\Column(type="string", length=40, nullable=true)
     */
    public $refnum;

    /**
     * @ORM\Column(type="string", length=250)
     */
    public $title;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    public $category;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    public $modules = array();

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @ORM\Column(type="string", length=2000, nullable=true)
     */
    public $external_url;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    public $external_url_label;

    /**
     * @ORM\OneToMany(targetEntity="Activity", mappedBy="item", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"stage" = "asc", "priority" = "asc", "created_date" = "asc"})
     * @var ArrayCollection|Activity[]
     */
    public $activities = null;

    /**
     * @ORM\OneToMany(targetEntity="Attachment", mappedBy="item", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"created_date" = "asc"})
     * @var ArrayCollection|Attachment[]
     */
    public $attachments = null;

    /**
     * @ORM\OneToMany(targetEntity="ItemComment", mappedBy="item", indexBy="id", orphanRemoval=true, cascade={"persist"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"created_date" = "asc"})
     * @var ArrayCollection|ItemComment[]
     */
    public $comments = null;

    /**
     * @ORM\OneToMany(targetEntity="ItemStatusLog", mappedBy="item", indexBy="id", orphanRemoval=true, cascade={"persist"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"created_date" = "asc"})
     * @var ArrayCollection|ItemStatusLog[]
     */
    public $status_logs = null;

    /**
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    public $status = Project::ITEM_STATUS_INIT;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime
     */
    public $approved_date;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     * @var array
     */
    public $plugin_data = array();

    protected $_statuses;

    public function __construct(Deployment $deployment)
    {
        $this->deployment = $deployment;
        $this->activities = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->status_logs = new ArrayCollection();
        $this->status_logs->add(new ItemStatusLog($this, $this->status));
    }

    public function displayTitle()
    {
        return ($this->refnum ? $this->refnum.' - ' : '').$this->title;
    }

    public function status()
    {
        if (isset($this->deployment->project->item_statuses[$this->status])) {
            return $this->status;
        }
        return array_keys($this->deployment->project->item_statuses)[0];
    }

    public function statusIcon()
    {
        $status = $this->status;
        if (isset($this->deployment->project->item_statuses[$status])) {
            return $this->deployment->project->item_statuses[$status]['icon'];
        }
        return 'x';
    }

    /**
     *
     * @param type $status_to_compare
     * @return mixed 0 = same status, <0 = current status is before the provided status, >0 = current is ahead, FALSE = invalid status provided
     */
    public function compareCurrentStatusTo($status_to_compare)
    {
        return static::compareStatuses($this->deployment->project, $this->status(), $status_to_compare);
    }

    static public function compareStatuses(Project $project, $status1, $status2)
    {
        $_statuses = array_keys($project->item_statuses);
        $compare_status = array_search($status1, $_statuses);
        if ($compare_status === FALSE) {
            return -1;
        }
        $against_status = array_search($status2, $_statuses);
        if ($against_status === FALSE) {
            return FALSE;
        }
        return $against_status - $compare_status;
    }

    public function getNextStatus()
    {
        $this->_statuses = array_keys($this->deployment->project->item_statuses);
        $compare_status = array_search($this->status(), $this->_statuses);
        if ($compare_status === FALSE) {
            return $this->_statuses[0];
        } elseif ($compare_status < count($this->_statuses) - 1) {
            return $this->_statuses[$compare_status + 1];
        } else {
            return null;
        }
    }

    public function changeStatus($status, $remark = null)
    {
        $project = $this->deployment->project;
        $old_status_real = $this->status();
        if ($status == Project::ITEM_STATUS_READY &&
            static::compareStatuses($project, $old_status_real, $status) > 0) {
            $this->approved_date = new DateTime();
        }
        if (static::compareStatuses($project, $status, Project::ITEM_STATUS_APPROVAL)
            >= 0) {
            $this->approved_date = null;
        }

        $old_status = $this->status;
        $this->storeOldValues(array('status'));
        $this->status = $status;
        $status_log = new ItemStatusLog($this, $this->status);
        if (!empty($remark)) {
            $comment = new ItemComment($this);
            $comment->event = "$old_status > $this->status";
            $comment->text = $remark;
            $this->comments->add($comment);
            $status_log->remark = $remark;
        }
        $this->status_logs->add($status_log);
        return static::compareStatuses($project, $old_status_real, $status);
    }

    public function getStatusLog($status)
    {
        static $crit = null;
        if (!$crit) {
            $eb = new ExpressionBuilder();
            $crit = new Criteria($eb->eq('status', $status));
        }
        return $this->status_logs->matching($crit)->last();
    }

    public function getStatusLogBefore(ItemStatusLog $status)
    {
        $found = false;
        foreach (array_reverse($this->status_logs->toArray()) as $log) {
            if ($found && $log->created_date < $status->created_date) {
                return $log;
            } else if ($log === $status) {
                $found = true;
            }
        }
    }

    public function isUsernameAllowed($username, $attribute)
    {
        $allowed = false;

        switch ($attribute) {
            case 'delete':
            case 'move':
                $allowed = ($this->created_by->username == $username);
                $attribute = 'approval';
                break;
        }

        return $allowed || $this->deployment->isUsernameAllowed($username, $attribute);
    }

    /**
     * @ORM\PostLoad
     */
    public function onLoad()
    {
        $this->old_values['deployment'] = $this->deployment;
    }

    public function getAllowedTransitions(User $user)
    {
        $transitions = array();
        foreach ($this->deployment->project->item_statuses as $status => $config) {
            $progress = $this->compareCurrentStatusTo($status);
            $transition = array();
            if ($progress < 0) {
                // iterated status is behind current status
                if ($this->deployment->project->isUserNameAllowed($user->username, $config['role'])) {
                    $transition['Revert'] = array(
                        'status' => $status,
                        'remark' => true,
                        'type' => '',
                    );
                }
            } else if ($progress == 0) {
                // iterated status = current status
                if ($this->deployment->project->isUserNameAllowed($user->username, $config['role'])) {
                    $transition[$config['proceedaction']] = array(
                        'status' => $this->getNextStatus(),
                        'remark' => false,
                        'type' => 'primary',
                    );
                    if ($config['rejectaction']) {
                        $transition[$config['rejectaction']] = array(
                            'status' => $config['rejectaction'],
                            'remark' => true,
                            'type' => '',
                        );
                    }
                }
                if ($this->status == Project::ITEM_STATUS_READY &&
                    $this->activities->count() > 0) {
                    // Special condition: Ready For Release cannot be completed here
                    $transition = array();
                }
            }
            if (!empty($transition)) {
                $transitions[$status] = $transition;
            }
        }
        return $transitions;
    }

    public function getProject(): ?Project
    {
        return $this->deployment->getProject();
    }

    public static function getValidationRules(): ?array
    {
        return [
            'refnum' => Rules::new()->trim()->maxlen(40),
            'title' => Rules::new()->trim()->required()->truncate(250)->unique('deployment'),
            'category' => Rules::new()->required(),
            'modules' => Rules::new()->required(),
            'external_url' => Rules::new()->trim()->maxlen(2000)->url(),
            'external_url_label' => Rules::new()->trim()->truncate(30),
        ];
    }

    public function isSafeToDelete(): bool
    {
        return $this->activities->count() == 0 && $this->attachments->count() == 0;
    }
}