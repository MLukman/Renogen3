<?php

namespace App\Controller\Admin;

use App\Base\RenoController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SystemController extends RenoController
{

    /**
     * @Route("/!/phpinfo/", name="app_admin_phpinfo", priority=10)
     */
    public function phpinfo()
    {
        $this->title = "PHP Info";
        $this->addCrumb('PHP Info', $this->nav->path('app_admin_phpinfo'), 'php');
        return $this->render("renobase.html.twig", array(
                'content' => '<iframe id="topmargin" style="position:fixed; top:0px; left:0; bottom:0; right:0; width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden;" src="'.$this->nav->path('app_admin_phpinfo_content').'" />'
        ));
    }

    /**
     * @Route("/!/phpinfo/!/", name="app_admin_phpinfo_content", priority=10)
     */
    public function phpinfo_content()
    {
        ob_start();
        phpinfo();
        $html = ob_get_contents();
        ob_end_clean();
        return new Response($html);
    }
}