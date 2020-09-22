<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Deployment as DeploymentEntity;
use App\Entity\Item;
use App\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DeploymentController extends RenoController
{
    const entityFields = array('execute_date', 'title', 'description', 'external_url',
        'external_url_label');

    /**
     * @Route("/{project}/+", name="app_deployment_create")
     */
    public function create(Request $request, $project)
    {
        try {
            $project_obj = $this->ds->fetchProject($project);
            $this->checkAccess('approval', $project_obj);
            $this->addEntityCrumb($project_obj);
            $this->addCreateCrumb('Create deployment', $this->nav->entityPath('app_deployment_create', $project_obj));
            return $this->edit_or_create(new DeploymentEntity($project_obj), $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/{deployment}", name="app_deployment_view")
     */
    public function view(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->ds->fetchDeployment($project, $deployment);
            if (is_string($deployment) && $deployment != $deployment_obj->datetimeString()) {
                return $this->nav->redirectForEntity('app_deployment_view', $deployment_obj);
            }
            $this->checkAccess('any', $deployment_obj);
            $this->addEntityCrumb($deployment_obj);
            return $this->render('deployment_view.html.twig', array(
                    'deployment' => $deployment_obj,
                    'project' => $deployment_obj->project,
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/{deployment}/edit", name="app_deployment_edit")
     */
    public function edit(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->ds->fetchDeployment($project, $deployment);
            if (is_string($deployment) && $deployment != $deployment_obj->datetimeString()) {
                return $this->nav->redirectForEntity('app_deployment_edit', $deployment_obj);
            }
            $this->checkAccess('approval', $deployment_obj);
            $this->addEntityCrumb($deployment_obj);
            $this->addEditCrumb($this->nav->entityPath('app_deployment_edit', $deployment_obj));
            return $this->edit_or_create($deployment_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Deployment not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(DeploymentEntity $deployment,
                                      ParameterBag $post)
    {
        $context = array();
        if ($post->count() > 0) {
            if ($post->get('_action') == 'Delete') {
                if ($deployment->items->count() == 0) {
                    $deployment->updated_by = $this->ds->currentUserEntity();
                    $this->ds->deleteEntity($deployment);
                    $this->ds->commit();
                    $this->addFlash('info', "Deployment '$deployment->title' has been deleted");
                    return $this->nav->redirectForEntity('app_project_view', $deployment->project);
                } else {
                    $this->addFlash('info', "Deployment '$deployment->title' cannot be deleted because it contains item(s).\nMove or delete the item(s) first.", "Invalid action", "error");
                    return $this->nav->redirectForEntity('app_deployment_edit', $deployment);
                }
            }

            if ($this->ds->prepareValidateEntity($deployment, static::entityFields, $post)) {
                $is_new = ($deployment->id == null);
                $this->ds->commit($deployment);
                $this->addFlash('info', "Deployment '$deployment->title' has been successfully saved");
                return $this->nav->redirectForEntity('app_deployment_view', $deployment);
            } else {
                $context['errors'] = $deployment->errors;
            }
        }
        $context['deployment'] = $deployment;
        $context['project'] = $deployment->project;
        return $this->render('deployment_form.html.twig', $context);
    }

    /**
     * @Route("/{project}/{deployment}/releasenote", name="app_release_note", priority=10)
     */
    public function release_note(Request $request, $project, $deployment)
    {
        $deployment_obj = $this->ds->fetchDeployment($project, $deployment);
        if (is_string($deployment) && $deployment != $deployment_obj->datetimeString()) {
            return $this->nav->redirectForEntity('app_release_note', $deployment_obj);
        }
        $this->addEntityCrumb($deployment_obj);
        $this->addCrumb('Release Note', $this->nav->entityPath('app_release_note', $deployment_obj), 'ordered list');
        $context = array(
            'deployment' => $deployment_obj,
            'project' => $deployment_obj->project,
            'items' => array(),
        );

        foreach ($deployment_obj->project->categories as $category) {
            $context['items'][$category] = array();
        }

        foreach ($deployment_obj->items as $item) {
            /* @var $item Item  */
            $context['items'][$item->category][] = $item;
        }

        return $this->render('release_note.html.twig', $context);
    }
}