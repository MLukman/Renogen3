<?php

namespace App\Entity;

use App\Base\Entity;
use App\Service\DataStore;
use App\Validation\Rules;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="deployments", indexes={@ORM\Index(name="execute_date_idx", columns={"execute_date"})})
 * @ORM\HasLifecycleCallbacks
 */
class Deployment extends Entity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=Ramsey\Uuid\Doctrine\UuidGenerator::class)
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
     * How many hours this deployment will take. Will be used to determine if deployment is ongoing.
     * @ORM\Column(type="integer")
     */
    public $duration = 0;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     * Will be dynamically calculated when loaded
     */
    public $end_date;

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
     * @ORM\Column(type="json", nullable=true)
     * @var array
     */
    public $plugin_data = [];
    protected $_caches = [];

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->duration = $this->project->approx_deployment_duration;
        $this->items = new ArrayCollection();
        $this->runitems = new ArrayCollection();
        $this->checklists = new ArrayCollection();
    }

    public function name(): string
    {
        return $this->datetimeString();
    }

    public function displayTitle(): string
    {
        return $this->datetimeString(true).' - '.$this->title;
    }

    public function datetimeString($pretty = false, \DateTime $ddate = null): string
    {
        if (!$ddate) {
            $ddate = $this->execute_date;
        }
        return static::generateDatetimeString($ddate, $pretty);
    }

    public function isActive(): bool
    {
        return !$this->project->archived && ($this->execute_date >= date_create(sprintf("-%d hours",
                    $this->duration ?: $this->project->approx_deployment_duration)));
    }

    public function isRunning(): bool
    {
        return $this->isActive() && $this->execute_date <= date_create();
    }

    /**
     *
     * @return array
     */
    public function getItemsWithStatus($status): array
    {
        $status_items = $this->cached("items",
            function () {
                $status_items = [];
                foreach ($this->items as $item) {
                    if (!isset($status_items[$item->status])) {
                        $status_items[$item->status] = [];
                    }
                    $status_items[$item->status][] = $item;
                }
                return $status_items;
            });
        return $status_items[$status] ?? [];
    }

    public function generateRunbooks(DataStore $ds): array
    {
        $activities = [
            -1 => [],
            0 => [],
            1 => [],
        ];
        foreach ($this->runitems as $runitem) {
            $tid = sprintf("%03d:%s", $runitem->template->priority,
                $runitem->template->id);
            $array = &$activities[$runitem->stage ?: 0];
            if (!isset($array[$tid])) {
                $array[$tid] = [];
            }
            $array[$tid][] = $runitem;
        }

        $rungroups = [
            -1 => [],
            0 => [],
            1 => [],
        ];
        foreach (array_keys($rungroups) as $stage) {
            ksort($activities[$stage]);
            foreach ($activities[$stage] as $acts) {
                $rungroups[$stage] = array_merge($rungroups[$stage],
                    $ds->getActivityTemplateClass($acts[0]->template->class)
                        ->convertActivitiesToRunbookGroups($acts));
            }
        }

        return $rungroups;
    }

    public function isUsernameAllowed($username, $attribute): bool
    {
        return $this->project->isUsernameAllowed($username, $attribute);
    }

    public function getChecklistTemplates(): array
    {
        if (empty($this->project->checklist_templates)) {
            return [];
        }
        $checklists = array_map(function ($c) {
            return $c->title;
        }, $this->checklists->toArray());
        return array_values(array_filter($this->project->checklist_templates,
                function ($a) use ($checklists) {
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
            'title' => Rules::new()->trim()->required()->truncate(100),
            'external_url' => Rules::new()->trim()->maxlen(2000)->url(),
            'external_url_label' => Rules::new()->trim()->truncate(30),
        ];
    }

    public function isSafeToDelete(): bool
    {
        return $this->items->count() == 0 && $this->checklists->count() == 0;
    }

    /** @ORM\PostLoad @ORM\PrePersist @ORM\PreUpdate */
    public function populateEndDate()
    {
        $hour = $this->duration ?: $this->project->approx_deployment_duration;
        $this->end_date = (clone $this->execute_date)->add(new \DateInterval("PT{$hour}H"));
    }
}