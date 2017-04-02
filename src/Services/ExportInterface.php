<?php namespace Flaportum\Services;

interface ExportInterface
{
    public function getServiceName();

    public function init(array $data);

    public function run();
}
