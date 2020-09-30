<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use DateInterval;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="projects", indexes={@ORM\Index(name="name_idx", columns={"name"})})
 */
class Project extends Entity
{
    /**
     * @ORM\Id @ORM\Column(type="string") @ORM\GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ORM\Column(type="datetime")
     */
    public $created_date;

    /**
     * @ORM\Column(type="string", length=30)
     */
    public $name;

    /**
     * @ORM\Column(type="string", length=100)
     */
    public $title;

    /**
     * @ORM\Column(type="string", length=30, options={"default":"cube"})
     */
    public $icon = 'cube';

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    public $modules = array();

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    public $categories = array(
        'Bug Fix',
        'Enhancement',
        'New Feature',
    );

    /**
     * @ORM\Column(type="boolean", options={"default":"0"})
     */
    public $private = false;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    public $checklist_templates;

    /**
     * @ORM\Column(type="boolean", options={"default":"0"})
     */
    public $archived = false;

    /**
     * @ORM\OneToMany(targetEntity="Deployment", mappedBy="project", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"execute_date" = "DESC"})
     * @var ArrayCollection|Deployment[]
     */
    public $deployments = null;

    /**
     * @ORM\OneToMany(targetEntity="DeploymentRequest", mappedBy="project", indexBy="id", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"execute_date" = "ASC"})
     * @var ArrayCollection|DeploymentRequest[]
     */
    public $deployment_requests = null;

    /**
     * @ORM\OneToMany(targetEntity="UserProject", mappedBy="project", indexBy="username", orphanRemoval=true, cascade={"persist"})
     * @var ArrayCollection|UserProject[]
     */
    public $userProjects = null;

    /**
     * @ORM\OneToMany(targetEntity="Template", mappedBy="project", indexBy="id", orphanRemoval=true)
     * @ORM\OrderBy({"priority" = "asc", "created_date" = "asc"})
     * @var ArrayCollection|Template[]
     */
    public $templates = null;

    /**
     * @ORM\OneToMany(targetEntity="Plugin", mappedBy="project", indexBy="name", orphanRemoval=true, cascade={"persist"})
     * @var ArrayCollection|Plugin[]
     */
    public $plugins = null;

    /**
     * Validation rules
     * @var array
     */
    protected $validation_rules = array(
        'name' => array('required' => 1, 'unique' => true, 'maxlen' => 30,
            'preg_match' => array('/^[0-9a-zA-Z][0-9a-zA-Z_-]*$/', 'Project name must start with an alphanumerical character'),
            'invalidvalues' => array('login', 'admin', 'archived', 'register')),
        'title' => array('required' => 1, 'unique' => true, 'maxlen' => 100),
        'categories' => array('required' => 1),
        'modules' => array('required' => 1),
        'icon' => array('trim' => 1, 'maxlen' => 30),
    );
    protected $validation_default = array('trim' => 1);

    const ITEM_STATUS_INIT = 'Documentation';
    const ITEM_STATUS_REVIEW = 'Review';
    const ITEM_STATUS_APPROVAL = 'Go No Go';
    const ITEM_STATUS_READY = 'Ready For Release';
    const ITEM_STATUS_COMPLETED = 'Completed';
    const ITEM_STATUS_REJECTED = 'Rejected';
    const ITEM_STATUS_FAILED = 'Failed';

    public $item_statuses = array(
        self::ITEM_STATUS_INIT => array(
            'icon' => 'edit',
            'stepicon' => 'edit',
            'proceedaction' => 'Submit For Review',
            'rejectaction' => false,
            'role' => ['entry', 'approval'],
            'color' => 'teal',
            'sequence' => 1,
        ),
        self::ITEM_STATUS_REVIEW => array(
            'icon' => 'clipboard check',
            'stepicon' => 'clipboard check',
            'proceedaction' => 'Verified',
            'rejectaction' => 'Rejected',
            'role' => ['review', 'approval'],
            'color' => 'grey',
            'sequence' => 2,
        ),
        self::ITEM_STATUS_APPROVAL => array(
            'icon' => 'thumbs up',
            'stepicon' => 'thumbs up',
            'proceedaction' => 'Approved',
            'rejectaction' => 'Rejected',
            'role' => 'approval',
            'color' => 'yellow',
            'sequence' => 3,
        ),
        self::ITEM_STATUS_READY => array(
            'icon' => 'cloud upload',
            'stepicon' => 'cloud upload',
            'proceedaction' => 'Completed',
            'rejectaction' => 'Failed',
            'role' => ['execute', 'approval'],
            'color' => 'orange',
            'sequence' => 4,
        ),
        self::ITEM_STATUS_COMPLETED => array(
            'icon' => 'flag checkered',
            'stepicon' => 'flag checkered',
            'proceedaction' => false,
            'rejectaction' => false,
            'role' => null,
            'color' => 'green',
            'sequence' => 5,
        )
    );

    public function __construct()
    {
        $this->created_date = new \DateTime();
        $this->deployments = new ArrayCollection();
        $this->deployment_requests = new ArrayCollection();
        $this->templates = new ArrayCollection();
        $this->plugins = new ArrayCollection();
        $this->userProjects = new ArrayCollection();
    }

