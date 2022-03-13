<?php

namespace App\Plugin;

use App\Entity\Project;
use App\Service\DataStore;
use App\Service\NavigationFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PluginAction
{
    /**
     *
     * @var Project
     */
    protected $project;

    /**
     *
     * @var PluginCore
     */
    protected $core;

    /**
     *
     * @var Request
     */
    protected $request;

    /**
     *
     * @var NavigationFactory
     */
    protected $nav;

    /**
     *
     * @var DataStore
     */
    protected $ds;

    /**
     *
     * @var string
     */
    protected $action;

    /**
     *
     * @var string
     */
    protected $handleBy = '';

    /**
     * 
     */
    const HANDLEBY_RESPONSE = 'response';
    const HANDLEBY_RENDER = 'render';
    const HANDLEBY_REDIRECT = 'redirect';

    /**
     *
     * @var string
     */
    protected $renderView;

    /**
     *
     * @var string
     */
    protected $redirectRoute;

    /**
     *
     * @var array
     */
    protected $redirectParams;

    /**
     *
     * @var array
     */
    protected $renderContext = [];

    /**
     *
     * @var Response
     */
    protected $response;

    public function __construct(Project $project, PluginCore $core,
                                Request $request, NavigationFactory $nav,
                                DataStore $ds, string $action = 'configure')
    {
        $this->project = $project;
        $this->core = $core;
        $this->request = $request;
        $this->nav = $nav;
        $this->ds = $ds;
        $this->action = $action;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getNav(): NavigationFactory
    {
        return $this->nav;
    }

    public function getDataStore(): DataStore
    {
        return $this->ds;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getHandleBy(): string
    {
        return $this->handleBy;
    }

    public function getRedirectRoute(): string
    {
        return $this->redirectRoute;
    }

    public function getRedirectParams(): array
    {
        return $this->redirectParams;
    }

    public function getRenderView(): ?string
    {
        return $this->renderView;
    }

    public function getRenderContext(): array
    {
        return $this->renderContext;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function render(string $view, $context = [])
    {
        $this->handleBy = self::HANDLEBY_RENDER;
        $this->renderView = $view;
        $this->renderContext = $context;
    }

    public function respond(Response $response)
    {
        $this->handleBy = self::HANDLEBY_RESPONSE;
        $this->response = $response;
    }

    public function redirect(string $route = '', array $params = [])
    {
        $this->handleBy = self::HANDLEBY_REDIRECT;
        $this->redirectRoute = $route;
        $this->redirectParams = $params;
    }
}