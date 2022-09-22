<?php

namespace App\Base;

use App\Entity\Project;
use App\Entity\User;
use App\Security\Authorization\SecuredAccessInterface;
use App\Security\Authorization\SecuredAccessTrait;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass @ORM\HasLifecycleCallbacks
 */
abstract class Entity implements SecuredAccessInterface
{

    use SecuredAccessTrait;
    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="created_by", referencedColumnName="username", onDelete="SET NULL")
     * @var User
     */
    public $created_by;

    /**
     * @ORM\Column(type="datetime")
     */
    public $created_date;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="updated_by", referencedColumnName="username", onDelete="SET NULL")
     * @var User
     */
    public $updated_by;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    public $updated_date;

    /**
     * Validation errors if any
     * @var array
     */
    public $errors = [];

    /**
     *
     * @var array Old values
     */
    public $old_values = [];

    /**
     * Cache
     * @var array
     */
    protected $_caches = [];

    public function __get($property)
    {
        return $this->$property;
    }

    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    static public function createCondition($f, $c, $v): Criteria
    {
        return Criteria::create()->where(new Comparison($f, $c, $v));
    }

    public function storeOldValues(array $fields)
    {
        foreach ($fields as $field) {
            $this->old_values[$field] = $this->$field;
        }
    }

    protected function cached($cacheid, callable $create, $force = false)
    {
        if (!isset($this->_caches[$cacheid]) || $force) {
            $this->_caches[$cacheid] = $create();
        }
        return $this->_caches[$cacheid];
    }

    /**
     * Return array of Entities that need to be deleted together with this entity
     * @return array Entities that to be cascade-deleted
     */
    public function cascadeDelete(): array
    {
        return [];
    }

    public function isUsernameAllowed($username, $attribute): bool
    {
        $allowed = false;

        switch ($attribute) {
            case 'delete':
                $allowed = ($this->created_by->username === $username) && $this->isSafeToDelete();
                break;
        }

        return $allowed;
    }

    static public function generateDatetimeString(\DateTime $ddate,
                                                  $pretty = false): string
    {
        if ($ddate->format('Hi') == '0000') {
            return $ddate->format($pretty ? 'd/m/Y' : 'Ymd');
        } else {
            return $ddate->format($pretty ? 'd/m/Y h:i A' : 'YmdHi');
        }
    }

    /**
     * Return the validation rules that to be used when validating this entity
     */
    abstract static public function getValidationRules(): ?array;

    /**
     * Return the Project this entity belongs to
     * @return Project The belonging Project
     */
    abstract public function getProject(): ?Project;

    public function isSafeToDelete(): bool
    {
        return true;
    }
}