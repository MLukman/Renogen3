<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Project;
use App\Entity\UserProject;
use App\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProjectController extends RenoController
{
    const entityFields = array('name', 'title', 'icon', 'description', 'approx_deployment_duration');

    /**
     * @Route("/+", name="app_project_create")
     */
    public function create(Request $request)
    {
        $this->requireAdminRole();
        $this->title = 'Create project';
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
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project);

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

    /**
     * @Route("/{project}/contributions", name="app_project_contrib", priority=10)
     */
    public function contributions(Request $request, $project)
    {
        $project = $this->ds->fetchProject($project);
        $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project);

        $queries = [
            'deployment_created' => 'SELECT COUNT(d.id) FROM \App\Entity\Deployment d WHERE d.project = :project AND d.created_by = :user',
            'deployment_requested' => 'SELECT COUNT(dr.id) FROM \App\Entity\DeploymentRequest dr WHERE dr.project = :project AND dr.created_by = :user',
            'item_created' => 'SELECT COUNT(i.id) FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d WHERE i.created_by = :user',
            'item_submitted' => [
                'SELECT COUNT(i.id) FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d WHERE EXISTS(SELECT isl FROM \App\Entity\ItemStatusLog isl WHERE isl.item = i AND isl.created_by = :user AND isl.status = :status)',
                [
                    'status' => Project::ITEM_STATUS_REVIEW,
                ]
            ],
            'item_reviewed' => [
                'SELECT COUNT(i.id) FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d WHERE EXISTS(SELECT isl FROM \App\Entity\ItemStatusLog isl WHERE isl.item = i AND isl.created_by = :user AND isl.status = :status)',
                [
                    'status' => Project::ITEM_STATUS_APPROVAL,
                ]
            ],
            'item_approved' => [
                'SELECT COUNT(i.id) FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d WHERE EXISTS(SELECT isl FROM \App\Entity\ItemStatusLog isl WHERE isl.item = i AND isl.created_by = :user AND isl.status = :status)',
                [
                    'status' => Project::ITEM_STATUS_READY,
                ]
            ],
            'item_rejected' => [
                'SELECT COUNT(i.id) FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d WHERE EXISTS(SELECT isl FROM \App\Entity\ItemStatusLog isl WHERE isl.item = i AND isl.created_by = :user AND isl.status = :status)',
                [
                    'status' => Project::ITEM_STATUS_REJECTED,
                ]
            ],
            'item_completed' => [
                'SELECT COUNT(i.id) FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d WHERE EXISTS(SELECT isl FROM \App\Entity\ItemStatusLog isl WHERE isl.item = i AND isl.created_by = :user AND isl.status = :status)',
                [
                    'status' => Project::ITEM_STATUS_COMPLETED,
                ]
            ],
            'item_failed' => [
                'SELECT COUNT(i.id) FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d WHERE EXISTS(SELECT isl FROM \App\Entity\ItemStatusLog isl WHERE isl.item = i AND isl.created_by = :user AND isl.status = :status)',
                [
                    'status' => Project::ITEM_STATUS_FAILED,
                ]
            ],
            'checklist_created' => 'SELECT COUNT(cl.id) FROM \App\Entity\Checklist cl JOIN \App\Entity\Deployment d WITH d.project = :project AND cl.deployment = d WHERE cl.created_by = :user',
            'checklist_updated' => 'SELECT COUNT(cl.id) FROM \App\Entity\Checklist cl JOIN \App\Entity\Deployment d WITH d.project = :project AND cl.deployment = d  WHERE EXISTS(SELECT clu FROM \App\Entity\ChecklistUpdate clu WHERE clu.checklist = cl AND clu.created_by = :user)',
            'activity_created' => 'SELECT COUNT(a.id) FROM \App\Entity\Activity a JOIN \App\Entity\Item i WITH a.item = i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d WHERE a.created_by = :user',
            'attachment_uploaded' => 'SELECT COUNT(a.id) FROM \App\Entity\Attachment a JOIN \App\Entity\Item i WITH a.item = i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d WHERE a.created_by = :user',
        ];

        $contribs = [];
        foreach ($project->userProjects as $up) {
            $results = [];
            $score = 0;
            foreach ($queries as $q => $dql) {
                $query = $this->ds->em()
                    ->createQuery(is_array($dql) ? $dql[0] : $dql);
                if (is_array($dql)) {
                    $query->setParameters($dql[1]);
                }
                $query->setParameter('project', $project)
                    ->setParameter('user', $up->user);

                $results[$q] = $query->getSingleScalarResult();
                $score += $results[$q];
            }
            $contribs[] = [
                'user' => $up->user->getName(),
                'role' => $up->role,
                'contribs' => $results,
                'score' => $score,
            ];
        }

        $this->addEntityCrumb($project);
        $this->addCrumb('Contributions', $this->nav->entityPath('app_project_contrib', $project), 'hands helping');
        return $this->render('project_contribs.html.twig', [
                'project' => $project,
                'users' => $contribs,
                'contrib_categories' => array_keys($queries),
        ]);
    }
}