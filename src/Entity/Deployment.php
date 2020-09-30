<?php

namespace App\Entity;

use App\Base\Entity;
use App\Service\DataStore;
use App\Validation\Rules;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="deployments", indexes={@ORM\Index(name="execute_date_idx", columns={"execute_date"})})
 * @ORM\HasLifecycleCallbacks
 */
class Deployment extends Entity
{
    /**
     * @ORM\Id @ORM\Column(type="string") @ORM\GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity="Project",inversedBy="deployments")
     * @ORM\JoinColumn(name="project_id", referencedColumnName="id", onDelete="CASCADE")
     * @var Project
     */
    public $project;

    /**
     * @ORM\Column(type="string", length=100)
     */
    public $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    public $execute_date;

    /**
     * @ORM\Column(type="string", length=2000, nullable=true)
     */
    public $external_url;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    public $external_url_label;

    /**
     * @ORM\OneToMany(targetEntity="Item", mappedBy="deployment", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @var ArrayCollection|Item[]
     */
    public $items = null;

    /**
     * @ORM\OneToMany(targetEntity="RunItem", mappedBy="deployment", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"created_date" = "ASC"})
     * @var ArrayCollection|RunItem[]
     */
    public $runitems = null;

    /**
     * @ORM\OneToMany(targetEntity="Checklist", mappedBy="deployment", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"start_datetime" = "ASC", "end_datetime" = "ASC"})
     * @var ArrayCollection|Checklist[]
     */
    public $checklists = null;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     * @var array
     */
    public $plugin_data = array();

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'execute_date' => array('required' => 1, 'unique' => 'project'),
        'title' => array('trim' => 1, 'required' => 1, 'truncate' => 100),
        'external_url' => array('trim' => 1, 'maxlen' => 2000, 'url' => 1),
        'external_url_label' => array('trim' => 1, 'truncate' => 30),
    );
    protected $_caches = [];

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->items = new ArrayCollection();
        $this->runitems = new ArrayCollection();
        $this->checklists = new ArrayCollection();
    }

    public function name()
    {
        return $this->datetimeString();
    }

    public function displayTitle()
    {
        return $this->datetimeString(true).' - '.$this->title;
    }

    public function datetimeString($pretty = false, \DateTime $ddate = null)
    {
        if (!$ddate) {
            $ddate = $this->execute_date;
        }
        return static::generateDatetimeString($ddate, $pretty);
    }

    public function isActive($buffer_day = 0)
    {
        $ref_date = clone $this->execute_date;
        $buffer_day = intval($buffer_day);
        if ($buffer_day != 0) {
            $ref_date->add(new \DateInterval("P${buffer_day}D"));
        }
        return ($ref_date >= date_create()->setTime(0, 0, 0)) && !$this->project->archived;
    }

    /**
     *
     * @return ArrayCollection
     */
    public function getApprovedItems()
    {
        return $this->items->matching(Criteria::create()->where(
                    new Comparison('approved_date', '<>', null)));
    }

    /**
     *
     * @return ArrayCollection
     */
    public function getUnapprovedItems()
    {
        return $this->items->matching(Criteria::create()->where(
                    new Comparison('approved_date', '=', null)));
    }

    /**
     *
     * @return ArrayCollection
     */
    public function getItemsWithStatus($status)
    {
        if (!isset($this->_caches["items.$status"])) {
            $this->_caches["items.$status"] = $this->items->matching(Criteria::create()->where(
                    new Comparison('status', '=', $status)));
        }
        return $this->_caches["items.$status"];
    }

    public function generateRunbooks(DataStore $ds)
    {
        $activities = array(
            -1 => array(),
            0 => array(),
            1 => array(),
        );
        foreach ($this->runitems as $runitem) {
            $tid = sprintf("%03d:%s", $runitem->template->priority, $runitem->template->id);
            $array = &$activities[$runitem->stage ?: 0];
            if (!isset($array[$tid])) {
                $array[$tid] = array();
            }
            $array[$tid][] = $runitem;
        }

        $rungroups = array(
            -1 => array(),
            0 => array(),
            1 => array(),
        );
        foreach (array_keys($rungroups) as $stage) {
            ksort($activities[$stage]);
            foreach ($activities[$stage] as $acts) {
                $rungroups[$stage] = array_merge($rungroups[$stage], $ds->getActivityTemplateClass($acts[0]->template->class)
                        ->convertActivitiesToRunbookGroups($acts));
            }
        }

        return $rungroups;
    }

    public function isUsernameAllowed($username, $attribute)
    {
        return $this->project->isUsernameAllowed($username, $attribute);
    }

    public function getChecklistTemplates()
    {
        if (empty($this->project->checklist_templates)) {
            return array();
        }
        $checklists = array_map(function($c) {
            return $c->title;
        }, $this->checklists->toArray());
        return array_values(array_filter($this->project->checklist_templates, function($a) use ($checklists) {
                return !in_array($a, $checklists);
            }));
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public static function getValidationRules(): ?array
    {
        return [
            'execute_date' => Rules::new()->required()->unique('project'),
            'title' => Rules::new()->required()->trim()->truncate(100),
            'external_url' => Rules::new()->trim()->maxlen(2000)->url(),
            'external_url_label' => Rules::new()->trim()->truncate(30),
        ];
    }
}