<?php

namespace App\ActivityTemplate\Parameter;

use App\ActivityTemplate\BaseClass;
use App\ActivityTemplate\Parameter;
use App\Base\Actionable;
use App\Entity\Template;
use ParsedownExtraPlugin;

class Markdown extends Parameter
{
    public $for_template = false;
    public $for_activity = false;

    static public function parse($raw)
    {
        $parser = new ParsedownExtraPlugin();
        $parser->setSafeMode(true);
        $parser->table_class = 'ui celled table';
        return $parser->text($raw);
    }

    static public function create(BaseClass $template, $activityLabel,
                                  $activityDescription, $activityRequired)
    {
        $param = static::generateParameterSimpler($template, 'markdown', $activityLabel, $activityDescription, $activityRequired);
        $param->for_activity = true;
        return $param;
    }

    static public function createForTemplateOnly(BaseClass $template,
                                                 $templateLabel,
                                                 $templateDescription,
                                                 $templateRequired,
                                                 $activityLabel = null)
    {
        $param = static::generateParameter($template, 'markdown', $templateLabel, $templateDescription, $templateRequired, $activityLabel
                    ?: $templateLabel, null, null);
        $param->for_template = true;
        return $param;
    }

    static public function createWithDefault(BaseClass $template,
                                             $templateLabel,
                                             $templateDescription,
                                             $templateRequired, $activityLabel,
                                             $activityDescription,
                                             $activityRequired)
    {
        $param = static::generateParameter($template, 'markdown', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
        $param->for_template = true;
        $param->for_activity = true;
        return $param;
    }

    public function displayActivityParameter(Actionable $activity, $key)
    {
        $param = (isset($activity->parameters[$key]) ?
            $activity->parameters[$key] : null);

        return static::parse($param);
    }

    public function displayTemplateParameter(Template $template, $key)
    {
        $param = (isset($template->parameters[$key]) ?
            $template->parameters[$key] : null);

        return '<div class="markdown">'.static::parse($param).'</div>';
    }

    public function getTwigForTemplateForm()
    {
        return 'parameter/template_markdown.html.twig';
    }

    public function getTwigForActivityForm()
    {
        return 'parameter/activity_markdown.html.twig';
    }
}