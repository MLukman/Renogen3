<?php

namespace App\Service;

use App\Base\Entity;
use App\Entity\Activity;
use App\Entity\ActivityFile;
use App\Entity\Attachment;
use App\Entity\Checklist;
use App\Entity\Deployment;
use App\Entity\DeploymentRequest;
use App\Entity\Item;
use App\Entity\ItemComment;
use App\Entity\Plugin;
use App\Entity\Project;
use App\Entity\RunItem;
use App\Entity\RunItemFile;
use App\Entity\Template;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NavigationFactory
{
    /**
     * @var UrlGeneratorInterface
     */
    protected $urlgen;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function __construct(UrlGeneratorInterface $urlgen,
                                RequestStack $requestStack)
    {
        $this->urlgen = $urlgen;
        $this->requestStack = $requestStack;
    }

    /**
     * @param string $route
     * @param array $params
     * @return string The url
     */
    public function url($route = 'app_home', array $params = array(), $anchor = null)
    {
        return $this->urlgen->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL)
            .($anchor ? "#$anchor" : '');
    }

    /**
     * @param string $route
     * @param array $params
     * @return string The path
     */
    public function path($route, array $params = array(), $anchor = null)
    {
        return $this->urlgen->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_PATH)
            .($anchor ? "#$anchor" : '');
    }

    public function getBasePath()
    {
        return $this->requestStack->getMasterRequest()->getBasePath();
    }

    /**
     * @param Entity $entity
     * @return array
     */
    public function entityParams(Entity $entity)
    {
        if ($entity instanceof Project) {
            return array(
                'project' => $entity->name,
            );
        } elseif ($entity instanceof Deployment) {
            return $this->entityParams($entity->project) + array(
                'deployment' => $entity->datetimeString(),
            );
        } elseif ($entity instanceof DeploymentRequest) {
            return $this->entityParams($entity->project) + array(
                'deployment_request' => $entity->id,
            );
        } elseif ($entity instanceof Item) {
            return $this->entityParams($entity->deployment) + array(
                'item' => $entity->id,
            );
        } elseif ($entity instanceof Checklist) {
            return $this->entityParams($entity->deployment) + array(
                'checklist' => $entity->id,
            );
        } elseif ($entity instanceof Activity) {
            return $this->entityParams($entity->item) + array(
                'activity' => $entity->id,
            );
        } elseif ($entity instanceof ActivityFile) {
            return $this->entityParams($entity->activity) + array(
                'file' => $entity->id,
            );
        } elseif ($entity instanceof ItemComment) {
            return $this->entityParams($entity->item) + array(
                'comment' => $entity->id,
            );
        } elseif ($entity instanceof Attachment) {
            return $this->entityParams($entity->item) + array(
                'attachment' => $entity->id,
            );
        } elseif ($entity instanceof Template) {
            return $this->entityParams($entity->project) + array(
                'template' => $entity->id,
            );
        } elseif ($entity instanceof RunItem) {
            return $this->entityParams($entity->deployment) + array(
                'runitem' => $entity->id,
            );
        } elseif ($entity instanceof RunItemFile) {
            return $this->entityParams($entity->runitem) + array(
                'file' => $entity->id,
            );
        } elseif ($entity instanceof User) {
            return array(
                'username' => $entity->username,
            );
        } elseif ($entity instanceof Plugin) {
            return $this->entityParams($entity->project) + array(
                'plugin' => $entity->name,
            );
        } else {
            return array();
        }
    }

    /**
     * @param string $route
     * @param Entity $entity
     * @param array $extras
     * @return string The path
     */
    public function entityPath($route, Entity $entity, array $extras = [],
                               $anchor = null)
    {
        return $this->path($route, $this->entityParams($entity) + $extras, $anchor);
    }

    /**
     * @param string|null $route
     * @param array $params
     * @param string|null $anchor
     * @return RedirectResponse
     */
    public function redirectRoute($route = null, Array $params = array(),
                                  $anchor = null)
    {
        return new RedirectResponse($route ? $this->path($route, $params, $anchor)
                : $this->requestStack->getMasterRequest()->getUri());
    }

    /**
     * @param string $route
     * @param Entity $entity
     * @param string|null $anchor
     * @return RedirectResponse
     */
    public function redirectForEntity($route, Entity $entity, $anchor = null)
    {
        return $this->redirectRoute($route, $this->entityParams($entity), $anchor);
    }
}