<?php

namespace App\Plugin\Telegram;

use App\Base\Entity;
use App\Entity\Deployment;
use App\Entity\DeploymentRequest;
use App\Entity\Item;
use App\Plugin\PluginAction;
use App\Plugin\PluginCore;
use GuzzleHttp\Client;
use function GuzzleHttp\json_decode;

class Core extends PluginCore
{
    protected $options = array(
        'bot_token' => null,
        'group_id' => null,
        'group_name' => null,
        'template_deployment_created' => '&#x1F4C5; [<b>{project}</b>] Deployment window <b>{datetime}</b> with title <a href="{url}">{title}</a> has been created by {who}',
        'template_deployment_date_changed' => '&#x1F4C5; [<b>{project}</b>] Deployment <a href="{url}">{title}</a> has changed date from <b>{old}</b> to <b>{new}</b> by {who}',
        'template_deployment_deleted' => '&#x1F4C5; [<b>{project}</b>] Deployment <b>{title} ({datetime})</b> has been deleted by {who}',
        'template_item_created' => '&#x1F4CC; [<b>{project}</b>] Item <a href="{url}">{title}</a> has been created for deployment <b>{deployment_title} ({deployment_datetime})</b> by {who}',
        'template_item_status_changed' => '&#x1F4CC; [<b>{project}</b>] Status of item <a href="{url}">{title}</a> has been changed from <b>{old}</b> to <b>{new}</b> by {who}',
        'template_item_moved' => '&#x1F4CC; [<b>{project}</b>] Item <a href="{url}">{title}</a> has moved from <b>{old_title} ({old_datetime})</b> to <b>{new_title} ({new_datetime})</b> by {who}',
        'template_item_deleted' => '&#x1F4CC; [<b>{project}</b>] Item <b>{title}</b> has been deleted from deployment <b>{deployment_title} ({deployment_datetime})</b> by {who}',
        'template_deployment_request_created' => '&#x1F514; [<b>{project}</b>] Deployment window <a href="{url}">{title}</a> has been requested for <b>{datetime}</b> by {who}',
        'template_deployment_request_approved' => '&#x1F514; [<b>{project}</b>] Deployment window <a href="{url}">{title} ({datetime})</a> has been <b>APPROVED</b> by {who}',
        'template_deployment_request_rejected' => '&#x1F514; [<b>{project}</b>] Deployment window <a href="{url}">{title} ({datetime})</a> has been <b>REJECTED</b> by {who}',
    );

    static public function getIcon()
    {
        return 'send';
    }

    static public function getTitle()
    {
        return "Telegram Notification";
    }

    protected function sendMessage($message)
    {
        if (empty($message) || substr($message, 0, 1) == '-') {
            // Not send if message template starts with a dash
            return;
        }
        $token = $this->options['bot_token'];
        $group_id = $this->options['group_id'];
        if (!$token || !$group_id) {
            return;
        }
        $client = new Client();
        $send = $client->postAsync("https://api.telegram.org/bot$token/sendMessage", array(
            'json' => array(
                'chat_id' => $group_id,
                'text' => $message,
                'parse_mode' => 'html',
            )
        ));
        register_shutdown_function(function() use ($send) {
            try {
                $send->wait();
            } catch (\Exception $ex) {
                // failed silently
            }
        });
    }

    protected function byWho()
    {
        return ($this->ds->currentUserEntity() ? "by ".$this->ds->currentUserEntity()->getName()
                : '');
    }

