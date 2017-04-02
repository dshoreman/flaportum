<?php namespace Flaportum\Services;

class ServiceManager
{
    public $services = [];

    public function __construct()
    {
        $this->loadServices();
    }

    protected function loadServices()
    {
        $this->services['export'] = [];
        $this->services['import'] = [];

        $this->loadExportServices();
    }

    protected function loadExportServices()
    {
        foreach (new \DirectoryIterator(__DIR__) as $serviceDir) {
            if ($serviceDir->isDot() || !$serviceDir->isDir()) {
                continue;
            }

            $ns = $serviceDir->getFilename();

            foreach (new \DirectoryIterator($serviceDir->getPathname()) as $serviceFile) {
                if (!$serviceFile->isFile()) {
                    continue;
                }

                if (substr_compare($serviceFile->getFilename(), '.php', -4, 4) !== 0) {
                    continue;
                }

                $class = sprintf('Flaportum\Services\%s\%s', $ns, $serviceFile->getBasename('.php'));

                if (!class_exists($class)) {
                    continue;
                }

                $service = new $class;

                if (!$service instanceof ExportInterface) {
                    continue;
                }

                $this->services['export'][$class] = $service->getServiceName();
            }
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'exportServices': return $this->services['export'];
            case 'importServices': return $this->services['import'];
        }

        throw new Exception("Property {$name} is invalid or inaccessible.");
    }
}
