<?php

namespace App\ActivityTemplate\Impl;

use App\ActivityTemplate\BaseClass;
use App\ActivityTemplate\Parameter;
use App\ActivityTemplate\RunbookGroup;
use App\Base\Actionable;
use App\Service\DataStore;
use App\Service\NavigationFactory;

class JenkinsJob extends BaseClass
{

    public function __construct(NavigationFactory $nav, DataStore $ds)
    {
        parent::__construct($nav, $ds);
        $this->addParameter('access', Parameter\Markdown::createForTemplateOnly($this, 'Jenkins Access Info', 'Numbered steps on how to access Jenkins, or simply the URL', true, 'Jenkins Access Info'));
        $this->addParameter('job', Parameter::Dropdown($this, 'Jobs Dropdown Options', 'A dropdown containing the list of the jobs defined here will be available when creating activities', true, 'Jenkin Job', 'The job path & name to execute', true));
        $this->addParameter('parameters', Parameter\MultiField::create($this, 'Job Parameters', 'Define the job parameters needed when executing the jobs', false, 'Job Parameters', '', false));
    }

    public function classTitle()
    {
        return 'Execute Jenkins Job';
    }

    public function describeActivityAsArray(Actionable $activity)
    {
        $describe = array();
        $describe["Jenkins Access"] = $this->getParameter('access')->displayTemplateParameter($activity->template, 'access');
        $describe["Jenkins Job"] = $this->getParameter('job')->displayActivityParameter($activity, 'job');
        if (($parameters = $this->getParameter('parameters')->displayActivityParameter($activity, 'parameters'))) {
            $describe["Job Parameters"] = $parameters;
        }
        return $describe;
    }

    public function convertActivitiesToRunbookGroups(array $activities)
    {
        $templates = array();
        $activities_by_template = array();

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
            $group->setTemplate('runbook/JenkinsJob.twig');
            $group->setInstructionLabel("Jenkin Access");
            $group->setInstruction($this->getParameter('access')->displayTemplateParameter($templates[$template_id], 'access'));
            foreach ($activities as $activity) {
                $row = [];
                $row["job"] = $this->getParameter('job')->displayActivityParameter($activity, 'job');
                if (($parameters = $this->getParameter('parameters')->displayActivityParameter($activity, 'parameters'))) {
                    $row["parameters"] = $parameters;
                }
                $group->addRow($activity, $row);
            }
            $groups[] = $group;
        }

        return $groups;
    }
}