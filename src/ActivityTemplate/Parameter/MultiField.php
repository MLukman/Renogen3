<?php

namespace App\ActivityTemplate\Parameter;

use App\ActivityTemplate\BaseClass;
use App\ActivityTemplate\Parameter;
use App\Base\Actionable;
use App\Entity\ActivityFile;
use App\Entity\RunItem;
use Symfony\Component\HttpFoundation\Request;

class MultiField extends Parameter
{
    public $allowed_types = ['freetext', 'password', 'dropdown', 'multiselect', 'multiline',
        'script', 'url', 'file', 'formatted', 'jsondropdown'];
    public $default_type = null;

    static public function create(BaseClass $template, $templateLabel,
                                  $templateDescription, $templateRequired,
                                  $activityLabel, $activityDescription,
                                  $activityRequired, $allowed_types = null,
                                  $default_type = null)
    {
        $param = static::generateParameter($template, 'multifield', $templateLabel, $templateDescription, $templateRequired, $activityLabel, $activityDescription, $activityRequired);
        if ($allowed_types) {
            $param->allowed_types = $allowed_types;
        }
        $param->default_type = $default_type;
        return $param;
    }

    public function validateTemplateInput(array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        $input[$key] = $this->templateFormToDatabase($input[$key]);
        $errkey = ($error_prefix ? "$error_prefix.$key" : $key);

        if (empty($input[$key]) && $this->templateRequired) {
            $errors[$errkey] = array('Required');
        }

        $keys = array();
        foreach ($input[$key] as $i => $p) {
            foreach (array('id', 'title', 'type') as $f) {
                if (empty($p[$f])) {
                    $errors[$errkey.'.'.$i.'.'.$f] = array('Required');
                }
            }
            if (!empty($p['id'])) {
                if (isset($keys[$p['id']])) {
                    $errors[$errkey.'.'.$i.'.id'] = array('Must be unique');
                } else {
                    $keys[$p['id']] = 1;
                }
            }
        }

        return empty($errors);
    }

    public function validateActivityInput(array $template_parameters,
                                          array &$input, $key, array &$errors,
                                          $error_prefix = '')
    {
        $errkey = ($error_prefix ? "$error_prefix.$key" : $key);
        foreach ($template_parameters[$key] as $p) {
            if (is_string($input[$key][$p['id']])) {
                $input[$key][$p['id']] = trim($input[$key][$p['id']]);
            }
            if ($p['required'] && empty($input[$key][$p['id']])) {
                $errors[$errkey.'.'.$p['id']] = array('Required');
            } elseif ($p['type'] == 'url' && !filter_var($input[$key][$p['id']], FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED)) {
                $errors[$errkey.'.'.$p['id']] = array('Must be a valid URL');
            } elseif ($p['type'] == 'formatted' && !preg_match("/^{$p['details']}$/", $input[$key][$p['id']])) {
                $errors[$errkey.'.'.$p['id']] = array('Invalid format');
            }
        }
        return empty($errors);
    }

    public function templateFormToDatabase($parameter)
    {
        $cfg = array();
        foreach ($parameter as $p) {
            if (empty($p['id']) && empty($p['title']) && empty($p['details']) && !isset($p['required'])) {
                continue;
            }
            if ($p['type'] == 'formatted') {
                $p['details'] = trim(explode("\n", $p['details'])[0]);
            } elseif ($p['type'] == 'jsondropdown') {
                $p['details'] = \json_decode($p['details'], true);
            }
            $cfg[] = array_merge(array('required' => 0), $p);
        }
        return $cfg;
    }

    public function templateDatabaseToForm($parameter)
    {
        if (!empty($parameter)) {
            foreach ($parameter as $k => $p) {
                if ($p['type'] == 'jsondropdown') {
                    $parameter[$k]['details'] = \json_encode($p['details'], JSON_PRETTY_PRINT);
                }
            }
        }
        return $parameter;
    }

