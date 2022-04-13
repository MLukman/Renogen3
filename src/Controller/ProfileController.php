<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Project;
use App\Entity\UserAuthentication;
use App\Security\Authentication\Driver\Password;
use App\Security\Authentication\OAuth2Authenticator;
use App\Service\DataStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends RenoController
{

    /**
     * @Route("/.profile", name="app_profile", priority=20)
     */
    public function profile(Request $request)
    {
        $this->title = 'Profile';
        $this->addCrumb('Profile', $this->nav->path('app_profile'), 'id badge');

        $queries = [
            'deployment_created' => 'SELECT p.name project, COUNT(DISTINCT d.id) AS contribs FROM \App\Entity\Deployment d JOIN \App\Entity\Project p WITH d.project = p WHERE d.created_by = :user GROUP BY p.name',
            'deployment_requested' => 'SELECT p.name project, COUNT(DISTINCT d.id) AS contribs FROM \App\Entity\DeploymentRequest d JOIN \App\Entity\Project p WITH d.project = p WHERE d.created_by = :user GROUP BY p.name',
            'item_created' => 'SELECT p.name project, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH i.deployment = d JOIN \App\Entity\Project p WITH d.project = p WHERE i.created_by = :user GROUP BY p.name',
            'item_submitted' => [
                'SELECT p.name project, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH i.deployment = d JOIN \App\Entity\Project p WITH d.project = p JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status WHERE isl.created_by = :user GROUP BY p.name',
                [
                    'status' => Project::ITEM_STATUS_REVIEW,
                ]
            ],
            'item_reviewed' => [
                'SELECT p.name project, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH i.deployment = d JOIN \App\Entity\Project p WITH d.project = p JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status WHERE isl.created_by = :user GROUP BY p.name',
                [
                    'status' => Project::ITEM_STATUS_APPROVAL,
                ]
            ],
            'item_approved' => [
                'SELECT p.name project, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH i.deployment = d JOIN \App\Entity\Project p WITH d.project = p JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status WHERE isl.created_by = :user GROUP BY p.name',
                [
                    'status' => Project::ITEM_STATUS_READY,
                ]
            ],
            'item_rejected' => [
                'SELECT p.name project, COUNT(DISTINCT i.id) AS contribs FROM \App\Entity\Item i JOIN \App\Entity\Deployment d WITH i.deployment = d JOIN \App\Entity\Project p WITH d.project = p JOIN \App\Entity\ItemStatusLog isl WITH isl.item = i AND isl.status = :status WHERE isl.created_by = :user GROUP BY p.name',
                [
                    'status' => Project::ITEM_STATUS_REJECTED,
                ]
            ],
            'checklist_created' => 'SELECT p.name project, COUNT(DISTINCT cl.id) AS contribs FROM \App\Entity\Checklist cl JOIN \App\Entity\Deployment d WITH cl.deployment = d JOIN \App\Entity\Project p WITH d.project = p WHERE cl.created_by = :user GROUP BY p.name',
            'checklist_updated' => 'SELECT p.name project, COUNT(DISTINCT cl.id) AS contribs FROM \App\Entity\Checklist cl JOIN \App\Entity\Deployment d WITH cl.deployment = d JOIN \App\Entity\Project p WITH d.project = p JOIN \App\Entity\ChecklistUpdate clu WITH clu.checklist = cl AND clu.created_by = :user GROUP BY p.name',
            'activity_created' => 'SELECT p.name project, COUNT(DISTINCT a.id) AS contribs FROM \App\Entity\Activity a JOIN \App\Entity\Item i WITH a.item = i JOIN \App\Entity\Deployment d WITH i.deployment = d JOIN \App\Entity\Project p WITH d.project = p WHERE a.created_by = :user GROUP BY p.name',
            'activity_completed' => [
                'SELECT p.name project, COUNT(DISTINCT a.id) AS contribs FROM \App\Entity\RunItem a JOIN \App\Entity\Deployment d WITH a.deployment = d JOIN \App\Entity\Project p WITH d.project = p WHERE a.updated_by = :user AND a.status = :status GROUP BY p.name',
                [
                    'status' => Project::ITEM_STATUS_COMPLETED,
                ]
            ],
            'activity_failed' => [
                'SELECT p.name project, COUNT(DISTINCT a.id) AS contribs FROM \App\Entity\RunItem a JOIN \App\Entity\Deployment d WITH a.deployment = d JOIN \App\Entity\Project p WITH d.project = p WHERE a.updated_by = :user AND a.status = :status GROUP BY p.name',
                [
                    'status' => Project::ITEM_STATUS_FAILED,
                ]
            ],
            'attachment_uploaded' => 'SELECT p.name project, COUNT(DISTINCT a.id) AS contribs FROM \App\Entity\Attachment a JOIN \App\Entity\Item i WITH a.item = i JOIN \App\Entity\Deployment d WITH i.deployment = d JOIN \App\Entity\Project p WITH d.project = p WHERE a.created_by = :user GROUP BY p.name',
        ];

        $results = [];
        $user = $this->ds->currentUserEntity();

        $projects = [];
        $roles_count = [];
        foreach ($this->ds->em()
            ->createQuery('SELECT up FROM \App\Entity\UserProject up JOIN \App\Entity\Project p WITH up.project = p AND p.archived != 1 WHERE up.user = :user ORDER BY up.fav DESC, p.title ASC')
            ->setParameter('user', $user)->getResult() as $up) {
            $projects[] = $up->project;
            $results[$up->project->name] = [
                'total' => 0,
                'role' => $up->role,
            ];
            if (!isset($roles_count[$up->role])) {
                $roles_count[$up->role] = 1;
            } else {
                $roles_count[$up->role]++;
            }
        }

        $contrib_categories = [];
        $super_total = 0;
        foreach ($queries as $q => $dql) {
            $query = $this->ds->em()
                ->createQuery(is_array($dql) ? $dql[0] : $dql);
            if (is_array($dql)) {
                $query->setParameters($dql[1]);
            }
            $query->setParameter('user', $user);
            $contrib_categories[$q] = 0;
            foreach ($query->getArrayResult() as $result) {
                $results[$result['project']][$q] = $result['contribs'];
                $results[$result['project']]['total'] += $result['contribs'];
                $contrib_categories[$q] += $result['contribs'];
                $super_total += $result['contribs'];
            }
        }

        $oauth2 = [];
        foreach ($this->ds->queryMany('\App\Entity\AuthDriver', ['allow_self_registration' => 1]) as $auth) {
            if ($auth->driverClass() instanceof Password) {
                continue;
            }
            $oauth2[] = $auth;
        }

        return $this->render('profile.html.twig', [
                'user' => $user,
                'roles_count' => $roles_count,
                'contribs' => $results,
                'contrib_categories' => $contrib_categories,
                'super_total' => $super_total,
                'projects' => $projects,
                'oauth2' => $oauth2,
        ]);
    }

    /**
     * @Route("/.profile/oauth2/{driver}", name="app_profile_oauth2", priority=20)
     */
    public function oauth2(DataStore $ds, OAuth2Authenticator $oauth2auth,
                           Request $request, $driver)
    {
        $authDriver = $ds->queryOne('\App\Entity\AuthDriver', ['name' => $driver]);
        $result = $oauth2auth->process($request, $authDriver, $request->getUri());
        if (!$result) {
            throw new \Exception('Unable to authenticate you via the third party identity provider. Please try again.');
        }
        if (($redirect = $result->getRedirectResponse())) {
            return $redirect;
        }
        $user_info = $result->getUserInfo();

        $existing = $ds->getUserAuthentication([
            'driver_id' => $driver,
            'credential' => $user_info['username'],
        ]);

        if ($existing) {
            throw new \Exception("Your identity with {$authDriver->title} is already tied to user {$existing->user->shortname}");
        }

        $user_auth = new UserAuthentication($ds->currentUserEntity(), $authDriver, $user_info['username']);
        $user_auth->email = $user_info['email'];
        $ds->commit($user_auth);
        $this->addFlash('info', "Your {$user_auth->driver->title} login has been added");

        return $this->nav->redirectRoute('app_profile', [], 'auth');
    }

    /**
     * @Route("/.profile/editAuth/", name="app_profile_edit_auth", priority=20)
     */
    public function editAuth(DataStore $ds, OAuth2Authenticator $oauth2auth,
                             Request $request)
    {
        switch ($request->request->get('action')) {
            case 'Reset Password':
                if (($ua = $ds->getUserAuthentication([
                    'user' => $ds->currentUserEntity(),
                    'driver_id' => $request->request->get('driver'),
                    ]))) {
                    $ua->generateResetCode();
                    $ds->commit($ua);
                    return $this->nav->redirectRoute('app_login', ['reset_code' => $ua->reset_code]);
                }
            case 'Delete':
                if (!($ua = $ds->getUserAuthentication([
                    'user' => $ds->currentUserEntity(),
                    'driver_id' => $request->request->get('driver'),
                    ]))) {
                    break;
                }
                if ($ds->currentSecurityUser() != $ua) {
                    $ds->deleteEntity($ua);
                    $ds->commit();
                    $this->addFlash('info', "Your {$ua->driver->title} login has been deleted");
                }
        }
        return $this->nav->redirectRoute('app_profile', [], 'auth');
    }
}