    protected function escape($text)
    {
        $text = preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function ($m) {
            $char = current($m);
            $utf = iconv('UTF-8', 'UCS-4', $char);
            return sprintf("&#x%s;", ltrim(strtoupper(bin2hex($utf)), "0"));
        }, $text);
        return htmlentities($text, ENT_COMPAT | ENT_HTML401, null, false);
    }

    public function onEntityCreated(Entity $entity)
    {
        if ($entity instanceof Item) {
            $this->onItemStatusUpdated($entity);
        } elseif ($entity instanceof Deployment) {
            $this->onDeploymentCreated($entity);
        } elseif ($entity instanceof DeploymentRequest) {
            $this->onDeploymentRequestEvent($entity, 'template_deployment_request_created');
        }
    }

    public function onEntityDeleted(Entity $entity)
    {
        if ($entity instanceof Item) {
            $this->onItemDeleted($entity);
        } elseif ($entity instanceof Deployment) {
            $this->onDeploymentDeleted($entity);
        }
    }

    public function onEntityUpdated(Entity $entity, array $old_values)
    {
        if ($entity instanceof Item) {
            if (isset($old_values['status'])) {
                $this->onItemStatusUpdated($entity, $old_values['status']);
            }
            if (isset($old_values['deployment'])) {
                $this->onItemMoved($entity, $old_values['deployment']);
            }
        } elseif ($entity instanceof Deployment) {
            if (isset($old_values['execute_date']) &&
                ($entity->execute_date->format('YmdHi') != $old_values['execute_date']->format('YmdHi'))) {
                $this->onDeploymentDateChanged($entity, $old_values['execute_date']);
            }
        } elseif ($entity instanceof DeploymentRequest) {
            if (isset($old_values['status'])) {
                switch ($entity->status) {
                    case 'Approved':
                        $this->onDeploymentRequestEvent($entity, 'template_deployment_request_approved');
                        break;
                    case 'Rejected':
                        $this->onDeploymentRequestEvent($entity, 'template_deployment_request_rejected');
                        break;
                }
            }
        }
    }

    protected function onDeploymentCreated(Deployment $deployment)
    {
        $message = $this->options['template_deployment_created'];
        $message = str_replace('{project}', $this->escape($deployment->project->title), $message);
        $message = str_replace('{url}', $this->escape($this->nav->url('app_deployment_view', $this->nav->entityParams($deployment))), $message);
        $message = str_replace('{title}', $this->escape($deployment->title), $message);
        $message = str_replace('{datetime}', $this->escape($deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($deployment->created_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($deployment->created_by->shortname), $message);
        $this->sendMessage($message);
    }

    protected function onDeploymentDateChanged(Deployment $deployment,
                                               \DateTime $old_date)
    {
        $message = $this->options['template_deployment_date_changed'];
        $message = str_replace('{project}', $this->escape($deployment->project->title), $message);
        $message = str_replace('{url}', $this->escape($this->nav->url('app_deployment_view', $this->nav->entityParams($deployment))), $message);
        $message = str_replace('{title}', $this->escape($deployment->title), $message);
        $message = str_replace('{old}', $this->escape($deployment->datetimeString(true, $old_date)), $message);
        $message = str_replace('{new}', $this->escape($deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($deployment->updated_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($deployment->updated_by->shortname), $message);
        $this->sendMessage($message);
    }

    protected function onDeploymentDeleted(Deployment $deployment)
    {
        $message = $this->options['template_deployment_deleted'];
        $message = str_replace('{project}', $this->escape($deployment->project->title), $message);
        $message = str_replace('{title}', $this->escape($deployment->title), $message);
        $message = str_replace('{datetime}', $this->escape($deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($deployment->updated_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($deployment->updated_by->shortname), $message);
        $this->sendMessage($message);
    }

    protected function onItemStatusUpdated(Item $item, $old_status = null)
    {
        if ($old_status) {
            $message = $this->options['template_item_status_changed'];
            $message = str_replace('{old}', $this->escape($old_status), $message);
            $message = str_replace('{new}', $this->escape($item->status), $message);
            $message = str_replace('{who}', $this->escape($item->updated_by->shortname), $message);
            $message = str_replace('{bywho}', ' by '.$this->escape($item->updated_by->shortname), $message);
        } else {
            $message = $this->options['template_item_created'];
            $message = str_replace('{who}', $this->escape($item->created_by->shortname), $message);
            $message = str_replace('{bywho}', ' by '.$this->escape($item->created_by->shortname), $message);
        }
        $message = str_replace('{project}', $this->escape($item->deployment->project->title), $message);
        $message = str_replace('{url}', $this->escape($this->nav->url('app_item_view', $this->nav->entityParams($item))), $message);
        $message = str_replace('{title}', $this->escape($item->displayTitle()), $message);
        $message = str_replace('{deployment}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{deployment_title}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{deployment_datetime}', $this->escape($item->deployment->datetimeString(true)), $message);
        $this->sendMessage($message);
    }

    protected function onItemMoved(Item $item, Deployment $old_deployment)
    {
        $message = $this->options['template_item_moved'];
        $message = str_replace('{project}', $this->escape($item->deployment->project->title), $message);
        $message = str_replace('{url}', $this->escape($this->nav->url('app_item_view', $this->nav->entityParams($item))), $message);
        $message = str_replace('{title}', $this->escape($item->displayTitle()), $message);
        $message = str_replace('{old}', $this->escape($old_deployment->title), $message);
        $message = str_replace('{old_title}', $this->escape($old_deployment->title), $message);
        $message = str_replace('{old_datetime}', $this->escape($old_deployment->datetimeString(true)), $message);
        $message = str_replace('{new}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{new_title}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{new_datetime}', $this->escape($item->deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($item->updated_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($item->updated_by->shortname), $message);
        $this->sendMessage($message);
    }

    protected function onItemDeleted(Item $item)
    {
        $message = $this->options['template_item_deleted'];
        $message = str_replace('{project}', $this->escape($item->deployment->project->title), $message);
        $message = str_replace('{title}', $this->escape($item->displayTitle()), $message);
        $message = str_replace('{deployment}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{deployment_title}', $this->escape($item->deployment->title), $message);
        $message = str_replace('{deployment_datetime}', $this->escape($item->deployment->datetimeString(true)), $message);
        $message = str_replace('{who}', $this->escape($item->updated_by->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($item->updated_by->shortname), $message);
        $this->sendMessage($message);
    }

    protected function onDeploymentRequestEvent(DeploymentRequest $deployment_request,
                                                $template)
    {
        $message = $this->options[$template];
        $message = str_replace('{project}', $this->escape($deployment_request->project->title), $message);
        $message = str_replace('{url}', $this->escape($this->nav->url('app_project_view', $this->nav->entityParams($deployment_request->project)), '/requests'), $message);
        $message = str_replace('{title}', $this->escape($deployment_request->title), $message);
        $message = str_replace('{datetime}', $this->escape($deployment_request::generateDatetimeString($deployment_request->execute_date, true)), $message);
        $message = str_replace('{who}', $this->escape((
                $deployment_request->updated_by ?: $deployment_request->created_by)->shortname), $message);
        $message = str_replace('{bywho}', ' by '.$this->escape($deployment_request->created_by->shortname), $message);
        $this->sendMessage($message);
    }

    public function handleConfigure(PluginAction $action)
    {
        $request = $action->getRequest();
        $post = array_merge($this->getOptions(),
            array('groups' => array('' => '-- Disabled --'))
        );
        $options = $this->getOptions();
        $hasUpdates = false;
        if (isset($options['group_id']) && isset($options['group_name'])) {
            $post['groups'][$options['group_id']] = $options['group_name'];
        }
        if (($token = $request->request->get('bot_token') ?: (
            isset($options['bot_token']) ? $options['bot_token'] : null))) {
            $post['bot_token'] = $token;
            $client = new Client();
            $response = $client->request('GET', "https://api.telegram.org/bot$token/getUpdates");
            $updates = json_decode($response->getBody(), true);
            $time = time();
            if (isset($updates['result'])) {
                $hasUpdates = true;
                $lastUpdateId = null;
                foreach ($updates['result'] as $update) {
                    if ($update['message']['chat']['type'] == 'group' ||
                        $update['message']['chat']['type'] == 'supergroup') {
                        $post['groups'][$update['message']['chat']['id']] = $update['message']['chat']['title'];
                    }
                    if ($time - $update['message']['date'] > 3600) {
                        $lastUpdateId = $update['update_id'];
                    }
                }
                if ($lastUpdateId) {
                    $client->request('GET', "https://api.telegram.org/bot$token/getUpdates?offset=$lastUpdateId&timeout=1");
                }
            }
        }

        switch ($request->request->get('_action')) {
            case 'Save':
                $group_id = $request->request->get('group_id');
                $group_names = $request->request->get('group_name');
                if (!$request->request->get('bot_token')) {
                    $this->deletePluginEntity();
                    $action->redirect();
                    return;
                } else if ($token) {
                    $noptions = array(
                        'bot_token' => $token,
                        'group_id' => $group_id,
                        'group_name' => ($group_id && isset($group_names[$group_id]))
                            ? $group_names[$group_id] : null,
                    );
                    foreach (array('template_deployment_created', 'template_deployment_date_changed',
                    'template_item_created', 'template_item_status_changed', 'template_item_moved',
                    'template_item_deleted') as $template) {
                        if ($request->request->get($template)) {
                            $noptions[$template] = $request->request->get($template);
                        }
                    }
                    $this->savePluginEntity($noptions);
                    $action->redirect();
                    return;
                }
                break;
        }
        $action->render('configure.html.twig', $post);
    }

    public function handleAction(PluginAction $action)
    {

    }

    public static function availableActions(): array
    {
        return array();
    }
}