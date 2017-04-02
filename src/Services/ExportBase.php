<?php namespace Flaportum\Services;

class ExportBase
{
    protected $baseUrl;

    protected $name;

    protected $attributes;

    public function getName()
    {
        return $this->name;
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
