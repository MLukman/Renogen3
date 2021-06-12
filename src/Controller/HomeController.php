<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Project;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends RenoController
{

    /**
     * @Route("/", name="app_home")
     */
    public function home()
    {
        $this->title = null;
        /** @var Project[] $projects */
        $projects = $this->ds->queryMany('\App\Entity\Project',
            array('archived' => false),
            array('title' => 'ASC')
        );

        // No project yet and the current user is an admin so go to create project screen
        if (count($projects) == 0 && $this->getUser() && in_array('ROLE_ADMIN', $this->getUser()->getRoles('ROLE_ADMIN'))) {
            return $this->redirectToRoute('app_project_create');
        }

        $contexts = array(
            'upcoming_deployments' => array(),
            'upcoming_deployments_hierarchy' => array(),
            'projects_with_access' => array(),
            'projects_no_access' => array(),
            'need_actions' => array(),
        );

        $roles = ['view', 'execute', 'entry', 'review', 'approval'];

        // Split projects with access and without access
        foreach ($projects as $project) {
            if ($this->security->isGranted($roles, $project)) {
                $contexts['projects_with_access'][] = $project;
            } elseif (!$project->private) {
                $contexts['projects_no_access'][] = $project;
            }
        }

        // Need actions
        $need_actions = array();
        foreach ($contexts['projects_with_access'] as $project) {
            $project_role = null;
            foreach ($roles as $role) {
                if ($this->security->isGranted($role, $project)) {
                    $project_role = $role;
                }
            }
            foreach ($project->upcoming() as $deployment) {
                $ddate = clone $deployment->execute_date;
                $ddate->setTime(0, 0);
                if (!isset($contexts['upcoming_deployments_hierarchy'][$ddate->getTimestamp()])) {
                    $contexts['upcoming_deployments_hierarchy'][$ddate->getTimestamp()]
                        = array();
                }
                if (!isset($contexts['upcoming_deployments_hierarchy'][$ddate->getTimestamp()][$deployment->execute_date->getTimestamp()])) {
                    $contexts['upcoming_deployments_hierarchy'][$ddate->getTimestamp()][$deployment->execute_date->getTimestamp()]
                        = array();
                }
                $contexts['upcoming_deployments_hierarchy'][$ddate->getTimestamp()][$deployment->execute_date->getTimestamp()][]
                    = $deployment;
                $contexts['upcoming_deployments'][] = $deployment;
                $d = array(
                    'deployment' => $deployment,
                    'items' => array(),
                    'checklists' => array(),
                    'activities' => array(),
                );
                foreach ($deployment->items as $item) {
                    switch ($item->status()) {
                        case Project::ITEM_STATUS_INIT:
                        case Project::ITEM_STATUS_REJECTED:
                        case Project::ITEM_STATUS_FAILED:
                            if ($project_role == 'entry') {
                                $d['items'][] = $item;
                            }
                            break;

                        case Project::ITEM_STATUS_REVIEW:
                            if ($project_role == 'review' || $project_role == 'approval') {
                                $d['items'][] = $item;
                            }
                            break;

                        case Project::ITEM_STATUS_APPROVAL :
                            if ($project_role == 'approval') {
                                $d['items'][] = $item;
                            }
                            break;

                        case Project::ITEM_STATUS_READY :
                            if ($project_role == 'execute') {
                                foreach ($item->activities as $activity) {
                                    if ($activity->runitem->status != 'New') {
                                        continue;
                                    }
                                    if (!isset($d['activities'][$activity->runitem->template->id])) {
                                        $d['activities'][$activity->runitem->template->id]
                                            = array(
                                            'status' => Project::ITEM_STATUS_READY,
                                            'template' => $activity->runitem->template,
                                            'runitems' => array(),
                                        );
                                    }
                                    $d['activities'][$activity->runitem->template->id]['runitems'][$activity->runitem->id]
                                        = $activity->runitem;
                                }
                            }
                            break;
                    }
                }
                foreach ($deployment->checklists as $checklist) {
                    if (!$checklist->isPending()) {
                        continue;
                    } elseif ($checklist->pics->contains($this->ds->currentUserEntity())) {
                        $d['checklists'][] = $checklist;
                    }
                }
                if ((!empty($d['items']) || !empty($d['checklists']) || !empty($d['activities']))) {
                    $k = $deployment->execute_date->getTimestamp()."-".$project->name;
                    $need_actions[$k] = $d;
                }
            }
        }

        /** SORTING * */
        if (!empty($need_actions)) {
            ksort($need_actions);
            $contexts['need_actions'] = $need_actions;
        }
        // sort deployments by key = execute_date
        foreach ($contexts['upcoming_deployments_hierarchy'] as &$deployment_dates) {
            ksort($deployment_dates);
        }
        ksort($contexts['upcoming_deployments_hierarchy']);
        // sort projects by favorite flags and then by titles
        usort($contexts['projects_with_access'], function($b, $a) {
            $a_fav = $a->userProject($this->ds->currentUserEntity())->fav;
            $b_fav = $b->userProject($this->ds->currentUserEntity())->fav;
            if ($a_fav == $b_fav) {
                return strcmp($b->title, $a->title);
            }
            return $a_fav - $b_fav;
        });

        return $this->render('home.html.twig', $contexts);
    }

    /**
     * @Route("/archived", name="app_archived", priority=10)
     */
    public function archived()
    {
        $this->title = 'Archived Projects';
        $this->addCrumb('Archived Projects', $this->nav->path('app_archived'), 'archive');
        $projects = $this->ds->queryMany('\App\Entity\Project',
            array('archived' => true),
            array('title' => 'ASC')
        );
        return $this->render('archived.html.twig', array('projects' => $projects));
    }

    /**
     * @Route("/about", name="app_about", priority=10)
     */
    public function about()
    {
        $this->title = 'About';
        $this->addCrumb('About Renogen', $this->nav->path('app_about'), 'help');
        return $this->render('about.html.twig');
    }

    /**
     * @Route("/fav/{project}/{value}", name="app_fav", priority=10)
     */
    public function fav($project, $value)
    {
        $project_obj = $this->ds->fetchProject($project);
        if (!$project_obj) {
            throw new NotFoundHttpException('Project not found');
        }
        if (!($userProject = $project_obj->userProject($this->ds->currentUserEntity()))) {
            throw new AccessDeniedException('User does not belong to this project');
        }
        $userProject->fav = !empty($value);
        $this->ds->commit($userProject);
        return new \Symfony\Component\HttpFoundation\JsonResponse();
    }
}