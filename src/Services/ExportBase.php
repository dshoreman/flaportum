<?php namespace Flaportum\Services;

use Flaportum\Core\Cache;

class ExportBase
{
    protected $baseUrl;

    protected $name;

    protected $code;

    protected $cache;

    protected $attributes;

    public function __construct()
    {
        $this->cache = new Cache($this);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getRequirements()
    {
        return $this->attributes;
    }

    public function init(array $data)
    {
        foreach (array_keys($this->getRequirements()) as $key) {
            if (!array_key_exists($key, $data) || empty($data[$key])) {
                throw new \Exception('Missing key "'.$key.'" required for this service.');
            }
        }
    }
}
