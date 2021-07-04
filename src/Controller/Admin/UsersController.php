<?php

namespace App\Controller\Admin;

use App\Base\RenoController;
use App\Entity\User;
use App\Entity\UserProject;
use App\Service\DataStore;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UsersController extends RenoController
{

    /**
     * @Route("/!/users/", name="app_admin_users", priority=10)
     */
    public function index(Request $request)
    {
        $this->requireAdminRole();
        $this->title = "Users";
        $this->addCrumb('Users', $this->nav->path('app_admin_users'), 'users');
        return $this->render('admin/user_list.html.twig', array('users' => $this->ds->queryMany('\App\Entity\User')));
    }

    /**
     * @Route("/!/users/+", name="app_admin_users_create", priority=10)
     */
    public function create(Request $request)
    {
        $this->requireAdminRole();
        $this->title = "Add User";
        $this->addCrumb('Users', $this->nav->path('app_admin_users'), 'users');
        $this->addCreateCrumb('Add user', $this->nav->path('app_admin_users_create'));
        return $this->edit_or_create(new User(), $request->request);
    }

    /**
     * @Route("/!/users/{username}", name="app_admin_users_edit", priority=10)
     */
    public function edit(Request $request, $username)
    {
        $this->requireAdminRole();
        $this->title = "Edit User '$username'";
        $user = $this->ds->fetchUser($username);
        $this->addCrumb('Users', $this->nav->path('app_admin_users'), 'users');
        $this->addEditCrumb($this->nav->path('app_admin_users_edit', array('username' => $username)));
        return $this->edit_or_create($user, $request->request);
    }

    protected function edit_or_create(User $user, ParameterBag $post)
    {
        $errors = array();
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Block':
                    $user->blocked = 1;
                    $this->ds->commit();
                    $this->addFlash("info", "User '$user->username' has been blocked");
                    return $this->nav->redirectForEntity('app_admin_users_edit', $user);
                case 'Unblock':
                    $user->blocked = null;
                    $this->ds->commit();
                    $this->addFlash("info", "User '$user->username' has been unblocked");
                    return $this->nav->redirectForEntity('app_admin_users_edit', $user);
                case 'Delete':
                    $this->ds->deleteEntity($user);
                    $this->ds->commit();
                    $this->addFlash("info", "User '$user->username' has been deleted");
                    return $this->nav->redirectRoute('app_admin_users');
                case 'Reset Password':
                    $driver = $post->get('driver');
                    $user_auth = $user->authentications[$driver];
                    $res = $user_auth->driver->driverClass()->resetPassword($user_auth);
                    if ($res) {
                        $this->ds->commit($user_auth);
                        $this->addFlash('info', $res);
                    }
                    return $this->nav->redirectForEntity('app_admin_users_edit', $user);
            }

            if (!$post->has('roles')) {
                $post->set('roles', array());
            }
            if ($this->ds->prepareValidateEntity($user, array('username',
                    'shortname',
                    'email', 'roles'), $post)) {
                $this->ds->commit($user);
                if (($auth = $post->get('auth')) && !isset($user->authentications[$auth])) {
                    $user_auth = new \App\Entity\UserAuthentication($user);
                    $user_auth->driver_id = $auth;
                    $this->ds->commit($user_auth);
                }
                foreach ($post->get('project_role', array()) as $project_name => $role) {
                    try {
                        $project = $this->ds->fetchProject($project_name);
                        $project_role = $project->userProjects->containsKey($user->username)
                                ? $project->userProjects->get($user->username) : null;
                        if ($role == 'none' || empty($role) || !in_array($role, DataStore::PROJECT_ROLES)) {
                            if ($project_role) {
                                $this->ds->deleteEntity($project_role);
                            }
                        } else {
                            if (!$project_role) {
                                $project_role = new UserProject($project, $user);
                                $this->ds->manage($project_role);
                            }
                            $project_role->role = $role;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                $this->ds->commit();
                $this->addFlash("info", "User '$user->username' has been saved");
                return $this->nav->redirectForEntity('app_admin_users_edit', $user);
            } else {
                $errors = $user->errors;
            }
        }

        $has_contrib = false;
        if ($user->created_date) {
            $entities = array(
                'Activity',
                'ActivityFile',
                'Attachment',
                'AuthDriver',
                'Checklist',
                'ChecklistUpdate',
                'Deployment',
                'FileStore',
                'Item',
                'ItemComment',
                'ItemStatusLog',
                'Plugin',
                'Project',
                'RunItem',
                'RunItemFile',
                'Template',
                'UserProject',
            );
            foreach ($entities as $entity) {
                $has_contrib = $has_contrib || 0 < count($this->ds->queryUsingOr("\App\Entity\\$entity",
                            array('created_by' => $user, 'updated_by' => $user)));
            }

            // special checking for User entity because self-registered user has created_by = himself
            if (!$has_contrib) {
                $managed_users = $this->ds->queryUsingOr("\App\Entity\User",
                    array('created_by' => $user, 'updated_by' => $user));
                if (count($managed_users) > 1 ||
                    (count($managed_users) == 1 && $managed_users[0]->username != $user->username)) {
                    $has_contrib = true;
                }
            }
        }

        return $this->render('admin/user_form.html.twig', array(
                'user' => $user,
                'project_roles' => $post->get('project_role', array()),
                'has_contrib' => $has_contrib,
                'auths' => $this->ds->queryMany('\App\Entity\AuthDriver'),
                'projects' => $this->ds->queryMany('\App\Entity\Project'),
                'errors' => $errors,
        ));
    }
}