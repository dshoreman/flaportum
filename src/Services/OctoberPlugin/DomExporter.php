<?php namespace Flaportum\Services\OctoberPlugin;

use Flaportum\Topic;
use Flaportum\Services\ExportBase;
use Flaportum\Services\ExportInterface;
use PHPHtmlParser\Dom;

class DomExporter extends ExportBase implements ExportInterface
{
    protected $name = 'OctoberCMS Plugin Forums (web scrape)';

    protected $code = 'october-plugin';

    protected $attributes = [
        'pluginCode' => 'Plugin code (e.g. "rainlab-blog"): ',
    ];

    protected $pages = [];
    public $pageCount = 0;

    protected $topics = [];
    public $topicCount = 0;

    public $postCount = 0;

    public function init(array $data)
    {
        parent::init($data);

        $this->cache->create($data['pluginCode']);

        $this->baseUrl = 'https://octobercms.com/plugin/support/'.$data['pluginCode'];
    }

    public function run()
    {
        $this->loadPages();

        foreach ($this->pages as $page) {
            $this->loadTopics($page);
        }
    }

    protected function loadPages()
    {
        $pagination = (new Dom)->load($this->baseUrl)->find('ul.pagination a[href*=page]');

        foreach ($pagination as $link) {
            if (is_numeric($link->innerHtml())) {
                $this->pages[] = $link->getAttribute('href');
            }
        }

        $this->pageCount = count($this->pages);
    }

    protected function loadTopics($page)
    {
        $topics = (new Dom)->load($page)->find('tr.forum-topic');

        foreach ($topics as $row) {
            $topic = $this->prepareTopic($row);

            $posts = (new Dom)->load($this->baseUrl.'/'.$topic->slug)->find('.forum-posts .forum-post');
            $postsArray = $posts->toArray();

            $topic->created_at = $this->getTimestamp($posts[0]);
            $topic->last_post_at = $this->getTimestamp(end($postsArray));

            $this->cache->putTopic($topic);

            $this->loadPosts($topic->slug, $posts);

            $this->topicCount++;
        }
    }

    protected function prepareTopic($data)
    {
        $name = $data->find('h5 a')[0];
        $link = $name->getAttribute('href');

        $topic = new Topic;
        $topic->title = $name->innerHtml();
        $topic->slug = substr($link, strrpos($link, '/') + 1);
        $topic->author = $data->find('h5 small a')[0]->innerHtml();

        return $topic;
    }

    protected function getTimestamp($post)
    {
        return $post->find('.content .metadata time')[0]->getAttribute('datetime');
    }

    protected function loadPosts($slug, $posts)
    {
        foreach ($posts as $post) {
            $this->topics[$slug]['posts'][] = [
                'author' => $post->find('.content > a.author')[0]->innerHtml(),
                'content' => $post->find('.content > .text')[0]->innerHtml(),
            ];
            $this->postCount++;
        }
    }
}
