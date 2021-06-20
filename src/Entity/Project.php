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
    const DEFAULT_ATTACHMENT_FILE_EXTS = ".png,.jpg,.jpeg,.gif,.tif,.tiff,.bmp,.eps,.pdf,.xls,.xlsx,.doc,.docx,.ppt,.pptx,.rtf";

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
     * @ORM\OneToMany(targetEntity="Deployment", mappedBy="project", orphanRemoval=true, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"execute_date" = "ASC"})
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
     * Approximation of how many hours a deployment will take. Will be used to determine which deployment is ongoing.
     * @ORM\Column(type="integer", options={"default" : 6})
     */
    public $approx_deployment_duration = 6;

    /**
     * Comma-delimited list of acceptable file extensions for deployment item attachments.
     * @ORM\Column(type="string", length=255, options={"default":Project::DEFAULT_ATTACHMENT_FILE_EXTS})
     */
    public $attachment_file_exts;

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
            'proceedstatus' => self::ITEM_STATUS_REVIEW,
            'rejectaction' => false,
            'role' => ['entry', 'approval'],
            'requirecurrent' => [self::ITEM_STATUS_INIT],
            'color' => 'teal',
            'sequence' => 1,
        ),
        self::ITEM_STATUS_REVIEW => array(
            'icon' => 'clipboard check',
            'stepicon' => 'clipboard check',
            'proceedaction' => 'Verified',
            'proceedstatus' => self::ITEM_STATUS_APPROVAL,
            'rejectaction' => 'Rejected',
            'role' => ['review', 'approval'],
            'requirecurrent' => [self::ITEM_STATUS_REVIEW],
            'color' => 'grey',
            'sequence' => 2,
        ),
        self::ITEM_STATUS_APPROVAL => array(
            'icon' => 'thumbs up',
            'stepicon' => 'thumbs up',
            'proceedaction' => 'Approved',
            'proceedstatus' => self::ITEM_STATUS_READY,
            'rejectaction' => 'Rejected',
            'role' => 'approval',
            'requirecurrent' => [self::ITEM_STATUS_REVIEW, self::ITEM_STATUS_APPROVAL],
            'color' => 'yellow',
            'sequence' => 3,
        ),
        self::ITEM_STATUS_READY => array(
            'icon' => 'cloud upload',
            'stepicon' => 'cloud upload',
            'proceedaction' => 'Completed',
            'proceedstatus' => self::ITEM_STATUS_COMPLETED,
            'rejectaction' => 'Failed',
            'role' => ['execute', 'approval'],
            'requirecurrent' => [self::ITEM_STATUS_READY],
            'color' => 'orange',
            'sequence' => 4,
        ),
        self::ITEM_STATUS_COMPLETED => array(
            'icon' => 'flag checkered',
            'stepicon' => 'flag checkered',
            'proceedaction' => false,
            'proceedstatus' => null,
            'rejectaction' => false,
            'requirecurrent' => [self::ITEM_STATUS_COMPLETED],
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
                return static::filterUpcomingDateOnly($this->deployments, 'execute_date', 'end_date');
            });
    }

    /**
     * Filters the provided collection for date_field that has upcoming dates only. Unless $strict is true, it will also fall back to those within same day or even those after yesterday's 6PM if result is empty.
     * @param Selectable $collection The collection to filter from
     * @param string $date_field The name of the field that contains the start date time
     * @param string $end_date_field The name of the field that contains the end date time
     * @param int $limit Limit to this number of results. Default to 0, which means no limit
     * @return Selectable the filtered collection sorted by the ascending order of the date field
     */
    static public function filterUpcomingDateOnly(Selectable $collection,
                                                  $date_field,
                                                  $end_date_field = null,
                                                  $limit = 0): Selectable
    {
        $compare_dates = [];
        $now = date_create();
        if (!empty($end_date_field)) {
            // find out if there is ongoing deployment (the latest one between now and previous $lookback hours)
            $ongoing = $collection->matching(Criteria::create()
                    ->where(new Comparison("$end_date_field", '>=', $now))
                    ->andWhere(new Comparison($date_field, '<=', $now))
                    ->orderBy([$date_field => 'DESC'])
                    ->setMaxResults(1));
            if ($ongoing->count() > 0) {
                $compare_dates[] = $ongoing->get(0)->execute_date;
            }
        }
        $compare_dates[] = $now;
        $upcoming = [];
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

    public function getDeploymentNumber(Deployment $deployment)
    {
        return $this->deployments->indexOf($deployment) + 1;
    }

    public function nextDeploymentAfter(\DateTime $date)
    {
        return $this->deployments->matching(Criteria::create()
                    ->where(new Comparison('execute_date', '>', $date))
                    ->orderBy(array('execute_date' => 'ASC'))
                    ->setMaxResults(1))
                ->first();
    }

    public function previousDeploymentBefore(\DateTime $date)
    {
        return $this->deployments->matching(Criteria::create()
                    ->where(new Comparison('execute_date', '<', $date))
                    ->orderBy(array('execute_date' => 'DESC'))
                    ->setMaxResults(1))
                ->first();
    }

    public function upcomingDeploymentRequests()
    {
        return $this->cached('upcomingRequests', function() {
                return static::filterUpcomingDateOnly($this->deployment_requests, 'execute_date');
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
        $up = $this->userProject($username);
        return ($up ? $up->role : null);
    }

    public function userProject($username)
    {
        if ($username instanceof User) {
            $username = $username->username;
        }
        return ($this->userProjects->containsKey($username) ?
            $this->userProjects->get($username) : null);
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
            'name' => Rules::new()->trim()->required()->unique()->maxlen(30)
                ->pregmatch('/^[0-9a-zA-Z][0-9a-zA-Z_-]*$/', 'Project name can only contains alphanumeric, dashes and undercores, and it must start with an alphanumerical character')
                ->invalidvalues(['login', 'admin', 'archived', 'register']),
            'title' => Rules::new()->trim()->required()->unique()->maxlen(100),
            'categories' => Rules::new()->trim()->required(),
            'modules' => Rules::new()->trim()->required(),
            'icon' => Rules::new()->trim()->maxlen(30),
            'attachment_file_exts' => Rules::new()->trim()->maxlen(255)->default(static::DEFAULT_ATTACHMENT_FILE_EXTS)
        ];
    }

    public function isSafeToDelete(): bool
    {
        return $this->deployments->count() == 0;
    }

    public function starCount(): int
    {
        return $this->userProjects->matching(Criteria::create()->where(Criteria::expr()->gt('fav', 0)))->count();
    }
}