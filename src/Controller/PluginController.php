<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Project;
use App\Exception\NoResultException;
use App\Plugin\PluginAction;
use App\Plugin\PluginCore;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PluginController extends RenoController
{
    protected $project;
    protected $pluginCore;

    /**
     * @Route("/{project}/plugins", name="app_plugin_index", priority=10)
     */
    public function index($project)
    {
        try {
            $project_obj = $this->ds->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $this->addEntityCrumb($project_obj);
            $this->addCrumb('Plugins', $this->nav->entityPath('app_plugin_index', $project_obj), 'plug');

            $plugins = array();

            foreach (glob(__DIR__.'/../Plugin/*', GLOB_ONLYDIR) as $plugin) {
                $plugin = basename($plugin);
                $pclass = "\\App\\Plugin\\$plugin\\Core";
                $plugins[$pclass] = array(
                    'name' => $plugin,
                    'title' => $pclass::getTitle(),
                    'icon' => $pclass::getIcon(),
                );
            }
            return $this->render('plugin_index.html.twig', array(
                    'project' => $project_obj,
                    'pluginClasses' => $plugins,
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/plugins/{plugin}/", name="app_plugin_configure", priority=10)
     */
    public function configure(Request $request, $project, $plugin)
    {
        try {
            $project_obj = $this->ds->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);

            $pluginCore = $this->fetchPluginCore($project_obj, $plugin, true);
            $pluginAction = new PluginAction($project_obj, $pluginCore, $request, $this->nav, $this->ds);
            $pluginCore->handleConfigure($pluginAction);
            return $this->handlePluginAction($project_obj, $pluginCore, $pluginAction);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/plugins/{plugin}/{action}", name="app_plugin_action", priority=10)
     */
    public function action(Request $request, $project, $plugin, $action)
    {
        $project_obj = $this->ds->fetchProject($project);
        if ($action == 'configure') {
            return $this->nav->redirectRoute('app_plugin_configure', $this->nav->entityParams($project_obj)
                    + array('plugin' => $plugin));
        }
        if (!($pluginCore = $this->fetchPluginCore($project_obj, $plugin))) {
            throw new NoResultException("Project '$project' does not have plugin named '$pname'");
        }
        if (!in_array($action, array_keys($pluginCore::availableActions()))) {
            throw new NoResultException("Plugin '$plugin' does not support action '$action'");
        }

        $pluginAction = new PluginAction($project_obj, $pluginCore, $request, $this->nav, $this->ds, $action);
        if (($return = $pluginCore->handleAction($pluginAction)) && $return instanceof Response) {
            $pluginAction->respond($return);
        }
        return $this->handlePluginAction($project_obj, $pluginCore, $pluginAction);
    }

    protected function fetchPluginCore(Project $project, $plugin,
                                       $create_if_not_exist = false): PluginCore
    {
        $plugin_entity = $this->ds->queryOne('\\App\\Entity\\Plugin', array(
            'project' => $project,
            'name' => $plugin,
        ));
        $pluginCore = null;
        if ($plugin_entity) {
            $pluginCore = $plugin_entity->instance($this->ds, $this->nav);
        } elseif ($create_if_not_exist) {
            $pclass = "\\App\\Plugin\\$plugin\\Core";
            $pluginCore = new $pclass($this->ds, $this->nav, $project);
        }
        return $pluginCore;
    }

    protected function handlePluginAction(Project $project_obj,
                                          PluginCore $pluginCore,
                                          PluginAction $pluginAction)
    {
        switch ($pluginAction->getHandleBy()) {
            case $pluginAction::HANDLEBY_RESPONSE:
                return $pluginAction->getResponse();

            case $pluginAction::HANDLEBY_RENDER:
                $this->addEntityCrumb($project_obj);
                $this->addCrumb('Plugins', $this->nav->entityPath('app_plugin_index', $project_obj), 'plug');
                if ($pluginAction->getAction() == 'configure') {
                    $this->addCrumb($pluginCore->getName(), $this->nav->path("app_plugin_configure", $this->nav->entityParams($project_obj)
                            + array('plugin' => $pluginCore->getName())), $pluginCore->getIcon());
                } else {
                    $this->addCrumb($pluginCore->getName(), $this->nav->path("app_plugin_action", $this->nav->entityParams($project_obj)
                            + array('plugin' => $pluginCore->getName(), 'action' => $pluginAction->getAction())), $pluginCore->getIcon());
                }
                return $this->render("@plugin/".$pluginCore->getName()."/".$pluginAction->getRenderView(), array_merge_recursive(array(
                        'project' => $project_obj,
                        'core' => $pluginCore,
                        'plugin' => array(
                            'name' => $pluginCore->getName(),
                            'icon' => $pluginCore->getIcon(),
                            'title' => $pluginCore->getTitle(),
                        )), $pluginAction->getRenderContext()));

            case $pluginAction::HANDLEBY_REDIRECT:
                return $this->nav->redirectRoute($pluginAction->getRedirectRoute(), $pluginAction->getRedirectParams());
            default:
                return $this->nav->redirectRoute("app_plugin_configure", $this->nav->entityParams($project_obj)
                        + array('plugin' => $pluginCore->getName()));
        }
    }
}