<?php

namespace App\Service;

use App\ActivityTemplate\BaseClass;
use App\Base\Entity;
use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\AuthDriver;
use App\Entity\Checklist;
use App\Entity\Deployment;
use App\Entity\DeploymentRequest;
use App\Entity\FileLink;
use App\Entity\FileStore;
use App\Entity\Item;
use App\Entity\Project;
use App\Entity\Template;
use App\Entity\User;
use App\Entity\UserAuthentication;
use App\Entity\UserProject;
use App\Exception\NoResultException;
use App\Security\Authentication\Driver\Password;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DataStore
{
    const PROJECT_ROLES = ['none', 'view', 'entry', 'review', 'approval', 'execute'];

    /**
     *
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     *
     * @var Security
     */
    protected $security;

    /**
     *
     * @var NavigationFactory
     */
    protected $nav;

    /**
     *
     * @var DoctrineValidator
     */
    protected $validator;

    /**
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     *
     * @var UserInterface
     */
    protected $securityUser;

    /**
     *
     * @var User
     */
    protected $userEntity;

    /**
     *
     * @var User
     */
    protected $overrideUser;
    protected $_templateClasses = [];
    protected $_pluginClasses = [];
    protected $_authClassNames = [];

    public function __construct(EntityManagerInterface $em, Security $security,
                                NavigationFactory $nav,
                                DoctrineValidator $validator,
                                CacheInterface $cache)
    {
        $this->em = $em;
        $this->security = $security;
        $this->nav = $nav;
        $this->validator = $validator;
        $this->cache = $cache;
    }

    public function em(): EntityManagerInterface
    {
        return $this->em;
    }

    /**
     *
     * @param type $entity
     * @param type $id_or_criteria
     * @return Entity|null
     */
    public function queryOne($entity, $id_or_criteria): ?Entity
    {
        if (empty($id_or_criteria)) {
            return null;
        }

        $repo = $this->em->getRepository($entity);
        return (is_array($id_or_criteria) ?
            $repo->findOneBy($id_or_criteria) :
            $repo->find($id_or_criteria));
    }

    /**
     *
     * @param string $entity
     * @param array $criteria
     * @param array $sort
     * @return array
     */
    public function queryMany($entity, Array $criteria = [], Array $sort = []): array
    {
        $repo = $this->em->getRepository($entity);
        return $repo->findBy($criteria, $sort);
    }

    /**
     *
     * @param string $entity
     * @param array $criteria
     * @return int
     */
    public function count($entity, Array $criteria = []): int
    {
        $qb = $this->em->getRepository($entity)->createQueryBuilder('e')
            ->select('COUNT(1)');
        foreach ($criteria as $field => $value) {
            $qb->andWhere("e.$field = :$field")
                ->setParameter($field, $value);
        }
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     *
     * @param type $entity
     * @param array $criteria
     * @param array $sort
     * @param type $limit
     * @return array
     */
    public function queryUsingOr($entity, Array $criteria = [],
                                 Array $sort = [], $limit = 0): array
    {
        $repo = $this->em->getRepository($entity);
        $qb = $repo->createQueryBuilder('e');
        foreach ($criteria as $crit => $value) {
            $qb->orWhere("e.$crit = :$crit")->setParameter($crit, $value);
        }
        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        foreach ($sort as $srt => $ord) {
            $qb->addOrderBy("e.$srt", $ord);
        }
        return $qb->getQuery()->execute();
    }

    /**
     *
     * @param type $project
     * @return Project
     * @throws NoResultException
     */
    public function fetchProject($project): Project
    {
        if (!($project instanceof Project)) {
            $name = $project;
            if (!($project = $this->queryOne('\App\Entity\Project', ['name' => $name]))) {
                throw new NoResultException("There is no such project with name '$name'");
            }
        }
        return $project;
    }

    /**
     *
     * @param type $user
     * @return User
     * @throws NoResultException
     */
    public function fetchUser($user): User
    {
        if (!($user instanceof User)) {
            $name = $user;
            if (!($user = $this->queryOne('\App\Entity\User', $name))) {
                throw new NoResultException("There is no such user with username '$name'");
            }
        }
        return $user;
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @return Deployment
     * @throws NoResultException
     */
    public function fetchDeployment($project, $deployment): Deployment
    {
        if ($deployment instanceof Deployment) {
            return $deployment;
        }
        $project_obj = $this->fetchProject($project);
        if (($deployments = $project_obj->getDeploymentsByDateString($deployment))
            && $deployments->count() > 0) {
            return $deployments->first();
        }
        throw new NoResultException("There is no such deployment matching '$deployment'");
    }

    /**
     *
     * @param type $project
     * @param type $deployment_request
     * @return DeploymentRequest
     * @throws NoResultException
     */
    public function fetchDeploymentRequest($project, $deployment_request): DeploymentRequest
    {
        if ($deployment_request instanceof DeploymentRequest) {
            return $deployment_request;
        }
        $project_obj = $this->fetchProject($project);
        if (($deployment = $project_obj->deployment_requests->get($deployment_request))) {
            return $deployment;
        }
        throw new NoResultException("There is no such deployment request matching '$deployment_request'");
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @param type $item
     * @return Item
     * @throws NoResultException
     */
    public function fetchItem($project, $deployment, $item): Item
    {
        if (!($item instanceof Item)) {
            $id = $item;
            if (!($item = $this->queryOne('\App\Entity\Item', ['id' => $id]))) {
                throw new NoResultException("There is no such deployment item with id '$id'");
            }
        }
        return $item;
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @param type $item
     * @return Checklist
     * @throws NoResultException
     */
    public function fetchChecklist($project, $deployment, $checklist): Checklist
    {
        if (!($checklist instanceof Checklist)) {
            $id = $checklist;
            if (!($checklist = $this->queryOne('\App\Entity\Checklist', ['id' => $id]))) {
                throw new NoResultException("There is no such deployment checklist id '$id'");
            }
        }
        return $checklist;
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @param type $item
     * @param type $activity
     * @return Activity
     * @throws NoResultException
     */
    public function fetchActivity($project, $deployment, $item, $activity): Activity
    {
        if (!($activity instanceof Activity)) {
            $id = $activity;
            $item_obj = $this->fetchItem($project, $deployment, $item);
            if (!($activity = $item_obj->activities->get($id))) {
                throw new NoResultException("There is no such activity with id '$id'");
            }
        }
        return $activity;
    }

    /**
     *
     * @param type $project
     * @param type $deployment
     * @param type $item
     * @param type $attachment
     * @return Attachment
     * @throws NoResultException
     */
    public function fetchAttachment($project, $deployment, $item, $attachment): Attachment
    {
        if (!($attachment instanceof Attachment)) {
            $id = $attachment;
            $item_obj = $this->fetchItem($project, $deployment, $item);
            if (!($attachment = $item_obj->attachments->get($id))) {
                throw new NoResultException("There is no such attachment with id '$id'");
            }
        }
        return $attachment;
    }

    /**
     *
     * @param Template|string $template
     * @param Project|string $project
     * @return Template
     * @throws NoResultException
     */
    public function fetchTemplate($template, $project = null): Template
    {
        if (!($template instanceof Template)) {
            $name = $template;
            $template = $this->queryOne('\App\Entity\Template', $template);
            if (!$template || ($project != null && $template->project != $this->fetchProject($project))) {
                throw new NoResultException("There is no such template with id '$name'");
            }
        }
        return $template;
    }

    /**
     *
     * @param User $user
     * @param type $roles
     * @param Project $exclude
     * @return ArrayCollection|Project[]
     */
    public function getProjectsForUserAndRole(User $user, $roles,
                                              Project $exclude = null): ArrayCollection
    {
        $projects = new ArrayCollection();
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        foreach ($roles as $role) {
            foreach ($this->queryMany('\App\Entity\UserProject', [
                'user' => $user,
                'role' => $role,
            ]) as $up) {
                /** @var UserProject $up */
                if ($up->getProject() !== $exclude) {
                    $projects->add($up->project);
                }
            }
        }
        return $projects;
    }

    /**
     *
     * @param Entity $entity
     * @return boolean
     */
    public function commit(Entity &$entity = null)
    {
        if ($entity) {
            $this->manage($entity);
            $this->em->flush($entity);
        } else {
            $this->em->flush();
        }
    }

    public function manage(Entity $entity)
    {
        $this->em->persist($entity);
    }

    public function unmanage(Entity $entity)
    {
        $this->em->detach($entity);
    }

    /**
     *
     * @param Entity $entity
     * @param array $fields
     * @param ParameterBag $data
     * @return boolean
     */
    public function prepareValidateEntity(Entity &$entity, Array $fields,
                                          ParameterBag $data): bool
    {
        foreach ($fields as $field) {
            if (!$data->has($field)) {
                continue;
            }
            $entity->storeOldValues(array($field));
            $field_value = $data->get($field);
            if ((substr($field, -5) == '_date' || substr($field, -9) == '_datetime')
                && !($field_value instanceof DateTime)) {
                if (!empty($field_value) && strlen($field_value) <= 10) {
                    $field_value .= ' 00:00 AM';
                }
                $entity->$field = (!$field_value ? null : DateTime::createFromFormat('d/m/Y h:i A', $field_value));
            } elseif (substr($field, -3) == '_by') {
                try {
                    $entity->$field = $this->fetchUser($field_value);
                } catch (Exception $e) {
                    $entity->$field = null;
                }
            } else {
                $entity->$field = $field_value;
            }
        }
        return $this->validateEntity($entity);
    }

    public function validateEntity(Entity &$entity): bool
    {
        $entity->errors = $this->validator->validate($entity, $entity::getValidationRules(), $entity->errors);
        return empty($entity->errors);
    }

    public function reloadEntity(Entity &$entity)
    {
        $this->em->refresh($entity);
    }

    public function deleteEntity(Entity &$entity)
    {
        $todelete = [];
        $cascader = function (Entity $e) use (&$todelete, &$cascader) {
            if (!in_array($e, $todelete)) {
                $todelete[] = $e;
                foreach ($e->cascadeDelete() as $c) {
                    if ($c instanceof Entity) {
                        $cascader($c);
                    }
                }
            }
        };
        $cascader($entity);
        foreach ($todelete as $d) {
            $this->em->remove($d);
        }
    }

    public function processFileUpload(UploadedFile $file,
                                      FileLink $filelink = null,
                                      array &$errors = []): ?FileLink
    {
        if ($file->isValid() && $filelink) {
            $sha1 = sha1_file($file->getRealPath());
            $filestore = $this->queryOne('\\App\\Entity\\FileStore', ['id' => $sha1]);
            if (!$filestore) {
                $filestore = new FileStore();
                $filestore->id = $sha1;
                $filestore->data = fopen($file->getRealPath(), 'rb');
                $filestore->filesize = $file->getSize();
                $filestore->mime_type = $file->getMimeType();
            }
            $filelink->filestore = $filestore;
            $filelink->filename = $file->getClientOriginalName();
            if (!$filelink->classifier) {
                $filelink->classifier = $filelink->filename;
            }
        } else {
            $errors = ['Unable to process uploaded file'];
        }
        return $filelink;
    }

    public function currentSecurityUser(): ?UserInterface
    {
        if (!$this->securityUser) {
            $this->securityUser = $this->security->getUser();
        }
        return $this->securityUser;
    }

    public function currentUserEntity(): ?User
    {
        if ($this->overrideUser) {
            return $this->overrideUser;
        }
        $this->currentSecurityUser();
        if (!$this->userEntity && $this->securityUser && !empty($this->securityUser->getUsername())) {
            $this->userEntity = $this->em->find('\\App\\Entity\\User', $this->securityUser->getUsername());
        }
        return $this->userEntity;
    }

    public function overrideUserEntity(?User $user)
    {
        $this->overrideUser = $user;
    }

    public function getUserAuthentication(array $criteria): ?UserAuthentication
    {
        return $this->queryOne('\\App\\Entity\\UserAuthentication', $criteria);
    }

    /**
     *
     * @param string $classId
     * @return Driver|null
     */
    public function getAuthDriver($classId): ?Driver
    {
        if (empty($this->em->getRepository('\App\Entity\AuthDriver')->find('password'))) {
            $auth_password = new AuthDriver('password');
            $auth_password->title = 'Internal User Database';
            $auth_password->class = Password::class;
            $this->em->persist($auth_password);
            $this->em->flush();
        }
        return $this->em->getRepository('\App\Entity\AuthDriver')->find($classId);
    }

    public function getAuthClassNames(): array
    {
        $globPath = __DIR__.'/../Security/Authentication/Driver/*.php';
        $authClassNames = $this->cache->get('authClasses', function (ItemInterface $item) use ($globPath) {
            $au = [];
            foreach (glob($globPath) as $fn) {
                $shortName = basename($fn, '.php');
                if ($shortName == 'Password') {
                    continue;
                }
                $className = '\\App\\Security\\Authentication\Driver\\'.$shortName;
                $classId = strtolower($shortName);
                $au[$classId] = $className;
            }
            return $au;
        });
        return $authClassNames;
    }

    /**
     *
     * @param string|null $name
     * @return BaseClass|array
     */
    public function getActivityTemplateClass($name = null)
    {
        if (empty($this->_templateClasses)) {
            foreach (glob(__DIR__.'/../ActivityTemplate/Impl/*.php') as $templateClass) {
                $templateClassName = '\App\ActivityTemplate\Impl\\'.basename($templateClass, '.php');
                $templateClass = new $templateClassName($this->nav, $this);
                $this->_templateClasses[$templateClassName] = $templateClass;
            }
            uasort($this->_templateClasses, function ($a, $b) {
                return strcmp($a->classTitle(), $b->classTitle());
            });
        }

        if (empty($name)) {
            return $this->_templateClasses;
        } elseif (isset($this->_templateClasses[$name])) {
            return $this->_templateClasses[$name];
        } elseif (class_exists($name)) {
            $this->_templateClasses[$name] = new $name($this->nav, $this);
            return $this->_templateClasses[$name];
        }
        return null;
    }

    public function createAdminUserIfNotExists(): ?UserAuthentication
    {
        if (count($this->queryMany('\App\Entity\User', ['admin' => 1])) == 0) {
            // register admin user
            $user = new User();
            $user->username = 'admin';
            $user->shortname = 'Administrator';
            $user->admin = 1;
            $user->created_by = $user;
            $user->created_date = new DateTime();
            $user_auth = new UserAuthentication($user, $this->getAuthDriver('password'));
            $user_auth->generateResetCode();
            $this->commit($user);
            $this->commit($user_auth);
            return $user_auth;
        }
        return null;
    }
}