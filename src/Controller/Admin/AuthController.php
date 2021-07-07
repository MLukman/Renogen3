<?php

namespace App\Controller\Admin;

use App\Base\RenoController;
use App\Entity\AuthDriver;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends RenoController
{

    /**
     * @Route("/!auth", name="app_admin_auth", priority=10)
     */
    public function index(Request $request)
    {
        $this->requireAdminRole();
        $this->title = 'Authentication';
        $this->addCrumb('Authentication', $this->nav->path('app_admin_auth'), 'lock');
        return $this->render('admin/auth_list.html.twig', array('drivers' => $this->ds->queryMany('\App\Entity\AuthDriver')));
    }

    /**
     * @Route("/!auth/+", name="app_admin_auth_create", priority=10)
     */
    public function create(Request $request)
    {
        $this->requireAdminRole();
        $this->title = 'Create Authentication';
        $this->addCrumb('Authentication', $this->nav->path('app_admin_auth'), 'lock');
        $this->addCreateCrumb('Create new authentication', $this->nav->path('app_admin_auth_create'));
        return $this->edit_or_create(new AuthDriver(), $request->request);
    }

    /**
     * @Route("/!auth/{driver}", name="app_admin_auth_edit", priority=10)
     */
    public function edit(Request $request, $driver)
    {
        $this->requireAdminRole();
        $this->title = "Edit '$driver' Authentication";
        $this->addCrumb('Authentication', $this->nav->path('app_admin_auth'), 'lock');
        $this->addEditCrumb($this->nav->path('app_admin_auth_edit', array('driver' => $driver)));
        $auth = $this->ds->queryOne('\App\Entity\AuthDriver', $driver);
        return $this->edit_or_create($auth, $request->request);
    }

    protected function edit_or_create(AuthDriver $auth, ParameterBag $post)
    {
        $errors = array();
        $ds = $this->ds;
        if ($post->count() > 0) {
            if ($post->get('_action') == 'Delete') {
                if ($auth->name == 'password') {
                    $this->addFlash("info", "Authentication '$auth->name' cannot be deleted");
                    return $this->nav->redirectRoute('admin_auth');
                }
                $ds->deleteEntity($auth);
                $ds->commit();
                $this->addFlash("info", "Authentication '$auth->name' has been deleted");
                return $this->nav->redirectRoute('app_admin_auth');
            }
            if (!$post->has('parameters')) {
                $post->set('parameters', array());
            }
            $attributes = array('title', 'parameters', 'allow_self_registration',
                'registration_explanation');
            if (!$auth->created_date) {
                $attributes[] = 'name';
                $attributes[] = 'class';
            }
            if (!$ds->prepareValidateEntity($auth, $attributes, $post)) {
                $errors = $auth->errors;
            }
            if (class_exists($auth->class) &&
                ($p_errors = call_user_func(array($auth->class, 'checkParams'), $auth->parameters))) {
                $errors['parameters'] = $p_errors;
            }
            if (empty($errors)) {
                $ds->commit($auth);
                return $this->nav->redirectRoute('app_admin_auth');
            }
        }
        return $this->render('admin/auth_form.html.twig', array(
                'auth' => $auth,
                'classes' => $this->ds->getAuthClassNames(),
                'paramConfigs' => ($auth->class ? call_user_func(array($auth->class,
                    'getParamConfigs')) : null),
                'errors' => $errors,
        ));
    }
}