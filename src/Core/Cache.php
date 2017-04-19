<?php namespace Flaportum\Core;

use Symfony\Component\Finder\Finder;

class Cache
{
    /**
     * Full path to the /cache directory
     */
    protected $cacheRoot;

    /**
     * Path to the actual dump directory
     */
    protected $cachePath;

    /**
     * Instance of the source service
     */
    protected $source;

    protected $users;

    public function __construct($source)
    {
        $this->cacheRoot = __DIR__.'/../../cache';

        $this->source = $source;
    }

    public function list()
    {
        $caches = [];

        $path = $this->getPath('root', null, true);

        foreach ((new Finder)->directories()->depth(0)->in($path) as $cache) {
            $caches[] = $cache->getFilename();
        }

        return  $caches;
    }

    public function open($dir)
    {
        $path = $this->getPath('root', $dir, true);

        if (!is_dir($path)) {
            throw new \Exception("Cache not found!");
        }

        $this->cachePath = $path;

        return $this;
    }

    public function create($dir)
    {
        $path = $cleanpath = $this->getPath('root', $dir, true);

        while (is_dir($path)) {
            $counter = !isset($counter) ? 2 : $counter + 1;

            $path = $cleanpath.'__'.$counter;
        }

        if (!mkdir($path, 0755, true)) {
            throw new \Exception("Failed to create cache directory {$path}");
        }

        $this->cachePath = $path;

        $topicPath = $this->getPath('topics', null);
        $postPath = $this->getPath('posts', null);
        $userPath = $this->getPath('users', null);

        if (!mkdir($topicPath, 0755)) {
            throw new \Exception("Failed to create Topic cache directory {$topicPath}");
        }

        if (!mkdir($postPath, 0755)) {
            throw new \Exception("Failed to create Post cache directory {$postPath}");
        }

        if (!mkdir($userPath, 0755)) {
            throw new \Exception("Failed to create User cache directory {$userPath}");
        }
    }

    public function getPath($cache = 'root', $item = null, $root = false)
    {
        $path = $root ? $this->cacheRoot : $this->cachePath;

        switch ($cache) {
            case 'topics': $path .= '/topics'; break;
            case 'posts': $path .= '/posts'; break;
            case 'users': $path .= '/users'; break;
            case 'root':
                if ($this->source !== null) {
                    $path .= '/'.$this->source->getCode();
                }
                break;
        }

        if ($item) {
            $path .= '/'.$item;
        }

        return $path;
    }

    public function getTopics()
    {
        $topics = [];

        foreach ((new Finder)->files()->in($this->getPath('topics')) as $file) {
            $topic = unserialize($file->getContents());
            $posts = (new Finder)->files()->in($this->getPath('posts', $topic->created_at));
            $topic->posts = [];

            foreach ($posts as $postfile) {
                $topic->posts[] = $postfile->getBasename('.txt');
            }

            sort($topic->posts, SORT_NUMERIC);

            $topics[] = $topic;
        }

        usort($topics, function($a, $b) {
            if ($a->created_at == $b->created_at) {
                return 0;
            }

            return $a->created_at > $b->created_at ? 1: -1;
        });

        return $topics;
    }

    public function putTopic($topic)
    {
        $path = $this->getPath('posts', $this->getTopicCachename($topic, false));

        if (!is_dir($path) && !mkdir($path)) {
            throw new \Exception("Failed to create topic cache at {$path}");
        }

        $file = $this->getPath('topics', $this->getTopicCachename($topic).'.txt');

        return false !== file_put_contents($file, serialize($topic));
    }

    protected function getTopicCachename($topic, $includeSlug = true)
    {
        return !$includeSlug
            ? $topic->created_at
            : $topic->created_at.'__'.$topic->slug;
    }

    public function getPost($topic, $postId)
    {
        $file = $this->getPath('posts', $topic->created_at)."/{$postId}.txt";

        if (!file_exists($file)) {
            throw new \Exception("Post not found in cache!");
        }

        return unserialize(file_get_contents($file));
    }

    public function putPost($post, $topic)
    {
        $path = $this->getPath('posts', $this->getTopicCachename($topic, false));

        if (!is_dir($path) && !mkdir($path)) {
            throw new \Exception("Post cache does not exist and could not be created.");
        }

        $file = $path.'/'.$post->id.'.txt';

        return false !== file_put_contents($file, serialize($post));
    }

    public function getUsers()
    {
        if (!$this->users) {
            foreach ((new Finder)->files()->in($this->getPath('users')) as $file) {
                $this->users[] = unserialize($file->getContents());
            }
        }

        return $this->users;
    }

    public function putUser($user)
    {
        $file = $this->getPath('users', $user->id.'.txt');

        return false !== file_put_contents($file, serialize($user));
    }
}
