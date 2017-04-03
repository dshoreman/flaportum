<?php namespace Flaportum\Core;

class Cache
{
    protected $cacheRoot;

    protected $exportRoot;

    protected $source;

    public function __construct($source)
    {
        $this->cacheRoot = __DIR__.'/../../cache';

        $this->source = $source;
    }

    public function create($dir)
    {
        $path = $cleanpath = $this->cacheRoot.'/'.$this->source->getCode().'/'.$dir;

        while (is_dir($path)) {
            $counter = !isset($counter) ? 2 : $counter + 1;

            $path = $cleanpath.'__'.$counter;
        }

        if (!mkdir($path, 0755, true)) {
            throw new \Exception("Failed to create cache directory {$path}");
        }

        $this->exportRoot = $path;
    }

    public function putTopic($topic)
    {
        $path = $this->exportRoot.'/'.$this->getTopicCachename($topic, false);

        if (!mkdir($path)) {
            throw new \Exception("Failed to create topic cache at {$path}");
        }

        $file = $this->exportRoot.'/'.$this->getTopicCachename($topic).'.txt';

        if (false === file_put_contents($file, serialize($topic))) {
            throw new \Exception("Failed to write topic data file at {$file}");
        }
    }

    protected function getTopicCachename($topic, $includeSlug = true)
    {
        return $includeSlug
            ? $topic->created_at
            : $topic->created_at.'__'.$topic->slug;
    }
}
