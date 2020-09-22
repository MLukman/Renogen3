<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Project;
use App\Entity\UserProject;
use App\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProjectController extends RenoController
{
    const entityFields = array('name', 'title', 'icon', 'description');

    /**
     * @Route("/+", name="app_project_create")
     */
    public function create(Request $request)
    {
        $this->addCreateCrumb('Create project', $this->nav->path('app_project_create'));
        return $this->edit_or_create($request->request);
    }

    /**
     * @Route("/{project}/", name="app_project_view", priority=-100)
     */
    public function view(Request $request, $project)
    {
        try {
            $project = $this->ds->fetchProject($project);
            $this->checkAccess('any', $project);
            $this->addEntityCrumb($project);
            return $this->render('project_view.html.twig', array(
                    'project' => $project
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Project not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/past", name="app_project_past", priority=10)
     */
    public function past(Request $request, $project)
    {
        try {
            $project = $this->ds->fetchProject($project);
            $this->checkAccess(array('view', 'execute', 'entry', 'review', 'approval'), $project);
            $this->addEntityCrumb($project);
            $this->addCrumb('Past deployments', $this->nav->entityPath('app_project_past', $project), 'clock');
            return $this->render('project_past.html.twig', array(
                    'project' => $project
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Project not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/users", name="app_project_users", priority=10)
     */
    public function users(Request $request, $project)
    {
        try {
            $project = $this->ds->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project);

            if ($request->request->get('_action')) {
                foreach ($request->request->get('role', array()) as $username => $role) {
                    try {
                        $project_role = $project->userProjects->containsKey($username)
                                ? $project->userProjects->get($username) : null;
                        if ($role) {
                            if (!$project_role) {
                                $project_role = new UserProject($project, $this->ds->fetchUser($username));
                                $this->ds->manage($project_role);
                            }
                            $project_role->role = $role;
                        } else {
                            if ($project_role) {
                                $this->ds->deleteEntity($project_role);
                            }
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
                $this->ds->commit();
                return $this->nav->redirectForEntity('app_project_users', $project);
            }

            $this->addEntityCrumb($project);
            $this->addCrumb('Users', $this->nav->entityPath('app_project_users', $project), 'users');
            return $this->render('project_users.html.twig', array(
                    'project' => $project,
                    'users' => $this->ds->queryMany('\App\Entity\User'),
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Project not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/edit", name="app_project_edit", priority=10)
     */
    public function edit(Request $request, $project)
    {
        try {
            $project = $this->ds->fetchProject($project);
            if (!$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted('approval', $project)) {
                throw new AccessDeniedException();
            }
            $this->addEntityCrumb($project);
            $this->addEditCrumb($this->nav->entityPath('app_project_edit', $project));
            return $this->edit_or_create($request->request, $project);
        } catch (NoResultException $ex) {
            return $this->errorPage('Project not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(ParameterBag $post,
                                      Project $project = null)
    {
        $context = array();
        if ($post->count() > 0) {
            if ($project && $post->get('_action') == 'Delete') {
                $this->ds->deleteEntity($project);
                $this->ds->commit();
                $this->addFlash('info', "Project '$project->title' has been deleted");
                return $this->nav->redirectRoute('app_home');
            }
            if ($project && $post->get('_action') == 'Archive') {
                $project->archived = true;
                $this->ds->commit($project);
                $this->addFlash('info', "Project '$project->title' has been archived");
                return $this->nav->redirectRoute('app_archived');
            }
            if ($project && $post->get('_action') == 'Unarchive') {
                $project->archived = false;
                $this->ds->commit($project);
                $this->addFlash('info', "Project '$project->title' has been unarchived");
                return $this->nav->redirectForEntity('app_project_view', $project);
            }
            if (!$project) {
                $project = new Project();
                $nuser = new UserProject($project, $this->ds->currentUserEntity());
                $nuser->role = 'approval';
                $project->userProjects->add($nuser);
            }

            $multiline2array = function($multiline) {
                return empty($multiline) ? null : explode("\n", str_replace("\r\n", "\n", $multiline));
            };
            $project->categories = $multiline2array(trim($post->get('categories')));
            $project->modules = $multiline2array(trim($post->get('modules')));
            $project->checklist_templates = $multiline2array(trim($post->get('checklist_templates')));
            $project->private = $post->get('private', false);
            if ($this->ds->prepareValidateEntity($project, static::entityFields, $post)) {
                $this->ds->commit($project);
                $this->addFlash('info', "Project '$project->title' has been successfully saved");
                return $this->nav->redirectForEntity('app_project_view', $project);
            } else {
                $context['errors'] = $project->errors;
            }
        } else if (!$project) {
            $project = new Project();
        }

        $context['project'] = $project;
        return $this->render('project_form.html.twig', $context);
    }
}