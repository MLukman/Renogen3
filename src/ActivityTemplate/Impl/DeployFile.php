<?php

namespace App\ActivityTemplate\Impl;

use App\ActivityTemplate\BaseClass;
use App\ActivityTemplate\Parameter;
use App\ActivityTemplate\RunbookGroup;
use App\Base\Actionable;
use App\Service\DataStore;
use App\Service\NavigationFactory;

class DeployFile extends BaseClass
{

    public function __construct(NavigationFactory $nav, DataStore $ds)
    {
        parent::__construct($nav, $ds);
        $this->addParameter('instruction', Parameter\Markdown::create($this, 'Instruction', 'The instruction for deployment', true));
        $this->addParameter('file', Parameter\File::create($this, 'File', 'File to be deployed', true));
        $this->addParameter('nodes', Parameter::MultiSelect($this, 'Nodes', 'The list of nodes', true, 'Nodes', 'The list of nodes the file will be deployed at', true));
    }

    public function classTitle()
    {
        return 'Manually deploy file';
    }

    public function convertActivitiesToRunbookGroups(array $activities)
    {
        $templates = array();
        $activities_by_template = array();
        $added = array();

        foreach ($activities as $activity) {
            /* @var $activity Actionable */
            if (!isset($activities_by_template[$activity->template->id])) {
                $templates[$activity->template->id] = $activity->template;
                $activities_by_template[$activity->template->id] = array();
            }
            $activities_by_template[$activity->template->id][] = $activity;
        }

        $groups = array();
        foreach ($activities_by_template as $template_id => $activities) {
            $group = new RunbookGroup($templates[$template_id]->title);
            if (!empty($templates[$template_id]->description)) {
                $group->setInstruction($templates[$template_id]->description);
            }
            $group->setTemplate('runbook/DeployFile.twig');
            foreach ($activities as $activity) {
                $output = $this->describeActivityAsArray($activity);
                $signature = json_encode($output);
                $group->addRow($activity, $output);
            }
            $groups[] = $group;
        }

        return $groups;
    }
}