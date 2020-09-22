<?php

namespace App\ActivityTemplate\Parameter;

use App\ActivityTemplate\BaseClass;
use App\ActivityTemplate\Parameter;
use App\Base\Actionable;
use App\Entity\ActivityFile;
use App\Entity\RunItem;
use Symfony\Component\HttpFoundation\Request;

class File extends Parameter
{

    static public function create(BaseClass $template, $activityLabel,
                                  $activityDescription, $activityRequired)
    {
        return static::generateParameterSimpler($template, 'file', $activityLabel, $activityDescription, $activityRequired);
    }

    public function validateTemplateInput(array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        if (isset($input[$key])) {
            $input[$key] = $this->templateFormToDatabase($input[$key]);
        }
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
        if (empty($input[$key]) && $this->activityRequired) {
            $errors[$errkey] = array('Required');
        }
        return empty($errors);
    }

    public function templateFormToDatabase($parameter)
    {
        return $parameter;
    }

    public function activityDatabaseToForm(array $template_parameters,
                                           array $parameters, $key,
                                           Actionable $activity = null)
    {
        if ($activity && isset($parameters[$key]) && ($activity_file = $this->template->ds()->queryOne($activity->fileClass, array(
            "{$activity->actionableType}" => $activity,
            'classifier' => $parameters[$key])))) {
            /* @var $activity_file ActivityFile */
            return array(
                'fileid' => $activity_file->id,
                'filename' => $activity_file->filename,
                'filesize' => $activity_file->filestore->filesize,
                'mime_type' => $activity_file->filestore->mime_type,
            );
        }
        return null;
    }

    public function displayActivityParameter(Actionable $activity, $key)
    {
        if ($activity instanceof RunItem) {
            $class = '\App\Entity\RunItemFile';
            $parent = 'runitem';
        } else {
            $class = '\App\Entity\ActivityFile';
            $parent = 'activity';
        }
        if (isset($activity->parameters[$key])) {
            return $this->template->ds()->queryOne($class, array(
                    "$parent" => $activity,
                    'classifier' => $activity->parameters[$key]));
        }
        return null;
    }

    public function handleActivityFiles(Request $request, Actionable $activity,
                                        array &$input, $key)
    {
        if (isset($activity->parameters[$key]) && $activity->files->containsKey($activity->parameters[$key])) {
            $activity_file = $activity->files->get($activity->parameters[$key]);
            $input[$key] = $activity_file->classifier;
        } else {
            $activity_file = new ActivityFile($activity);
        }

        $files = $request->files->get('parameters');
        if (isset($files[$key]) &&
            ($file = $files[$key])) {
            $activity_file = $this->template->ds()->processFileUpload($file, $activity_file);
            $activity_file->classifier = $key;
            if ($this->template->ds()->validateEntity($activity_file)) {
                $this->template->ds()->manage($activity_file);
                $input[$key] = $activity_file->classifier;
            } elseif ($activity_file->id) {
                $this->template->ds()->reloadEntity($activity_file);
            }
        }
    }

    public function getTwigForTemplateForm()
    {
        return 'parameter/template_file.html.twig';
    }

    public function getTwigForActivityForm()
    {
        return 'parameter/activity_file.html.twig';
    }
}