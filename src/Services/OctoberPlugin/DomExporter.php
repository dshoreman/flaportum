<?php namespace Flaportum\Services\OctoberPlugin;

use Flaportum\Post;
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

            $topic->created_at = $this->getCreateTimestamp($posts[0]);
            $topic->last_post_at = $this->getCreateTimestamp(end($postsArray));

            $this->cache->putTopic($topic);

            $this->loadPosts($posts, $topic);

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

    protected function loadPosts($posts, $topic)
    {
        foreach ($posts as $result) {
            $post = new Post;
            $post->id = $result->getAttribute('data-post-id');
            $post->author = $result->find('.content > a.author')[0]->innerHtml();
            $post->content = $result->find('.content > .text')[0]->innerHtml();
            $post->created_at = $this->getCreateTimestamp($result);
            $post->updated_at = $this->getEditTimestamp($result);

            $this->cache->putPost($post, $topic);

            $this->postCount++;
        }
    }

    protected function getCreateTimestamp($post)
    {
        return $post->find('.content .metadata time')[0]->getAttribute('datetime');
    }

    protected function getEditTimestamp($post)
    {
        $mutedLines = $post->find('.content > .text small.text-muted');

        foreach ($mutedLines as $line) {
            if (trim($line->text) != 'Last updated') {
                continue;
            }

            return $line->find('time')[0]->getAttribute('datetime');
        }
    }
}
