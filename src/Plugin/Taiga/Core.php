<?php

namespace App\Plugin\Taiga;

use App\Base\Entity;
use App\Entity\Deployment;
use App\Entity\Item;
use App\Entity\Project;
use App\Entity\User;
use App\Plugin\PluginAction;
use App\Plugin\PluginCore;
use App\Service\DataStore;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\JsonResponse;

class Core extends PluginCore
{
    protected $options = [
        'allow_delete_item' => false,
        'delete_fresh_item_only' => false,
        'extract_refnum_from_subject' => null,
        'auto_refnum_from_id_prefix' => null,
        'auto_refnum_from_id_lpad' => 1,
        'deployment_date_adjust' => '+0 day',
        'deployment_time' => '12:00 AM',
    ];
    protected $extract_refnum_patterns = [
        '([^\-\s]+)\s*-\s*(.*)' => 'REFNUM - Item title',
        '#([^\-\s]+)\s*\-*\s*(.*)' => '#REFNUM Item title',
        '\[([^\]\s]+)\]\s*\-*\s*(.*)' => '[REFNUM] Item title',
        '\(([^\)\s]+)\)\s*\-*\s*(.*)' => '(REFNUM) Item title',
    ];
    protected $deployment_date_adjustments = [
        '+0 day' => 'Same day',
        '-1 day' => 'The day before',
        '+1 day' => 'The next day',
        '+2 day' => 'The day after next',
        'next monday' => 'The coming Monday',
        'next tuesday' => 'The coming Tuesday',
        'next wednesday' => 'The coming Wednesday',
        'next thursday' => 'The coming Thursday',
        'next friday' => 'The coming Friday',
        'next saturday' => 'The coming Saturday',
        'next sunday' => 'The coming Sunday',
    ];

    static public function getIcon()
    {
        return 'shipping fast';
    }

    static public function getTitle()
    {
        return 'Integration with Taiga';
    }

    public function onEntityCreated(Entity $entity)
    {

    }

    public function onEntityDeleted(Entity $entity)
    {

    }

    public function onEntityUpdated(Entity $entity, array $old_values)
    {

    }

    public function handleConfigure(PluginAction $action)
    {
        if ($action->getRequest()->request->get('_action') == 'Save') {
            if (!$action->getRequest()->request->get('enabled')) {
                $this->deletePluginEntity();
            } else {
                $options = $this->getOptions();
                foreach ($options as $k => $v) {
                    $options[$k] = $action->getRequest()->request->get($k, $v);
                }
                $this->savePluginEntity($options);
            }
        }
        $action->render('configure.html.twig', [
            'plugin_entity' => $this->getPluginEntity(),
            'extract_refnum_patterns' => $this->extract_refnum_patterns,
            'deployment_date_adjustments' => $this->deployment_date_adjustments,
        ]);
    }

    public function handleAction(PluginAction $action)
    {
        switch ($action->getAction()) {
            case 'webhook':
                $payload = \json_decode($action->getRequest()->getContent(), true);
                if (is_array($payload)) {
                    $action->getDataStore()->overrideUserEntity($this->taigaUser($action->getDataStore()));
                    switch ($payload['type']) {
                        case 'milestone':
                            return $this->handleWebhookDeployment($action, $payload);
                        case 'userstory':
                            return $this->handleWebhookItem($action, $payload);
                    }
                }
                return new JsonResponse(['status' => 'success']);
        }
    }

    public static function availableActions(): array
    {
        return ['webhook' => true];
    }

    protected function handleWebhookDeployment(PluginAction $action, $payload)
    {
        $errors = null;
        $project = $action->getProject();
        $ds = $action->getDataStore();
        $pluginCore = $this;

        switch ($payload['action']) {
            case 'create':
            case 'change':
                if (!($nd = $this->findDeploymentWithTaigaId($project, $payload['data']['id'], $payload['data']['project']['permalink']))) {
                    $nd = new Deployment($project);
                    $nd->plugin_data['Taiga'] = [
                        'id' => $payload['data']['id'],
                        'project' => $payload['data']['project']['permalink'],
                    ];
                }

                $parameters = new ParameterBag([
                    'title' => $payload['data']['name'],
                    'external_url' => $payload['data']['project']['permalink'].'/taskboard/'.$payload['data']['slug'],
                    'external_url_label' => 'Taiga Taskboard',
                ]);

                // only change execute_date if milestone end date changed
                if (!isset($payload['change']) || !isset($payload['change']['diff'])
                    || $payload['change']['diff']['estimated_finish']['to'] != $payload['change']['diff']['estimated_finish']['from']) {
                    $parameters->set('execute_date', $this->makeDeploymentDate($pluginCore, $payload['data']['estimated_finish']));
                }

                if ($ds->prepareValidateEntity($nd, $parameters->keys(), $parameters)) {
                    $ds->commit($nd);
                } else {
                    $errors = $nd->errors;
                }
                $action->respond(new JsonResponse([
                        'status' => empty($errors) ? 'success' : 'failed',
                        'handled_as' => 'deployment',
                        'errors' => $errors,
                ]));
                break;

            case 'delete':
                if (($nd = $this->findDeploymentWithTaigaId($project, $payload['data']['id'], $payload['data']['project']['permalink']))
                    &&
                    $nd->items->count() == 0) {
                    $ds->deleteEntity($nd);
                    $ds->commit();
                    $action->respond(new JsonResponse([
                            'status' => 'success',
                            'message' => 'deployment deleted',
                    ]));
                } else {
                    $action->respond(new JsonResponse([
                            'status' => 'failed',
                            'message' => 'deployment not found',
                    ]));
                }
                break;
        }
    }

