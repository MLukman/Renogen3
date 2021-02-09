<?php

namespace MLukman\MultiAuthBundle\Service;

use MLukman\MultiAuthBundle\DriverInstance;
use MLukman\MultiAuthBundle\MultiAuthAdapterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class MultiAuth
{
    private $persistence;
    private $cache;
    private $all_drivers;
    private $driver_class_names;

    public function __construct(CacheInterface $cache,
                                MultiAuthAdapterInterface $persistence)
    {
        $this->cache = $cache;
        $this->persistence = $persistence;
    }

    public function getAdapter(): MultiAuthAdapterInterface
    {
        return $this->persistence;
    }

    public function getDriverClassNames()
    {
        if (!$this->driver_class_names) {
            $globPath = __DIR__.'/../DriverClass/*.php';
            $driverNamespace = substr(__NAMESPACE__, 0, strrpos(__NAMESPACE__, '\\'));
            $this->driver_class_names = $this->cache->get('driver_class_names', function(ItemInterface $item) use($globPath, $driverNamespace) {
                $au = [];
                foreach (glob($globPath) as $fn) {
                    $shortName = basename($fn, '.php');
                    $className = "$driverNamespace\\DriverClass\\$shortName";
                    $classId = strtolower($shortName);
                    $au[$classId] = $className;
                }
                return $au;
            });
        }
        return $this->driver_class_names;
    }

    private function loadAllDrivers(): array
    {
        if (!$this->all_drivers) {
            $this->all_drivers = array();
            foreach ($this->persistence->loadAllDriverInstances() as $driver) {
                if ($driver instanceof DriverInstance) {
                    $this->all_drivers[$driver->getId()] = $driver;
                }
            }
        }
        return $this->all_drivers;
    }

    public function getDriverById(string $driver_id): DriverInstance
    {
        $drivers = $this->loadAllDrivers();
        return $drivers[$driver_id] ?? null;
    }

    public function getLoginFormDrivers(): array
    {
        $form_drivers = array();
        foreach ($this->loadAllDrivers() as $driver) {
            /** @var DriverInstance $driver */
            $login_param = $driver->getClass()->getLoginDisplay();
            if ($login_param['type'] == 'form') {
                $form_drivers[] = $login_param['params'];
            }
        }
        return $form_drivers;
    }

    public function getLoginOAuth2Drivers(): array
    {
        $oauth2_drivers = array();
        foreach ($this->loadAllDrivers() as $driver) {
            /** @var DriverInstance $driver */
            $login_param = $driver->getClass()->getLoginDisplay();
            if ($login_param['type'] == 'oauth2') {
                $oauth2_drivers[] = $login_param['params'];
            }
        }
        return $oauth2_drivers;
    }
}