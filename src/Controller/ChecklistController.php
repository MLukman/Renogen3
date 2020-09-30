<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Checklist as ChecklistEntity;
use App\Entity\ChecklistUpdate;
use App\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ChecklistController extends RenoController
{
    const entityFields = array('title', 'start_datetime', 'end_datetime', 'status');

    /**
     * @Route("/{project}/{deployment}/checklist", name="app_checklist_create")
     */
    public function create(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->ds->fetchDeployment($project, $deployment);
            $this->checkAccess(array('entry', 'approval'), $deployment_obj);
            $this->addEntityCrumb($deployment_obj);
            $this->addCreateCrumb('Add checklist task', $this->nav->entityPath('app_checklist_create', $deployment_obj));
            $checklist = new ChecklistEntity($deployment_obj);
            $checklist->pics->add($this->ds->currentUserEntity());
            return $this->edit_or_create($checklist, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/{deployment}/checklist/{checklist}", name="app_checklist_edit")
     */
    public function edit(Request $request, $project, $deployment, $checklist)
    {
        try {
            $checklist_obj = $this->ds->fetchChecklist($project, $deployment, $checklist);
            if (!$checklist_obj->isUsernameAllowed($this->ds->currentUserEntity()->username, 'edit')) {
                throw new AccessDeniedException();
            }
            $this->addEntityCrumb($checklist_obj);
            return $this->edit_or_create($checklist_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(ChecklistEntity $checklist, ParameterBag $post)
    {
        $context = array(
            'post' => $post,
        );
        $ds = $this->ds;
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Delete':
                    $ds->deleteEntity($checklist);
                    $ds->commit();
                    $this->addFlash('info', "Checklist task '$checklist->title' has been deleted");
                    return $this->nav->redirectForEntity('app_deployment_view', $checklist->deployment, 'checklist');

                default:
                    $checklist->pics->clear();
                    if ($post->has('pics')) {
                        foreach ($post->get('pics') as $username) {
                            $checklist->pics->add($this->ds->fetchUser($username));
                        }
                    } else {
                        $checklist->errors['pics'][] = 'Required';
                    }
                    if (empty($post->get('title'))) {
                        $post->set('title', $post->get('template'));
                    }
                    $errors = array();
                    $fields = static::entityFields;
                    if ($checklist->created_by && !$checklist->isRoleAllowed('edit_title')) {
                        $fields = array_diff($fields, array('title'));
                    }
                    if ($ds->prepareValidateEntity($checklist, $fields, $post)) {
                        if ($checklist->id) {
                            $update = new ChecklistUpdate($checklist);
                            $update->comment = $post->get('update');
                            if ($this->ds->validateEntity($update)) {
                                $checklist->updates->add($update);
                            } else {
                                $errors['update'] = $update->errors['comment'];
                            }
                        }
                    } else {
                        $errors = $checklist->errors;
                    }

                    if (empty($errors)) {
                        $ds->commit($checklist);
                        $this->addFlash('info', "Checklist task '$checklist->title' has been successfully saved");
                        return $this->nav->redirectForEntity('app_deployment_view', $checklist->deployment, 'checklist');
                    } else {
                        $context['errors'] = $errors;
                    }
            }
        }
        $context['checklist'] = $checklist;
        $context['project'] = $checklist->deployment->project;
        $context['template'] = $post->get('template');
        return $this->render('checklist_form.html.twig', $context);
    }
}