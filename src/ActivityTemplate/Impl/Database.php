<?php

namespace App\ActivityTemplate\Impl;

use App\ActivityTemplate\BaseClass;
use App\ActivityTemplate\Parameter;
use App\ActivityTemplate\RunbookGroup;
use App\Base\Actionable;
use App\Service\DataStore;
use App\Service\NavigationFactory;

class Database extends BaseClass
{

    public function __construct(NavigationFactory $nav, DataStore $ds)
    {
        parent::__construct($nav, $ds);
        $this->addParameter('dbname', Parameter::Config($this, 'Database name', 'The well-known name the database is known as', true));
        $this->addParameter('login', Parameter::Dropdown($this, 'Logins', 'The choices of logins to the database', true, 'Login As', 'The database login the DBA needs to log in as into the database', true));
        $this->addParameter('sql', Parameter::MultiLineText($this, 'SQL', 'The SQL script', true));
    }

    public function classTitle()
    {
        return 'Execute database SQL script';
    }

    public function describeActivityAsArray(Actionable $activity)
    {
        return [
            "Login" => $activity->parameters['login'],
            "SQL" => [
                'templateString' => '{% import "parameter/macros.html.twig" as r %}{{ r.copyableSourceField(label, value) }}',
                'templateContext' => [
                    'label' => 'SQL',
                    'value' => $activity->parameters['sql']
                ],
            ]
        ];
    }

    public function convertActivitiesToRunbookGroups(array $activities)
    {
        $templates = [];
        $activities_by_template = [];
        $added = [];

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
            $group->setInstruction("Log into database '".$templates[$template_id]->parameters['dbname']."' using login(s) specified below and execute the respective SQL script(s):");
            $group->setTemplate('runbook/Database.twig');
            foreach ($activities as $activity) {
                $group->addRow($activity, [
                    'login' => $activity->parameters['login'],
                    'sql' => $activity->parameters['sql'],
                ]);
            }
            $groups[] = $group;
        }

        return $groups;
    }
}