<?php namespace Flaportum\Services\OctoberPlugin;

use Flaportum\Post;
use Flaportum\Topic;
use Flaportum\User;
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

    protected $users = [];

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

        $cur = 1;
        $all = count($topics);

        foreach ($topics as $row) {
            $topic = $this->prepareTopic($row);

            $posts = (new Dom)->load($this->baseUrl.'/'.$topic->slug)->find('.forum-posts .forum-post');
            $postsArray = $posts->toArray();

            $topic->created_at = $this->getCreateTimestamp($posts[0]);
            $topic->last_post_at = $this->getCreateTimestamp(end($postsArray));

            echo sprintf(
                "[%s] Caching topic %s of %s: %s".PHP_EOL,
                $this->cache->putTopic($topic) ? 'PASS' : 'FAIL',
                $cur, $all,
                $topic->title
            );

            $this->loadPosts($posts, $topic);

            $this->topicCount++;
            $cur++;
        }
    }

    protected function prepareTopic($data)
    {
        $name = $data->find('h5 a')[0];
        $link = $name->getAttribute('href');

        $topic = new Topic;
        $topic->title = html_entity_decode($name->innerHtml(), ENT_QUOTES);
        $topic->slug = substr($link, strrpos($link, '/') + 1);
        $topic->author = $this->getUser($data->find('h5 small a')[0])->id;

        return $topic;
    }

    protected function loadPosts($posts, $topic)
    {
        $cur = 1;
        $all = count($posts);

        foreach ($posts as $result) {
            $post = new Post;
            $post->id = $result->getAttribute('data-post-id');
            $post->author = $this->getUser($result->find('.content > a.author')[0])->id;
            $post->created_at = $this->getCreateTimestamp($result);
            $post->updated_at = $this->getEditTimestamp($result);
            $post->content = $result->find('.content > .text')[0]->innerHtml();

            echo sprintf(
                "[%s] Caching post %s of %s".PHP_EOL,
                $this->cache->putPost($post, $topic) ? 'PASS' : 'FAIL',
                $cur, $all
            );

            $this->postCount++;
            $cur++;
        }
    }

    protected function getCreateTimestamp($post)
    {
        return $post->find('.content .metadata time')[0]->getAttribute('datetime');
    }

    protected function getEditTimestamp(&$post)
    {
        $mutedLines = $post->find('.content > .text small.text-muted');

        foreach ($mutedLines as $i => $line) {
            if (trim($line->text) != 'Last updated') {
                continue;
            }

            $time = $line->find('time')[0]->getAttribute('datetime');

            $mutedLines[$i]->delete();

            return $time;
        }
    }

    protected function getUser($link)
    {
        $href = $link->getAttribute('href');
        $slug = substr($href, strrpos($href, '/') + 1);

        if (array_key_exists($slug, $this->users)) {
            return $this->users[$slug];
        }

        $info = (new Dom)->load($href)->find('.forum-member-info')[0];

        $user = new User;
        $user->id = $slug;
        $user->username = $link->innerHtml();
        $user->created_at = trim(str_replace('Member since: ', '', $info->find('p')[0]->text));

        echo sprintf(
            "[%s] Caching new user: %s".PHP_EOL,
            $this->cache->putUser($user) ? 'PASS' : 'FAIL',
            $user->username
        );

        return $this->users[$slug] = $user;
    }
}
