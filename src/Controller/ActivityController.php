<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Activity;
use App\Exception\NoResultException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ActivityController extends RenoController
{
    const entityFields = ['title', 'stage', 'parameters'];
    const editAccess = ['entry', 'review', 'approval'];

    /**
     * @Route("/{project}/{deployment}/{item}/+", name="app_activity_create")
     */
    public function create(Request $request, $project, $deployment, $item)
    {
        try {
            $project_obj = $this->ds->fetchProject($project);
            $item_obj = $this->ds->fetchItem($project_obj, $deployment, $item);
            $this->checkAccess(static::editAccess, $item_obj);
            $this->addEntityCrumb($item_obj);
            $this->addCreateCrumb('Add activity', $this->nav->entityPath('app_activity_create', $item_obj));
            $activity_obj = new Activity($item_obj);
            $activity_obj->template = $project_obj->templates->get($request->request->get('template'));
            if ($activity_obj->template && empty($activity_obj->title)) {
                $activity_obj->title = $activity_obj->template->title;
            }
            return $this->edit_or_create($activity_obj, $request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/{deployment}/{item}/{activity}/edit", name="app_activity_edit")
     */
    public function edit(Request $request, $project, $deployment, $item,
                         $activity)
    {
        try {
            $activity_obj = $this->ds->fetchActivity($project, $deployment, $item, $activity);
            $this->checkAccess(static::editAccess, $activity_obj);
            $this->addEntityCrumb($activity_obj);
            return $this->edit_or_create($activity_obj, $request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(Activity $activity, Request $request)
    {
        $post = $request->request;
        $context = [];
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Delete':
                    $this->ds->deleteEntity($activity);
                    $this->ds->commit();
                    $this->addFlash('info', "Activity has been deleted");
                    return $this->nav->redirectForEntity('app_item_view', $activity->item);

                case 'Next':
                    $this->ds->prepareValidateEntity($activity, static::entityFields, $post);
                    $context['errors'] = $activity->errors;
                    break;

                default:
                    $errors = [];
                    if ($activity->template) {
                        $activity->priority = $activity->template->priority;
                        if (($templateClass = $activity->template->templateClass($this->ds))) {
                            $parameters = $post->get('parameters', []);
                            foreach ($templateClass->getParameters() as $param => $parameter) {
                                $parameter->handleActivityFiles($request, $activity, $parameters, $param);
                                $parameter->validateActivityInput($activity->template->parameters, $parameters, $param, $errors, 'parameters');
                            }
                            $post->set('parameters', $parameters);
                        }
                    }

                    if ($this->ds->prepareValidateEntity($activity, static::entityFields, $post)
                        && empty($errors)) {
                        $activity->calculateSignature();
                        $activity->runitem = null;
                        $this->ds->manage($activity);
                        $this->ds->commit();
                        $this->addFlash('info', "Activity has been successfully saved");
                        return $this->nav->redirectForEntity('app_item_view', $activity->item);
                    } else {
                        $context['errors'] = $errors + $activity->errors;
                    }
            }
        }
        $context['activity'] = $activity;
        return $this->render('activity_form.html.twig', $context);
    }

    /**
     * @Route("/{project}/{deployment}/{item}/{activity}/{file}", name="app_activity_file_download")
     */
    public function download_file(Request $request, $project, $deployment,
                                  $item, $activity, $file)
    {
        try {
            if (!($activity_file = $this->ds->queryOne('\App\Entity\ActivityFile', $file))) {
                throw new NoResultException("No such activity file with id '$file'");
            }
            return $activity_file->returnDownload();
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}