<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Deployment;
use App\Entity\DeploymentRequest;
use App\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DeploymentRequestController extends RenoController
{
    const entityFields = array('execute_date', 'title', 'description', 'external_url',
        'external_url_label');

    /**
     * @Route("/{project}/+request/", name="app_deployment_request_create", priority=10)
     */
    public function create(Request $request, $project)
    {
        try {
            $project_obj = $this->ds->fetchProject($project);
            $this->checkAccess(['entry','approval'], $project_obj);
            $this->addEntityCrumb($project_obj);
            $this->addCreateCrumb('Request for deployment', $this->nav->entityPath('app_deployment_request_create', $project_obj));
            return $this->edit_or_create(new DeploymentRequest($project_obj), $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/+request/{deployment_request}", name="app_deployment_request_edit", priority=10)
     */
    public function edit(Request $request, $project, $deployment_request)
    {
        try {
            $deployment_request_obj = $this->ds->fetchDeploymentRequest($project, $deployment_request);
            $this->checkAccess(['entry', 'approval'], $deployment_request_obj);
            $this->addEntityCrumb($deployment_request_obj);
            $this->addEditCrumb($this->nav->entityPath('app_deployment_request_edit', $deployment_request_obj));
            return $this->edit_or_create($deployment_request_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Deployment request not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/+request/{deployment_request}/approve", name="app_deployment_request_approve", priority=10)
     */
    public function approve(Request $request, $project, $deployment_request)
    {
        try {
            $deployment_request_obj = $this->ds->fetchDeploymentRequest($project, $deployment_request);
            $this->checkAccess('approval', $deployment_request_obj);
            if ($deployment_request_obj->status == 'Approved') {
                // Already approved so we don't want double approve
                return $this->nav->redirectForEntity('app_project_view', $deployment_request_obj->project);
            }
            if (0 < $deployment_request_obj->project->getDeploymentsByDateString($deployment_request_obj->execute_date->format('YmdHi'), false, false)->count()) {
                $this->addFlash('warning', "Unable to approve deployment request '$deployment_request_obj->title' because there is already existing deployment with exact same date. Please reject the deployment request instead.");
                return $this->nav->redirectForEntity('app_project_view', $deployment_request_obj->project);
            }
            $deployment_request_obj->status = 'Approved';
            $deployment = new Deployment($deployment_request_obj->project);
            $deployment->title = $deployment_request_obj->title;
            $deployment->execute_date = $deployment_request_obj->execute_date;
            $deployment->description = $deployment_request_obj->description;
            $deployment->external_url = $deployment_request_obj->external_url;
            $deployment->external_url_label = $deployment_request_obj->external_url_label;
            $deployment_request_obj->deployment = $deployment;
            $this->ds->manage($deployment);
            $this->ds->commit();
            $this->addFlash('info', "Deployment request '$deployment_request_obj->title' has been approved and here is the deployment. You may further edit the details.");
            return $this->nav->redirectForEntity('app_deployment_edit', $deployment);
        } catch (NoResultException $ex) {
            return $this->errorPage('Deployment request not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/+request/{deployment_request}/reject", name="app_deployment_request_reject", priority=10)
     */
    public function reject(Request $request, $project, $deployment_request)
    {
        try {
            $deployment_request_obj = $this->ds->fetchDeploymentRequest($project, $deployment_request);
            $this->checkAccess('approval', $deployment_request_obj);
            $deployment_request_obj->status = 'Rejected';
            $this->ds->commit();
            $this->addFlash('info', "Deployment request '$deployment_request_obj->title' has been rejected");
            return $this->nav->redirectForEntity('app_project_view', $deployment_request_obj->project, '/requests');
        } catch (NoResultException $ex) {
            return $this->errorPage('Deployment request not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(DeploymentRequest $deployment_request,
                                      ParameterBag $post)
    {
        $context = array();
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Delete':
                    $this->ds->deleteEntity($deployment_request);
                    $this->ds->commit();
                    $this->addFlash('info', "Deployment request '$deployment_request->title' has been deleted");
                    return $this->nav->redirectForEntity('app_project_view', $deployment_request->project, '/requests');
            }

            if ($this->ds->prepareValidateEntity($deployment_request, static::entityFields, $post)) {
                $deployment_request->status = 'New';
                $this->ds->commit($deployment_request);
                $this->addFlash('info', "Deployment request '$deployment_request->title' has been successfully saved");
                return $this->nav->redirectForEntity('app_project_view', $deployment_request->project, '/requests');
            } else {
                $context['errors'] = $deployment_request->errors;
            }
        }
        $context['deployment_request'] = $deployment_request;
        $context['project'] = $deployment_request->project;
        return $this->render('deployment_request_form.html.twig', $context);
    }
}