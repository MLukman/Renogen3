<?php

namespace App\Controller;

use App\Base\RenoController;
use App\Entity\Project;
use App\Exception\NoResultException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RunbookController extends RenoController
{

    /**
     * @Route("/{project}/{deployment}/runbook/", name="app_runbook_view", priority=10)
     */
    public function view(Request $request, $project, $deployment)
    {
        try {
            $deployment_obj = $this->ds->fetchDeployment($project, $deployment);
            if (is_string($deployment) && $deployment != $deployment_obj->datetimeString()) {
                return $this->nav->redirectForEntity('app_runbook_view', $deployment_obj);
            }
            $this->checkAccess(array('approval', 'review', 'execute'), $deployment_obj->project);
            $this->addEntityCrumb($deployment_obj);
            $this->addCrumb('Run Book', $this->nav->entityPath('app_runbook_view', $deployment_obj), 'checkmark box');
            return $this->render('runbook_view.html.twig', ['deployment' => $deployment_obj]);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/{deployment}/runbook/{runitem}/{file}", name="app_runitem_file_download", priority=10)
     */
    public function download_file(Request $request, $file)
    {
        try {
            if (!($activity_file = $this->ds->queryOne('\App\Entity\RunItemFile', $file))) {
                throw new NoResultException("No such run item file with id '$file'");
            }
            return $activity_file->returnDownload();
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }

    /**
     * @Route("/{project}/{deployment}/runbook/{runitem}", name="app_runitem_update", priority=10)
     */
    public function runitem_update(Request $request, $runitem)
    {
        try {
            if (!($runitem = $this->ds->queryOne('\App\Entity\RunItem', $runitem))) {
                throw new NoResultException("No such run item with id '$file'");
            }
            if (($status = $request->request->get('new_status'))) {
                $runitem->status = $status;
                $this->ds->commit($runitem);
                $remark = $request->request->get('remark');

                switch ($status) {
                    case Project::ITEM_STATUS_COMPLETED:
                        foreach ($runitem->activities as $activity) {
                            if ($activity->item->status == $status) {
                                continue;
                            }
                            foreach ($activity->item->activities as $item_activity) {
                                if ($item_activity->runitem->status != $status) {
                                    continue 2;
                                }
                            }
                            $old_status = $activity->item->status;
                            $activity->item->changeStatus($status, $remark);
                            $this->ds->commit($activity->item);
                        }
                        break;
                    case Project::ITEM_STATUS_FAILED:
                        foreach ($runitem->activities as $activity) {
                            if ($activity->item->status == $status) {
                                continue;
                            }
                            $old_status = $activity->item->status;
                            $activity->item->changeStatus($status, $remark);
                            $this->ds->commit($activity->item);
                        }
                        break;
                }
            }
            return $this->nav->redirectForEntity('app_runbook_view', $runitem->deployment, $runitem->id);
        } catch (NoResultException $ex) {
            return $this->errorPage('Object not found', $ex->getMessage());
        }
    }
}