    protected function handleWebhookItem(PluginAction $action, $payload)
    {
        $errors = null;
        $project = $action->getProject();
        $ds = $action->getDataStore();
        $pluginCore = $this;

        $d_item = null;
        foreach ($project->deployments as $deployment) {
            foreach ($deployment->items as $item) {
                if (isset($item->plugin_data['Taiga']['id']) && $item->plugin_data['Taiga']['id']
                    == $payload['data']['id']) {
                    $d_item = $item;
                    break 2;
                }
            }
        }

        if ($d_item && ($payload['action'] == 'delete' || empty($payload['data']['milestone']))) {
            if ($pluginCore->getOptions('allow_delete_item') && (
                !$pluginCore->getOptions('delete_fresh_item_only') || ($d_item->status
                == 'Documentation' && $d_item->activities->count() == 0 && $d_item->attachments->count()
                == 0))) {
                $ds->deleteEntity($d_item);
                $ds->commit();
                return new JsonResponse([
                    'status' => 'success',
                    'message' => 'item deleted',
                ]);
            } else {
                return new JsonResponse([
                    'status' => 'failed',
                    'message' => 'item deletion disabled',
                ]);
            }
        }

        if (empty($payload['data']['milestone'])) {
            // do not process user story without milestone
            return new JsonResponse([
                'status' => 'failed',
                'message' => 'milestone not defined',
            ]);
        }
        if (!($d_deployment = $this->findDeploymentWithTaigaId($project, $payload['data']['milestone']['id'], $payload['data']['project']['permalink']))) {
            // do not process the milestone was not integrated into Renogen
            return new JsonResponse([
                'status' => 'failed',
                'message' => 'milestone not integrated into Renogen',
            ]);
        }

        $parameters = new ParameterBag([
            'external_url' => $payload['data']['project']['permalink'].'/us/'.$payload['data']['ref'],
            'external_url_label' => 'Taiga User Story',
        ]);

        if (!$d_item) {
            $d_item = new Item($d_deployment);
            $parameters->set('category', 'N/A');
            $parameters->set('modules', ['N/A']);
        } else {
            $d_item->deployment = $d_deployment;
        }

        // store taiga userstory id
        $d_item->plugin_data['Taiga'] = ['id' => $payload['data']['id']];

        $title = $payload['data']['subject'];
        $matches = null;
        if (($extract = $pluginCore->getOptions('extract_refnum_from_subject')) && preg_match("/^$extract$/", $title, $matches)) {
            $parameters->set('refnum', $matches[1]);
            $parameters->set('title', $matches[2]);
        } else {
            $parameters->set('title', $title);
            if (empty($d_item->refnum) && ($prefix = $pluginCore->getOptions('auto_refnum_from_id_prefix'))) {
                $id = $payload['data']['id'];
                $lpad = intval($pluginCore->getOptions('auto_refnum_from_id_ldap'));
                $refnum = (strlen($id) >= $lpad ? $id : str_repeat('0', $lpad - strlen($id)).$id);
                $parameters->set('refnum', $prefix.$refnum);
            }
        }

        if ($payload['data']['description']) {
            $parameters->set('description', $payload['data']['description']);
        }

        // read category & modules from tags
        $modules = [];
        foreach ($payload['data']['tags'] as $tag) {
            if (in_array($tag, $d_item->deployment->project->categories)) {
                $parameters->set('category', $tag);
            }
            if (in_array($tag, $d_item->deployment->project->modules)) {
                $modules[] = $tag;
            }
        }
        if (!empty($modules)) {
            $parameters->set('modules', $modules);
        }

        if ($ds->prepareValidateEntity($d_item, $parameters->keys(), $parameters)) {
            $ds->commit($d_item);
        } else {
            $errors = $d_item->errors;
        }

        return new JsonResponse(array(
            'status' => empty($errors) ? 'success' : 'error',
            'handled_as' => 'item',
            'errors' => $d_item->errors,
        ));
    }

    protected function findDeploymentWithTaigaId(Project $project, $id,
                                                 $taiga_project)
    {
        foreach ($project->deployments as $deployment) {
            if (isset($deployment->plugin_data['Taiga']['id']) &&
                $deployment->plugin_data['Taiga']['id'] == $id &&
                (
                !isset($deployment->plugin_data['Taiga']['project']) ||
                $deployment->plugin_data['Taiga']['project'] == $taiga_project
                )
            ) {
                return $deployment;
            }
        }
        return null;
    }

    protected function makeDeploymentDate(PluginCore &$pluginCore, $string)
    {
        $execute_date = \DateTime::createFromFormat('Y-m-d', $string, new \DateTimeZone('UTC'));

        $adjust_date = $pluginCore->getOptions('deployment_date_adjust');
        if (intval($adjust_date)) {
            $execute_date->modify("+{$adjust_date} days");
        } else {
            $execute_date->modify($adjust_date);
        }

        $matches = null;
        if (preg_match("/^(\\d+):(\\d+) (\\w+)$/", $pluginCore->getOptions('deployment_time'), $matches)) {
            if ($matches[3] == 'PM' && $matches[1] < 12) {
                $matches[1] += 12;
            } elseif (($matches[3] == 'AM' && $matches[1] == 12)) {
                $matches[1] = 0;
            }
            $execute_date->setTime($matches[1], $matches[2], 0);
        } else {
            $execute_date->setTime(0, 0, 0);
        }

        return $execute_date;
    }

    protected function taigaUser(DataStore $ds)
    {
        try {
            return $ds->fetchUser('taiga');
        } catch (\Exception $ex) {
            $taiga = new User();
            $taiga->username = 'taiga';
            $taiga->shortname = 'Taiga';
            $taiga->roles = ['ROLE_NONE'];
            $taiga->auth = 'password';
            $taiga->password = md5(random_bytes(100));
            $ds->commit($taiga);
            return $taiga;
        }
    }
}