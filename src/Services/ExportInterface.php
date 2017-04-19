<?php namespace Flaportum\Services;

interface ExportInterface
{
    public function getName();

    public function getCode();

    public function init(array $data);

    public function run();
}
