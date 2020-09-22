<?php

namespace App\ActivityTemplate;

use App\Base\Actionable;
use App\Entity\Activity;
use App\Entity\FileLink;
use App\Entity\RunItemFile;
use App\Service\DataStore;
use App\Service\NavigationFactory;

abstract class BaseClass
{
    /**
     * @var NavigationFactory
     */
    protected $nav;

    /**
     * @var DataStore
     */
    protected $ds;
    private $_parameters = array();

    public function __construct(NavigationFactory $nav, DataStore $ds)
    {
        $this->nav = $nav;
        $this->ds = $ds;
    }

    final protected function addParameter($name, Parameter $parameter)
    {
        $this->_parameters[(string) $name] = $parameter;
    }

    /**
     *
     * @return Parameter[]
     */
    final public function getParameters()
    {
        return $this->_parameters;
    }

    /**
     *
     * @param type $name
     * @return Parameter
     */
    final public function getParameter($name)
    {
        return (isset($this->_parameters[(string) $name]) ?
            $this->_parameters[(string) $name] : null);
    }

    abstract public function classTitle();

    /**
     * @return array
     */
    public function describeActivityAsArray(Actionable $activity)
    {
        $desc = array();
        foreach ($this->_parameters as $key => $param) {
            if (empty($param->activityLabel)) {
                continue;
            }
            $desc[$param->activityLabel] = $param->displayActivityParameter($activity, $key);
        }
        return $desc;
    }

    /**
     * Generate signature for a particular activity
     * @param Activity $activity
     * @return string
     */
    public function activitySignature(Actionable $activity)
    {
        return md5(json_encode($this->describeActivityAsArray($activity)));
    }

    public function getDownloadLink(FileLink $filelink)
    {
        return $this->nav->entityPath($filelink instanceof RunItemFile ?
                'app_runitem_file_download' : 'app_activity_file_download', $filelink);
    }

    public function nav(): NavigationFactory
    {
        return $this->nav;
    }

    public function ds(): DataStore
    {
        return $this->ds;
    }

    /**
     * @return RunbookGroup[]
     */
    abstract public function convertActivitiesToRunbookGroups(array $activities);
}