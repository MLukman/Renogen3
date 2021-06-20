<?php

namespace App\Controller;

use App\ActivityTemplate\Parameter\Markdown;
use App\Base\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AjaxController extends Controller
{

    /**
     * @Route("/$/markdown", name="app_ajax_markdown", priority=10)
     */
    public function markdown(Request $request)
    {
        return new Response(Markdown::parse($request->request->get("code")));
    }

    /**
     * @Route("/$/fav/{project}/{value}", name="app_ajax_fav", priority=10)
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
        $this->ds->reloadEntity($project_obj);
        return new JsonResponse($project_obj->starCount());
    }
}