<?php

namespace App\Base;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\Checklist;
use App\Entity\Deployment;
use App\Entity\Item;
use App\Entity\Project;
use App\Entity\Template;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class RenoController extends Controller
{
    const titleLength = 32;
    const hideOnMobileThreshold = 1;

    protected function addEntityCrumb(Entity $entity, $level = 0)
    {
        if ($entity instanceof Project) {
            $project = $entity;
            $this->title = $project->title;
            $this->addCrumb($this->title, $this->nav->entityPath('app_project_view', $project), $project->icon,
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Deployment) {
            $deployment = $entity;
            $this->addEntityCrumb($deployment->project, $level + 1);
            $this->title = $deployment->displayTitle();
            $this->addCrumb($deployment->datetimeString(true), $this->nav->entityPath('app_deployment_view', $deployment), 'calendar check o',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Item) {
            $item = $entity;
            $this->addEntityCrumb($item->deployment, $level + 1);
            $this->title = $item->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->nav->entityPath('app_item_view', $item), 'flag',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Activity) {
            $activity = $entity;
            $this->addEntityCrumb($activity->item, $level + 1);
            $this->title = $activity->displayTitle();
            $this->addCrumb(strlen($this->title) > self::titleLength ?
                    substr($this->title, 0, self::titleLength).'...' : $this->title, $this->nav->entityPath('app_activity_edit', $activity), 'add to cart',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Attachment) {
            $attachment = $entity;
            $this->addEntityCrumb($attachment->item, $level + 1);
            $this->title = $attachment->description;
            $this->addCrumb($this->title, $this->nav->entityPath('app_attachment_edit', $attachment), 'attach',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Template) {
            $template = $entity;
            $this->addEntityCrumb($template->project, $level + 1);
            $this->addCrumb('Activity templates', $this->nav->entityPath('app_template_list', $template->project), 'clipboard',
                $level > self::hideOnMobileThreshold);
            $this->title = $template->title;
            $this->addCrumb($this->title, $this->nav->entityPath('app_template_edit', $template), 'copy',
                $level > self::hideOnMobileThreshold);
        } elseif ($entity instanceof Checklist) {
            $checklist = $entity;
            $this->addEntityCrumb($checklist->deployment, $level + 1);
            $this->title = $checklist->title;
            $this->addCrumb($this->title, $this->nav->entityPath('app_checklist_edit', $checklist), 'tasks',
                $level > self::hideOnMobileThreshold);
        }
    }

    protected function addEditCrumb($path)
    {
        $this->addCrumb('Edit', $path, 'pencil');
    }

    protected function addCreateCrumb($text, $path)
    {
        $this->addCrumb($text, $path, 'plus');
    }

    protected function requireAdminRole()
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('Administrator role is required', null, 403);
        }
    }

    protected function checkAccess($attr, Entity $entity)
    {
        if ($attr == 'any') {
            $attr = array('view', 'execute', 'entry', 'review', 'approval');
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            // Admins can do anything
            return true;
        }

        if (!$this->security->isGranted($attr, $entity)) {
            $message = is_array($attr) ?
                "This page/action requires any of the following roles: ".join(', ', $attr)
                    : "This page/action requires $attr role";
            throw new AccessDeniedHttpException($message, null, 403);
        }
    }
}