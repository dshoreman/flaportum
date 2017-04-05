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

        $topicPath = $this->getPath('topics', null, $path);
        $postPath = $this->getPath('posts', null, $path);
        $userPath = $this->getPath('users', null, $path);

        if (!mkdir($topicPath, 0755)) {
            throw new \Exception("Failed to create Topic cache directory {$topicPath}");
        }

        if (!mkdir($postPath, 0755)) {
            throw new \Exception("Failed to create Post cache directory {$postPath}");
        }

        if (!mkdir($userPath, 0755)) {
            throw new \Exception("Failed to create User cache directory {$userPath}");
        }

        $this->exportRoot = $path;
    }

    protected function getPath($cache = 'root', $item = null, $root = null)
    {
        $path = $root ?: $this->exportRoot;

        switch ($cache) {
            case 'topics': $path .= '/topics'; break;
            case 'posts': $path .= '/posts'; break;
            case 'users': $path .= '/users'; break;
        }

        if ($item) {
            $path .= '/'.$item;
        }

        return $path;
    }

    public function putTopic($topic)
    {
        $path = $this->getPath('posts', $this->getTopicCachename($topic, false));

        if (!is_dir($path) && !mkdir($path)) {
            throw new \Exception("Failed to create topic cache at {$path}");
        }

        $file = $this->getPath('topics', $this->getTopicCachename($topic).'.txt');

        if (false === file_put_contents($file, serialize($topic))) {
            throw new \Exception("Failed to write topic data file at {$file}");
        }
    }

    protected function getTopicCachename($topic, $includeSlug = true)
    {
        return !$includeSlug
            ? $topic->created_at
            : $topic->created_at.'__'.$topic->slug;
    }

    public function putPost($post, $topic)
    {
        $path = $this->getPath('posts', $this->getTopicCachename($topic, false));

        if (!is_dir($path) && !mkdir($path)) {
            throw new \Exception("Post cache does not exist and could not be created.");
        }

        $file = $path.'/'.$post->id.'.txt';

        if (false === file_put_contents($file, serialize($post))) {
            throw new \Exception("Failed to write post data file at {$file}");
        }
    }

    public function putUser($user)
    {
        $file = $this->getPath('users', $user->id.'.txt');

        if (false === file_put_contents($file, serialize($user))) {
            throw new \Exception("Failed to write user data file at {$file}");
        }
    }
}
