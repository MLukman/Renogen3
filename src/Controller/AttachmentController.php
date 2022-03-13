<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Attachment;
use Doctrine\ORM\NoResultException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AttachmentController extends RenoController
{
    const entityFields = ['description'];
    const editAccess = ['entry', 'review', 'approval'];

    /**
     * @Route("/{project}/{deployment}/{item}/attachments", name="app_attachment_create", priority=10)
     */
    public function create(Request $request, $project, $deployment, $item)
    {
        try {
            $item_obj = $this->ds->fetchItem($project, $deployment, $item);
            $this->checkAccess(static::editAccess, $item_obj);
            $this->addEntityCrumb($item_obj);
            $this->addCreateCrumb('Add attachment', $this->nav->entityPath('app_attachment_create', $item_obj));
            $attachment_obj = new Attachment($item_obj);
            return $this->edit_or_create($attachment_obj, $request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/{deployment}/{item}/attachments/{attachment}", name="app_attachment_download", priority=10)
     */
    public function download(Request $request, $project, $deployment, $item,
                             $attachment)
    {
        try {
            $attachment_obj = $this->ds->fetchAttachment($project, $deployment, $item, $attachment);
            return $attachment_obj->returnDownload();
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/{deployment}/{item}/attachments/{attachment}/edit", name="app_attachment_edit", priority=10)
     */
    public function edit(Request $request, $project, $deployment, $item,
                         $attachment)
    {
        try {
            $attachment_obj = $this->ds->fetchAttachment($project, $deployment, $item, $attachment);
            $this->checkAccess(static::editAccess, $attachment_obj->item);
            $this->addEntityCrumb($attachment_obj);
            return $this->edit_or_create($attachment_obj, $request);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    protected function edit_or_create(Attachment $attachment, Request $request)
    {
        $post = $request->request;
        $context = ['errors' => []];
        if ($post->count() > 0) {
            switch ($post->get('_action')) {
                case 'Delete':
                    $this->ds->deleteEntity($attachment);
                    $this->ds->commit();
                    $this->addFlash('info', "Attachment has been deleted");
                    return $this->nav->redirectForEntity('app_item_view', $attachment->item);

                default:
                    $file = $request->files->get('file');
                    if ($file) {
                        $errors = [];
                        $filelink = $this->ds->processFileUpload($file, $attachment, $errors);
                        $this->ds->commit($filelink);
                        if (!empty($errors)) {
                            $context['errors'] += ['file' => $errors];
                        }
                    } elseif (!$attachment->filename) {
                        $context['errors'] += ['file' => ['Required']];
                    }
                    if ($this->ds->prepareValidateEntity($attachment, static::entityFields, $post)
                        && empty($context['errors'])) {
                        $this->ds->commit($attachment);
                        $this->addFlash('info', "Attachment has been successfully saved");
                        return $this->nav->redirectForEntity('app_item_view', $attachment->item);
                    } else {
                        $context['errors'] += $attachment->errors + ['file' => [
                                'Your file is fine but you need to re-upload your file since other field(s) failed validations'
                        ]];
                    }
            }
        }
        $context['attachment'] = $attachment;
        return $this->render('attachment_form.html.twig', $context);
    }
}