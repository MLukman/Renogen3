<?php

namespace App\Controller;

use App\ActivityTemplate\Parameter\Markdown;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AjaxController
{

    /**
     * @Route("/$/markdown", name="app_ajax_markdown")
     */
    public function markdown(Request $request)
    {
        return new \Symfony\Component\HttpFoundation\Response(Markdown::parse($request->request->get("code")));
    }
}