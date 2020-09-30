<?php

namespace App\Entity;

use App\Base\Entity;
use App\Validation\Rules;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;

/**
 * @ORM\Entity @ORM\Table(name="deployment_requests")
 */
class DeploymentRequest extends Entity
{
    /**
     * @ORM\Id @ORM\Column(type="string") @ORM\GeneratedValue(strategy="UUID")
     */
    public $id;

    /**
     * @ORM\ManyToOne(targetEntity="Project",inversedBy="deployment_requests")
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
     * @var DateTime
     */
    public $execute_date;

    /**
     * @ORM\Column(type="string", length=30)
     */
    public $status = 'New';

    /**
     * @ORM\Column(type="string", length=2000, nullable=true)
     */
    public $external_url;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     */
    public $external_url_label;

    /**
     * @ORM\OneToOne(targetEntity="Deployment")
     * @ORM\JoinColumn(name="deployment_id", referencedColumnName="id", onDelete="SET NULL")
     * @var Deployment
     */
    public $deployment;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function datetimeString($pretty = false)
    {
        return static::generateDatetimeString($this->execute_date, $pretty);
    }

    public static function getValidationRules(): ?array
    {
        return [
            'title' => Rules::new()->required()->trim()->truncate(100),
            'execute_date' => Rules::new()->required()->unique('project')->future(2)->callback(function(DeploymentRequest $e) {
                    if (0 < $e->project->deployments->matching(Criteria::create()->where(new Comparison('execute_date', '=', $e->execute_date)))->count()) {
                        throw new RuntimeException('Existing deployment with the exact date and time already exist within the same project');
                    }
                    return true;
                }),
            'external_url' => array('trim' => 1, 'maxlen' => 2000, 'url' => 1),
            'external_url_label' => array('trim' => 1, 'truncate' => 30),
        ];
    }
}