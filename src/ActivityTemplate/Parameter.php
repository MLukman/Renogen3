<?php

namespace App\ActivityTemplate;

use App\ActivityTemplate\BaseClass;
use App\Base\Actionable;
use App\Entity\FileLink;
use App\Entity\Template;
use Symfony\Component\HttpFoundation\Request;

class Parameter
{
    public $type;
    public $templateLabel;
    public $templateDescription;
    public $templateRequired;
    public $activityLabel;
    public $activityDescription;
    public $activityRequired;

    /**
     *
     * @var BaseClass
     */
    protected $template;

    protected function __construct(BaseClass $template, $type)
    {
        $this->template = $template;
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * Config is for a parameter that needs be entered
     * during template creation but not during activity creation
     * @param type $templateLabel
     * @param type $templateDescription
     * @param type $templateRequired
     * @return \static
     */
    static public function Config(BaseClass $template, $templateLabel,
                                  $templateDescription, $templateRequired,
                                  $activityLabel = null,
                                  $activityDescription = null)
    {
        $param = new static($template, 'config');
        $param->templateLabel = $templateLabel;
        $param->templateDescription = $templateDescription;
        $param->templateRequired = (bool) $templateRequired;
        $param->activityLabel = $activityLabel;
        $param->activityDescription = $activityDescription;
        return $param;
    }

    /**
     * MultiLineConfig is for a parameter that needs be entered
     * during template creation but not during activity creation
     * @param type $templateLabel
     * @param type $templateDescription
     * @param type $templateRequired
     * @return \static
     */
    static public function MultiLineConfig(BaseClass $template, $templateLabel,
                                           $templateDescription,
                                           $templateRequired,
                                           $activityLabel = null,
                                           $activityDescription = null)
    {
        $param = new static($template, 'multilineconfig');
        $param->templateLabel = $templateLabel;
        $param->templateDescription = $templateDescription;
        $param->templateRequired = (bool) $templateRequired;
        $param->activityLabel = $activityLabel;
        $param->activityDescription = $activityDescription;
        return $param;
    }

    static public function FreeText(BaseClass $template, $activityLabel,
                                    $activityDescription, $activityRequired)
    {
        return static::generateParameterSimpler($template, 'freetext', $activityLabel, $activityDescription, $activityRequired);
    }

    static public function FreeTextWithDefault(BaseClass $template,
                                               $templateLabel,
                                               $templateDescription,
                                               $templateRequired,
                                               $activityLabel,
                                               $activityDescription,
                                               $activityRequired)
    {
        return static::generateParameter($template, 'freetext', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }
    /*
      static public function RegexText($templateLabel, $templateDescription,
      $templateRequired, $activityLabel,
      $activityDescription, $activityRequired)
      {
      return static::generateParameter('regextext', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
      } */

    static public function MultiLineText(BaseClass $template, $activityLabel,
                                         $activityDescription, $activityRequired)
    {
        return static::generateParameterSimpler($template, 'multilinetext', $activityLabel, $activityDescription, $activityRequired);
    }

    static public function MultiLineTextWithDefault(BaseClass $template,
                                                    $templateLabel,
                                                    $templateDescription,
                                                    $templateRequired,
                                                    $activityLabel,
                                                    $activityDescription,
                                                    $activityRequired)
    {
        return static::generateParameter($template, 'multilinetext', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }

    static public function MultiFreeText(BaseClass $template, $templateLabel,
                                         $templateDescription,
                                         $templateRequired, $activityLabel,
                                         $activityDescription, $activityRequired)
    {
        return static::generateParameter($template, 'multifreetext', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }

    static public function Dropdown(BaseClass $template, $templateLabel,
                                    $templateDescription, $templateRequired,
                                    $activityLabel, $activityDescription,
                                    $activityRequired)
    {
        return static::generateParameter($template, 'dropdown', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }

    static public function MultiSelect(BaseClass $template, $templateLabel,
                                       $templateDescription, $templateRequired,
                                       $activityLabel, $activityDescription,
                                       $activityRequired)
    {
        return static::generateParameter($template, 'multiselect', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
    }

    static protected function generateParameter(BaseClass $template, $type,
                                                $templateLabel,
                                                $templateDescription,
                                                $templateRequired,
                                                $activityLabel,
                                                $activityDescription,
                                                $activityRequired)
    {
        $param = new static($template, $type);
        $param->templateLabel = $templateLabel;
        $param->templateDescription = $templateDescription;
        $param->templateRequired = (bool) $templateRequired;
        $param->activityLabel = $activityLabel;
        $param->activityDescription = $activityDescription;
        $param->activityRequired = (bool) $activityRequired;
        return $param;
    }

    static protected function generateParameterSimpler(BaseClass $template,
                                                       $type, $activityLabel,
                                                       $activityDescription,
                                                       $activityRequired)
    {
        $param = new static($template, $type);
        $param->activityLabel = $activityLabel;
        $param->activityDescription = $activityDescription;
        $param->activityRequired = (bool) $activityRequired;
        return $param;
    }

    static protected function linesToCleanArray($text)
    {
        $lines = array();
        foreach (explode("\n", $text) as $t) {
            $t = trim($t);
            if (!empty($t)) {
                $lines[] = $t;
            }
        }
        return $lines;
    }

    public function validateTemplateInput(array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        if (empty($this->templateLabel)) {
            return true;
        }

        $input[$key] = $this->templateFormToDatabase($input[$key]);

        $errkey = ($error_prefix ? "$error_prefix.$key" : $key);
        if (empty($input[$key]) && $this->templateRequired) {
            $errors[$errkey] = array('Required');
        }

        return empty($errors);
    }

    public function validateActivityInput(array $template_parameters,
                                          array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        $errkey = ($error_prefix ? "$error_prefix.$key" : $key);
        switch ($this->type) {
            case 'multifreetext':
                foreach ($template_parameters[$key] as $id => $label) {
                    if (substr($label, -1) == '*' && empty($input[$key][$id])) {
                        $errors[$errkey.'.'.$id] = array('Required');
                    }
                }
                break;
            case 'dropdown':
            case 'multiselect':
                if (empty($input[$key]) && !empty($template_parameters[$key])) {
                    if (count($template_parameters[$key]) == 1) {
                        $input[$key] = array_values($template_parameters)[0];
                    } else {
                        $errors[$errkey] = array('Required');
                        return false;
                    }
                }
                break;

            default:
                if (empty($input[$key]) && $this->activityRequired) {
                    $errors[$errkey] = array('Required');
                    return false;
                }
        }
        return empty($errors);
    }

    public function activityLabel(array $map)
    {
        $label = $this->activityLabel;
        foreach ($map as $key => $value) {
            if (is_string($value)) {
                $label = str_replace('{'.$key.'}', $value, $label);
            }
        }
        return $label;
    }

    public function activityRequireInputs($templateParameter)
    {
        switch ($this->type) {
            case 'dropdown':
            case 'multiselect':
            case 'multifreetext':
                return !empty($templateParameter);
            default:
                return !empty($this->activityLabel);
        }
    }

    public function templateFormToDatabase($parameter)
    {
        switch ($this->type) {
            case 'dropdown':
            case 'multiselect':
                $cfg = array();
                $values = static::linesToCleanArray($parameter['values']);
                $texts = static::linesToCleanArray($parameter['texts']);
                $size = count($values);
                for ($i = 0; $i < $size; $i++) {
                    if (empty($values[$i])) {
                        continue;
                    }
                    $text = trim(isset($texts[$i]) && !empty(trim($texts[$i])) ?
                        $texts[$i] : $values[$i]);

                    $cfg[$values[$i]] = $text;
                }
                return $cfg;

            case 'multifreetext':
                $cfg = array();
                $keys = static::linesToCleanArray($parameter['keys']);
                $labels = static::linesToCleanArray($parameter['labels']);
                $size = min(count($keys), count($labels));
                for ($i = 0; $i < $size; $i++) {
                    if (empty($keys[$i]) || empty($labels[$i])) {
                        continue;
                    }
                    $cfg[$keys[$i]] = $labels[$i];
                }
                return $cfg;

            default:
                return $parameter;
        }
    }

    public function templateDatabaseToForm($parameter)
    {
        switch ($this->type) {
            case 'dropdown':
            case 'multiselect':
                return array(
                    'values' => implode("\n", array_keys($parameter ?: array())),
                    'texts' => implode("\n", array_values($parameter ?: array())),
                );

            case 'multifreetext':
                return array(
                    'keys' => implode("\n", array_keys($parameter ?: array())),
                    'labels' => implode("\n", array_values($parameter ?: array())),
                );

            default:
                return $parameter;
        }
    }

    public function activityDatabaseToForm(array $template_parameters,
                                           array $parameters, $key,
                                           Actionable $activity = null)
    {
        return (isset($parameters[$key]) ? $parameters[$key] : null);
    }

    public function displayActivityParameter(Actionable $activity, $key)
    {
        return (isset($activity->parameters[$key]) ? $activity->parameters[$key]
                : null);
    }

    public function displayTemplateParameter(Template $template, $key)
    {
        return (isset($template->parameters[$key]) ? $template->parameters[$key]
                : null);
    }

    public function handleActivityFiles(Request $request, Actionable $activity,
                                        array &$input, $key)
    {
        // nothing to do
    }

    public function getDownloadLink(FileLink $filelink)
    {
        return $this->template->getDownloadLink($filelink);
    }

    public function getTwigForTemplateForm()
    {
        return 'parameter/template.html.twig';
    }

    public function getTwigForActivityForm()
    {
        return 'parameter/activity.html.twig';
    }
}