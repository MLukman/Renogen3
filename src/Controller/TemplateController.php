<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Activity;
use App\Entity\Deployment;
use App\Entity\Item;
use App\Entity\Template;
use App\Exception\NoResultException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TemplateController extends RenoController
{
    const entityFields = array(
        'class',
        'title',
        'description',
        'disabled',
        'stage',
        'priority',
        'parameters',
    );

    /**
     * @Route("/{project}/templates/", name="app_template_list", priority=10)
     */
    public function index(Request $request, $project)
    {
        try {
            $project_obj = $this->ds->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $this->addEntityCrumb($project_obj);
            $this->addCrumb('Activity templates', $this->nav->entityPath('app_template_list', $project_obj), 'clipboard');
            return $this->render('template_list.html.twig', array('project' => $project_obj));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/templates/+", name="app_template_create", priority=10)
     */
    public function create(Request $request, $project)
    {
        try {
            $project_obj = $this->ds->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $this->addEntityCrumb($project_obj);
            //$this->addCrumb('Activity templates', $this->nav->entityPath('template_list', $project_obj), 'clipboard');
            $this->addCreateCrumb('Create activity template', $this->nav->entityPath('app_template_create', $project_obj));
            $template = null;
            if (($copyfrom = $request->query->get('copy')) && ($copytmpl = $this->ds->fetchTemplate($copyfrom))) {
                $template = clone $copytmpl;
                $template->id = null;
                $template->project = $project_obj;
            } else {
                $template = new Template($project_obj);
            }
            return $this->edit_or_create($request, $template, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/templates/{template}/", name="app_template_edit", priority=10)
     */
    public function edit(Request $request, $project, $template)
    {
        try {
            $project_obj = $this->ds->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $template_obj = $this->ds->fetchTemplate($template, $project_obj);
            $this->addEntityCrumb($template_obj);
            return $this->edit_or_create($request, $template_obj, $request->request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(Request $request, Template $template,
                                      ParameterBag $post)
    {
        $context = array();

        $this->setTemplateClassContext($context, $template->class);

        if ($post->count() > 0) {
            switch ($post->get('_action')) {

                case 'Next':
                    if (!$this->setTemplateClassContext($context, $post->get('class'))) {
                        $context['errors'] = array('class' => 'Please select a category');
                    }
                    break;

                case 'Import':
                    $file = $request->files->get('import');
                    if ($file && ($imported = json_decode(file_get_contents($file->getRealPath()), true))) {
                        if (isset($imported['class']) && $this->setTemplateClassContext($context, $imported['class'])) {
                            foreach (self::entityFields as $k) {
                                if (isset($imported[$k])) {
                                    $template->$k = $imported[$k];
                                }
                            }
                            $template->priority = 0;
                            break;
                        }
                    }
                    $context['errors'] = array('import' => 'Please select a valid activity template exported file');
                    break;

                case 'Delete':
                    $this->ds->deleteEntity($template);
                    // Adjust priority of the other templates
                    $qb = $this->ds->em()->createQueryBuilder()
                        ->select('e')
                        ->from('\App\Entity\Template', 'e')
                        ->where('e.project = :p')
                        ->andWhere('e.priority > :from')
                        ->setParameter('p', $template->project)
                        ->setParameter('from', $template->priority)
                        ->orderBy('e.priority', 'ASC');
                    $prio = 0;
                    foreach ($qb->getQuery()->getResult() as $atemplate) {
                        $atemplate->priority = ++$prio;
                    }
                    $this->ds->commit();
                    $this->addFlash('info', "Template '$template->title' has been deleted");
                    return $this->nav->redirectForEntity('app_template_list', $template);

                case 'Test Form Validation':
                    $context['sample'] = array(
                        'data' => array(),
                        'errors' => array(),
                    );
                    $context['sample']['activity'] = new Activity(new Item(new Deployment($template->project)));
                    $context['sample']['activity']->template = $template;
                    $parameters = $post->get('parameters', array());
                    foreach ($template->templateClass()->getParameters() as $param => $parameter) {
                        $parameter->handleActivityFiles($request, $context['sample']['activity'], $parameters, $param);
                        $parameter->validateActivityInput($template->parameters, $parameters, $param, $context['sample']['errors'], 'parameters');
                    }
                    $post->set('parameters', $parameters);
                    if ($this->ds->prepareValidateEntity($context['sample']['activity'], array(
                            'parameters'), $post) && empty($context['sample']['errors'])) {
                        $this->addFlash('info', "Form validation success");
                    } else {
                        $this->addFlash('info', "Form validation failure");
                    }
                    $context['project'] = $template->project;
                    $context['template'] = $template;
                    return $this->render('template_form', $context);

                case 'Create activity template':
                case 'Save activity template':
                    $this->setTemplateClassContext($context, $post->get('class'));
                    $parameters = $post->get('parameters', array());
                    $errors = array();
                    foreach ($context['class_instance']->getParameters() as $param => $parameter) {
                        $parameter->validateTemplateInput($parameters, $param, $errors, 'parameters');
                    }
                    $post->set('parameters', $parameters);
                    $oldpriority = $template->priority ?:
                        $template->project->templates->count() + 1;

                    if ($this->ds->prepareValidateEntity($template, static::entityFields, $post)
                        && empty($errors)) {
                        if ($oldpriority != $template->priority) {
                            $qb = $this->ds->em()->createQueryBuilder()
                                ->select('e')
                                ->from('\App\Entity\Template', 'e')
                                ->where('e.project = :p')
                                ->andWhere('e.priority >= :from')
                                ->andWhere('e.priority <= :to')
                                ->setParameter('p', $template->project);
                            if ($oldpriority > $template->priority) {
                                $qb->setParameter('from', $template->priority)
                                    ->setParameter('to', $oldpriority - 1)
                                    ->orderBy('e.priority', 'ASC');
                                $prio = $template->priority;
                                foreach ($qb->getQuery()->getResult() as $atemplate) {
                                    $atemplate->priority = ++$prio;
                                }
                            } else {
                                $qb->setParameter('from', $oldpriority + 1)
                                    ->setParameter('to', $template->priority)
                                    ->orderBy('e.priority', 'DESC');
                                $prio = $template->priority;
                                foreach ($qb->getQuery()->getResult() as $atemplate) {
                                    $atemplate->priority = --$prio;
                                }
                            }
                            $this->ds->commit();
                        }
                        $this->ds->commit($template);

                        // Fix: re-order templates to ensure unique order
                        $qb = $this->ds->em()->createQueryBuilder()
                            ->select('e')
                            ->from('\App\Entity\Template', 'e')
                            ->where('e.project = :p')
                            ->setParameter('p', $template->project)
                            ->orderBy('e.priority', 'ASC')
                            ->addOrderBy('e.created_date', 'DESC');
                        $prio = 0;
                        foreach ($qb->getQuery()->getResult() as $atemplate) {
                            $prio++;
                            $atemplate->priority = $prio;
                        }
                        $this->ds->commit();
                        $this->addFlash('info', "Template '$template->title' has been successfully saved");
                        return $this->nav->redirectForEntity('app_template_edit', $template);
                    } else {
                        $context['errors'] = $errors + $template->errors;
                    }
                    break;
            }
        }

        $context['project'] = $template->project;
        $context['template'] = $template;
        return $this->render('template_form.html.twig', $context);
    }

    private function setTemplateClassContext(&$context, $class)
    {
        if ($class && ($templateClass = $this->ds->getActivityTemplateClass($class))) {
            $context['class'] = $class;
            $context['class_instance'] = $templateClass;
            return true;
        }
        return false;
    }

    /**
     * @Route("/{project}/templates/{template}/export", name="app_template_export", priority=10)
     */
    public function export(Request $request, $project, $template)
    {
        try {
            $project_obj = $this->ds->fetchProject($project);
            $this->checkAccess(array('approval', 'ROLE_ADMIN'), $project_obj);
            $template_obj = $this->ds->fetchTemplate($template, $project_obj);
            $export = array();
            foreach (self::entityFields as $k) {
                $export[$k] = $template_obj->$k;
            }
            $filename = preg_replace('/[^a-z0-9]+/', '-', strtolower($template_obj->title));
            return new JsonResponse($export, 200, array(
                'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
            ));
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}