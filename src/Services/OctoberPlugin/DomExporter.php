<?php namespace Flaportum\Services\OctoberPlugin;

use Flaportum\Services\ExportBase;
use Flaportum\Services\ExportInterface;
use PHPHtmlParser\Dom;

class DomExporter extends ExportBase implements ExportInterface
{
    protected $name = 'OctoberCMS Plugin Forums (web scrape)';

    protected $attributes = [
        'code' => 'Plugin code (e.g. "rainlab-blog"): ',
    ];

    protected $pages = [];
    public $pageCount = 0;

    protected $topics = [];
    public $topicCount = 0;

    public $postCount = 0;

    public function init(array $data)
    {
        parent::init($data);


        $this->baseUrl = 'https://octobercms.com/plugin/support/'.$data['code'];
    }

    public function run()
    {
        $this->loadPages();

        foreach ($this->pages as $page) {
            $this->loadTopics($page);
        }

        foreach ($this->topics as $slug => $data) {
            $this->loadPosts($slug);
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

        foreach ($topics as $topic) {
            $title = $topic->find('h5 a')[0];
            $link = $title->getAttribute('href');
            $linkParts = explode('/', $link);
            $slug = end($linkParts);

            $this->topics[$slug] = [
                'link' => $link,
                'title' => $title->innerHtml(),
                'author' => $topic->find('h5 small a')[0]->innerHtml(),
                'posts' => [],
            ];
            $this->topicCount++;
        }
    }

    protected function loadPosts($slug)
    {
        $posts = (new Dom)->load($this->baseUrl.'/'.$slug)->find('.forum-posts .forum-post');

        foreach ($posts as $post) {
            $this->topics[$slug]['posts'][] = [
                'author' => $post->find('.content > a.author')[0]->innerHtml(),
                'content' => $post->find('.content > .text')[0]->innerHtml(),
            ];
            $this->postCount++;
        }
    }
}