    /**
     * Get upcoming deployments
     * @return ArrayCollection|Deployment[]
     */
    public function upcoming()
    {
        return $this->cached('upcoming', function() {
                return static::filterUpcomingDateOnly($this->deployments, 'execute_date');
            });
    }

    /**
     * Filters the provided collection for date_field that has upcoming dates only. Unless $strict is true, it will also fall back to those within same day or even those after yesterday's 6PM if result is empty.
     * @param Selectable $collection The collection to filter from
     * @param string $date_field The name of the field that contains the date to compare with
     * @param bool $strict Set true to only filter > now. Default to false, where it will fall back to > 12am today or even > 6pm yesterday
     * @param int $limit Limit to this number of results. Default to 0, which means no limit
     * @return Selectable the filtered collection sorted by the ascending order of the date field
     */
    static public function filterUpcomingDateOnly(Selectable $collection,
                                                  $date_field, $strict = false,
                                                  $limit = 0): Selectable
    {
        if ($strict) {
            $compare_dates = array(date_create());
        } else {
            $compare_dates = array(date_create(), date_create()->setTime(0, 0, 0),
                date_create('yesterday')->setTime(18, 0, 0));
        }
        $upcoming = array();
        foreach ($compare_dates as $compare) {
            $criteria = Criteria::create()
                ->where(new Comparison($date_field, '>=', $compare))
                ->orderBy(array($date_field => 'ASC'));
            if ($limit > 0) {
                $criteria = $criteria->setMaxResults($limit);
            }
            $upcoming = $collection->matching($criteria);
            if ($upcoming->count() > 0) {
                break;
            }
        }
        return $upcoming;
    }

    /**
     * Get past deployments
     * @return ArrayCollection|Deployment[]
     */
    public function past($limit = 0)
    {
        return $this->cached("past.$limit", function() use ($limit) {
                $criteria = Criteria::create()
                    ->orderBy(array('execute_date' => 'DESC'));
                $upcoming = $this->upcoming();
                if (count($upcoming) > 0) {
                    $criteria = $criteria->where(new Comparison('execute_date', '<', $upcoming[0]->execute_date));
                }
                if ($limit > 0) {
                    $criteria = $criteria->setMaxResults($limit);
                }
                return $this->deployments->matching($criteria);
            });
    }

    public function getDeploymentsByDateString($datestring,
                                               $include_future = false,
                                               $fuzzy = true)
    {
        $criteria = Criteria::create();
        switch (strlen($datestring)) {
            case 12:
                $criteria->where(Criteria::expr()->eq('execute_date', DateTime::createFromFormat('!YmdHi', $datestring)));
                $matching = $this->deployments->matching($criteria);
                if ($matching->count() == 0 && $fuzzy) {
                    return $this->getDeploymentsByDateString(substr($datestring, 0, 8), true);
                }
                return $matching;

            case 8:
                $criteria->andWhere(new Comparison('execute_date', '>=', DateTime::createFromFormat('!Ymd', $datestring)))
                    ->orderBy(array('execute_date' => 'ASC'));
                if (!$include_future) {
                    $criteria->andWhere(new Comparison('execute_date', '<', DateTime::createFromFormat('!Ymd', $datestring)->add(new DateInterval("P1D"))));
                }
                return $this->deployments->matching($criteria);

            default:
                return null;
        }
    }

    public function getUserAccess($username)
    {
        if ($username instanceof User) {
            $username = $username->username;
        }
        return ($this->userProjects->containsKey($username) ?
            $this->userProjects->get($username)->role : null);
    }

    public function isUsernameAllowed($username, $attr = 'view')
    {
        $this->allowedRoles = array();
        if (method_exists($this, '__load')) {
            $this->__load();
        }
        if (!$this->userProjects->containsKey($username)) {
            return false;
        } elseif ($attr == 'any') {
            return true;
        }
        if (!is_array($attr)) {
            $attr = array($attr);
        }
        $role = $this->userProjects->get($username)->role;
        foreach ($attr as $a) {
            if ($role == $a) {
                return true;
            }
        }
        return false;
    }

    public function enabled_templates()
    {
        if (!isset($this->_enabled_templates)) {
            $this->_enabled_templates = $this->templates->matching(Criteria::create()->where(Criteria::expr()->eq("disabled", false)));
        }
        return $this->_enabled_templates;
    }

    public function usersWithRole($role)
    {
        return array_filter(
            array_map(function($a) {
                return $a->user;
            }, $this->userProjects->matching(Criteria::create()->where(Criteria::expr()->eq('role', $role)))->toArray()),
            function($u) {
            return $u->blocked != 1;
        });
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function defaultIcon()
    {
        if (empty($this->icon)) {
            $this->icon = 'cube';
        }
    }

    public function getProject(): ?Project
    {
        return $this;
    }

    public static function getValidationRules(): ?array
    {
        return [
            'name' => Rules::new()->required()->unique()->maxlen(30)
                ->pregmatch('/^[0-9a-zA-Z][0-9a-zA-Z_-]*$/', 'Project name can only contains alphanumeric, dashes and undercores, and it must start with an alphanumerical character')
                ->invalidvalues(['login', 'admin', 'archived', 'register']),
            'title' => Rules::new()->required()->unique()->maxlen(100),
            'categories' => Rules::new()->required(),
            'modules' => Rules::new()->required(),
            'icon' => Rules::new()->trim()->maxlen(30),
        ];
    }
}