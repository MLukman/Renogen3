<?php

namespace App\Base;

use App\Service\DataStore;
use App\Service\NavigationFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

abstract class Controller extends AbstractController
{
    private $appTitle = 'Renogen';
    private $appLogo = '/ui/logo.png';

    /**
     *
     * @var string
     */
    public $title = null;

    /**
     * Base context
     * @var array
     */
    protected $basectx = array(
        'extra_js' => array(),
        'extra_css' => array(),
    );

    /**
     * @var  DataStore
     */
    protected $ds;

    /**
     * @var  NavigationFactory
     */
    protected $nav;

    /**
     * @var  Security
     */
    protected $security;

    public function __construct(NavigationFactory $nav, Security $security,
                                DataStore $ds)
    {
        $this->basectx['controller'] = $this;
        $this->basectx['nav'] = $this->nav = $nav;
        $this->basectx['security'] = $this->security = $security;
        $this->basectx['ds'] = $this->ds = $ds;
        $this->basectx['errors'] = array();
        $this->basectx['crumbs'] = array();
        if (empty($this->title)) {
            $reflect = new \ReflectionClass($this);
            $this->title = $reflect->getShortName();
        }
    }

    public function render(string $view, array $context = array(),
                           ?Response $response = NULL): Response
    {
        $this->title .= ' :: '.$this->appTitle;
        return parent::render($view, array_merge($this->basectx, $context), $response);
    }

    public function addJS($file, $tag = null)
    {
        $this->basectx['extra_js'][$tag ?: $file] = $this->relativizeFile($file);
    }

    public function addCSS($file, $tag = null)
    {
        $this->basectx['extra_css'][$tag ?: $file] = $this->relativizeFile($file);
    }

    public function addCrumb($text, $url, $icon = null, $hide_on_mobile = false)
    {
        $this->basectx['crumbs'][] = array(
            'text' => $text,
            'url' => $url,
            'icon' => $icon,
            'hide_on_mobile' => $hide_on_mobile,
        );
    }

    protected function relativizeFile($file)
    {
        if (substr($file, 0, 7) !== "http://" && substr($file, 0, 1) !== "/") {
            $file = $this->request->getBaseUrl().'/'.$file;
        }
        return $file;
    }

    public function errorPage($title, $message)
    {
        return $this->render('error.html.twig', array(
                'error' => array(
                    'title' => $title,
                    'message' => $message,
                )
        ));
    }

    public function getAppTitle()
    {
        return $this->appTitle;
    }

    public function getAppLogo()
    {
        return $this->appLogo;
    }
}