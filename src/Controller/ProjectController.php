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
    const entityFields = array('name', 'title', 'icon', 'description', 'approx_deployment_duration', 'attachment_file_exts');

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
    public function contributions($project)
    {
        $project_obj = $this->ds->fetchProject($project);
        $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);

        $queries = [
            'deployment_created' => 'SELECT u.username, u.shortname, COUNT(DISTINCT d.id) AS contribs FROM \App\Entity\Deployment d JOIN \App\Entity\User u WITH d.created_by = u WHERE d.project = :project GROUP BY u',
            'deployment_requested' => 'SELECT u.username, u.shortname, COUNT(DISTINCT d.id) AS contribs FROM \App\Entity\DeploymentRequest d JOIN \App\Entity\User u WITH d.created_by = u WHERE d.project = :project GROUP BY u',
            'item_created' => 'SELECT u.username, u.shortname, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d JOIN \App\Entity\User u WITH i.created_by = u GROUP BY u',
            'item_submitted' => [
                'SELECT u.username, u.shortname, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status JOIN \App\Entity\User u WITH isl.created_by = u GROUP BY u',
                [
                    'status' => Project::ITEM_STATUS_REVIEW,
                ]
            ],
            'item_reviewed' => [
                'SELECT u.username, u.shortname, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status JOIN \App\Entity\User u WITH isl.created_by = u GROUP BY u',
                [
                    'status' => Project::ITEM_STATUS_APPROVAL,
                ]
            ],
            'item_approved' => [
                'SELECT u.username, u.shortname, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status JOIN \App\Entity\User u WITH isl.created_by = u GROUP BY u',
                [
                    'status' => Project::ITEM_STATUS_READY,
                ]
            ],
            'item_rejected' => [
                'SELECT u.username, u.shortname, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status JOIN \App\Entity\User u WITH isl.created_by = u GROUP BY u',
                [
                    'status' => Project::ITEM_STATUS_REJECTED,
                ]
            ],
            'item_completed' => [
                'SELECT u.username, u.shortname, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status JOIN \App\Entity\User u WITH isl.created_by = u GROUP BY u',
                [
                    'status' => Project::ITEM_STATUS_COMPLETED,
                ]
            ],
            'item_failed' => [
                'SELECT u.username, u.shortname, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status JOIN \App\Entity\User u WITH isl.created_by = u GROUP BY u',
                [
                    'status' => Project::ITEM_STATUS_FAILED,
                ]
            ],
            'checklist_created' => 'SELECT u.username, u.shortname, COUNT(DISTINCT cl.id) AS contribs FROM \App\Entity\Checklist cl JOIN \App\Entity\Deployment d WITH d.project = :project AND cl.deployment = d JOIN \App\Entity\User u WITH cl.created_by = u GROUP BY u',
            'checklist_updated' => 'SELECT u.username, u.shortname, COUNT(DISTINCT cl.id) AS contribs FROM \App\Entity\Checklist cl JOIN \App\Entity\Deployment d WITH d.project = :project AND cl.deployment = d JOIN \App\Entity\ChecklistUpdate clu WITH clu.checklist = cl JOIN \App\Entity\User u WITH clu.created_by = u GROUP BY u',
            'activity_created' => 'SELECT u.username, u.shortname, COUNT(DISTINCT a.id) AS contribs FROM \App\Entity\Activity a JOIN \App\Entity\Item i WITH a.item = i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d JOIN \App\Entity\User u WITH a.created_by = u GROUP BY u',
            'attachment_uploaded' => 'SELECT u.username, u.shortname, COUNT(DISTINCT a.id) AS contribs FROM \App\Entity\Attachment a JOIN \App\Entity\Item i WITH a.item = i JOIN \App\Entity\Deployment d WITH d.project = :project AND i.deployment = d JOIN \App\Entity\User u WITH a.created_by = u GROUP BY u',
        ];


        $results = [];
        foreach ($queries as $q => $dql) {
            $query = $this->ds->em()
                ->createQuery(is_array($dql) ? $dql[0] : $dql);
            if (is_array($dql)) {
                $query->setParameters($dql[1]);
            }
            $query->setParameter('project', $project_obj);
            $results[$q] = [];
            foreach ($query->getArrayResult() as $result) {
                $results[$q][$result['username']] = $result['contribs'];
            }
        }

        $contribs = [];
        $totals = [];
        $super_total = 0;
        $role_counts = [];
        foreach ($project_obj->userProjects as $up) {
            $score = 0;
            $ucontrib = [];
            foreach ($queries as $q => $dql) {
                $ucontrib[$q] = $results[$q][$up->user->username] ?? 0;
                $score += $ucontrib[$q];
                if (!isset($totals[$q])) {
                    $totals[$q] = 0;
                }
                $totals[$q] += $ucontrib[$q];
                $super_total += $ucontrib[$q];
            }
            $contribs[] = [
                'user' => $up->user->getName(),
                'role' => $up->role,
                'contribs' => $ucontrib,
                'score' => $score,
            ];
            if (!isset($role_counts[$up->role])) {
                $role_counts[$up->role] = 1;
            } else {
                $role_counts[$up->role]++;
            }
        }
        usort($contribs, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        ksort($role_counts);

        $this->addEntityCrumb($project_obj);
        $this->addCrumb('Contributions', $this->nav->entityPath('app_project_contrib', $project_obj), 'hands helping');
        return $this->render('project_contribs.html.twig', [
                'project' => $project_obj,
                'users' => $contribs,
                'totals' => $totals,
                'super_total' => $super_total,
                'role_counts' => $role_counts,
                'contrib_categories' => array_keys($queries),
        ]);
    }
}