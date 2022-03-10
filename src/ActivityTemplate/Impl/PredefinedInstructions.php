<?php

namespace App\ActivityTemplate\Impl;

use App\ActivityTemplate\BaseClass;
use App\ActivityTemplate\Parameter;
use App\ActivityTemplate\RunbookGroup;
use App\Base\Actionable;
use App\Service\DataStore;
use App\Service\NavigationFactory;

class PredefinedInstructions extends BaseClass
{

    public function __construct(NavigationFactory $nav, DataStore $ds)
    {
        parent::__construct($nav, $ds);
        $this->addParameter('instructions', Parameter\Markdown::createForTemplateOnly($this, 'Instructions', 'The instructions to be performed before/during/after deployment; any variations can be configurable during activity creations by adding them to the additional details below. You can use markdown syntax to format this instructions text.', true));
        $this->addParameter('details', Parameter\MultiField::create($this, 'Details', 'Define configurable activity details to be entered when creating activities', false, 'Details', '', false));
        $this->addParameter('nodes', Parameter::MultiSelect($this, 'Nodes', 'The list of nodes', false, '{nodes_label}', 'The list of nodes the file will be deployed at', true));
        $this->addParameter('nodes_label', Parameter::Config($this, 'Label for "Nodes"', 'This label will be shown in activity form, activity list and run book if you defined list of nodes above', false));
    }

    public function classTitle()
    {
        return 'Perform predefined instructions with additional configurations';
    }

    public function prepareInstructions(Actionable $activity)
    {
        $instr = $activity->template->parameters['instructions'];
        foreach ($activity->template->parameters['details'] as $cfg) {
            $k = $cfg['id'];
            if (strpos($instr, "{{$k}}") === false ||
                ($cfg['type'] == 'password' && $activity->actionableType != 'runitem')) {
                continue;
            }
            $v = $activity->parameters['details'][$k];
            $instr = str_replace("{{$k}}", $v, $instr);
        }
        return $instr;
    }

    public function instructionsContainVariables(Actionable $activity)
    {
        foreach ($activity->template->parameters['details'] as $cfg) {
            if (false !== strpos($activity->template->parameters['instructions'], "{{$cfg['id']}}")) {
                return true;
            }
        }
        return false;
    }

    public function describeActivityAsArray(Actionable $activity)
    {
        if (empty($activity->template->parameters['nodes_label'])) {
            $activity->template->parameters['nodes_label'] = 'Nodes';
        }
        $instr = $activity->template->parameters['instructions'];
        $instr = str_replace("{@ID}", $activity->id, $instr);
        $params = $this->getParameter('details')->activityDatabaseToForm($activity->template->parameters, $activity->parameters, 'details', $activity);
        foreach ($activity->template->parameters['details'] as $cfg) {
            $k = $cfg['id'];
            if ($cfg['type'] == 'jsondropdown') {
                $options = $cfg['details'];
                if (isset($options[$params[$k]])) {
                    // $sk = sub key, $sv = sub value
                    foreach ($options[$params[$k]] as $sk => $sv) {
                        $instr = str_replace("{{$k}.{$sk}}", $sv, $instr);
                    }
                }
            }
            if (strpos($instr, "{{$k}}") === false ||
                ($cfg['type'] == 'password' && $activity->actionableType != 'runitem')) {
                continue;
            }
            if ($cfg['type'] == 'file') {
                $v = $params[$k]['filename'];
            } else {
                $v = $params[$k];
                unset($activity->parameters['details'][$k]);
            }
            if (!empty($v)) {
                $instr = str_replace("{{$k}}", $v, $instr);
            }
        }
        $activity->parameters['instructions'] = $instr;

        $describe = [];

        if (!empty($activity->template->parameters['nodes'])) {
            $nodes_param = $this->getParameter('nodes');
            $nodes_label = $nodes_param->activityLabel($activity->template->parameters);
            $describe[$nodes_label ?: "Nodes"] = $nodes_param->displayActivityParameter($activity, 'nodes');
        }

        $describe["Instructions"] = $this->getParameter('instructions')->displayActivityParameter($activity, 'instructions');

        if (($details = $this->getParameter('details')->displayActivityParameter($activity, 'details'))) {
            $describe["Details"] = $details;
        }
        return $describe;
    }

    public function convertActivitiesToRunbookGroups(array $activities)
    {
        $templates = [];
        $activities_by_template = [];

        foreach ($activities as $activity) {
            /* @var $activity Actionable */
            if (!isset($activities_by_template[$activity->template->id])) {
                $templates[$activity->template->id] = $activity->template;
                $activities_by_template[$activity->template->id] = [];
            }
            $activities_by_template[$activity->template->id][] = $activity;
        }

        $groups = [];
        foreach ($activities_by_template as $template_id => $activities) {
            $group = new RunbookGroup($templates[$template_id]->title);
            $group->setTemplate('runbook/PredefinedInstructions.twig');
            foreach ($activities as $activity) {
                $group->addRow($activity, $this->describeActivityAsArray($activity));
            }
            $groups[] = $group;
        }

        return $groups;
    }
}