    public function activityDatabaseToForm(array $template_parameters,
                                           array $parameters, $key,
                                           Actionable $activity = null)
    {
        $data = array();
        foreach ($template_parameters[$key] as $p) {
            if (!isset($parameters[$key]) || !isset($parameters[$key][$p['id']])) {
                continue;
            }
            switch ($p['type']) {
                case 'file':
                    if (($activity_file = $this->template->ds()->queryOne($activity->fileClass, array(
                        "{$activity->actionableType}" => $activity,
                        'classifier' => $parameters[$key][$p['id']])))) {
                        /* @var $activity_file ActivityFile */
                        $data[$p['id']] = array(
                            'fileid' => $activity_file->id,
                            'filename' => $activity_file->filename,
                            'filesize' => $activity_file->filestore->filesize,
                            'mime_type' => $activity_file->filestore->mime_type,
                        );
                    }
                    break;
                default:
                    $data[$p['id']] = $parameters[$key][$p['id']];
            }
        }
        return $data;
    }

    public function handleActivityFiles(Request $request, Actionable $activity,
                                        array &$input, $key)
    {
        foreach ($activity->template->parameters[$key] as $p) {
            if ($p['type'] == 'file') {
                $pid = $p['id'];
                if (isset($activity->parameters[$key][$pid]) && $activity->files->containsKey($activity->parameters[$key][$pid])) {
                    $activity_file = $activity->files->get($activity->parameters[$key][$pid]);
                    $input[$key][$pid] = $activity_file->classifier;
                } else {
                    $activity_file = new ActivityFile($activity);
                }

                $post = $request->request->get('parameters');
                $files = $request->files->get('parameters');
                if (isset($files[$key]) &&
                    isset($files[$key][$pid]) &&
                    ($file = $files[$key][$pid])) {
                    $activity_file = $this->template->ds()->processFileUpload($file, $activity_file);
                    $activity_file->classifier = "$key.$pid";
                    if ($this->template->ds()->validateEntity($activity_file)) {
                        $this->template->ds()->manage($activity_file);
                        $input[$key][$pid] = $activity_file->classifier;
                    } elseif ($activity_file->id) {
                        $this->template->ds()->reloadEntity($activity_file);
                    }
                } elseif (isset($post[$key]) &&
                    isset($post[$key][$pid.'_delete']) &&
                    $post[$key][$pid.'_delete']) {
                    $input[$key][$pid] = null;
                    $activity->files->removeElement($activity_file);
                    unset($input[$key][$pid.'_delete']);
                    continue;
                }
            }
        }
    }

    public function displayActivityParameter(Actionable $activity, $key)
    {
        $isForRunbook = ($activity instanceof RunItem);
        $options = array();
        $data = $this->activityDatabaseToForm($activity->template->parameters, $activity->parameters, $key, $activity);
        foreach ($activity->template->parameters[$key] as $p) {
            if ($isForRunbook) {
                $d = $p['id'];
            } else {
                $d = $p['title'];
            }

            //$options[$d] = null;
            if (isset($data[$p['id']])) {
                if ($p['type'] == 'file') {
                    $file = $this->template->ds()->queryOne($activity->fileClass, array(
                        "{$activity->actionableType}" => $activity,
                        'classifier' => $key.'.'.$p['id'],
                    ));
                    if ($file) {
                        $options[$d] = '<a href="'.htmlentities($this->getDownloadLink($file)).'">'.htmlentities($file->filename).'</a>';
                    }
                } elseif ($p['type'] == 'password' && !$isForRunbook) {
                    $options[$d] = '******';
                } elseif ($p['type'] == 'url') {
                    $options[$d] = '<a href="'.htmlentities($data[$p['id']]).'" target="_blank">'.htmlentities($data[$p['id']]).'</a>';
                } elseif ($p['type'] == 'script') {
                    $options[$d] = '<div class="ui form"><textarea readonly="readonly" style="font-family: monospace">'.htmlentities($data[$p['id']]).'</textarea></div>';
                } else {
                    $options[$d] = $data[$p['id']];
                }
            }
        }
        return $options;
    }

    public function getTwigForTemplateForm()
    {
        return 'parameter/template_multifield.html.twig';
    }

    public function getTwigForActivityForm()
    {
        return 'parameter/activity_multifield.html.twig';
    }
}