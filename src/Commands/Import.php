<?php namespace Flaportum\Commands;

use Flaportum\Core\Cache;
use Flaportum\Markdown\Markdown;
use Flaportum\Services\Flarum\Api;
use Flaportum\Services\ServiceManager;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Import extends Command
{
    protected $api;

    protected $apiHost;

    protected $cache;

    protected $config;

    protected $helper;

    protected $users;

    protected $userMap;

    protected function configure()
    {
        $this->setName('import');
        $this->setDescription('Imports data from a supported forum');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn([
            '╔══════════════════════════╗',
            '║ Flaportum Forum Importer ║',
            '╚══════════════════════════╝',
            '',
        ]);

        $this->config = (object) (
            file_exists($config = __DIR__.'/../../config/flarum.php')
            ? require_once $config
            : ['api_token' => '']
        );

        $this->md = new Markdown();

        $this->helper = $this->getHelper('question');

        $this->cache = $this->chooseCache($input, $output);

        $forum = $this->chooseHost($input, $output);

        $tag = $this->chooseTag($input, $output, $forum->tags);

        $this->importUsers($input, $output);

        $this->importDiscussions($input, $output);
    }

    protected function chooseCache($input, $output)
    {
        $service = $this->helper->ask($input, $output, new ChoiceQuestion(
            "Select the service you used for the export: ",
            (new ServiceManager)->exportServices,
            0
        ));

        $cache = new Cache(new $service);

        $cacheDir = $this->helper->ask($input, $output, new ChoiceQuestion(
            "Now select the forum dump to import: ",
            $cache->list()
        ));

        return $cache->open($cacheDir);
    }

    protected function chooseHost($input, $output)
    {
        while (!isset($forum)) {
            $this->apiHost = $this->helper->ask($input, $output, new Question(
                "Enter the URL to import into, or leave blank for localhost: ",
                'http://localhost'
            ));

            $forum = $this->api()->forum()->request();

            $output->writeLn("We found a forum called '{$forum->title}' at that link!");

            if ($this->helper->ask($input, $output, new ConfirmationQuestion("Is that right? [y/N] ", false))) {
                return $forum;
            }

            unset($forum);
        }
    }

    protected function api($userId = 1)
    {
        if (!$this->api) {
            $this->api = new Api($this->apiHost, $this->config->token);
        }

        return $this->api->instance($userId);
    }

    protected function chooseTag($input, $output, $tagdata)
    {
        $tags = [
            $createStr = 'None (let me create one)'
        ] + Arr::pluck($tagdata, 'attributes.slug', 'id');

        $tag = $this->helper->ask($input, $output, new ChoiceQuestion(
            "Select the tag you'd like to save discussions into: ",
            $tags,
            0
        ));

        // This could return false (not found) or 0 ("None" option)
        // It doesn't matter to us. Anything truthy is a valid tag.
        if ($tag = array_search($tag, $tags)) {
            $action = 'Selected';

            $tag = $tagdata[$tag];
        } else {
            $action = 'Created';

            $tag = $this->createTag($input, $output);
        }

        $output->writeLn(["{$action} tag: {$tag->name}", PHP_EOL]);

        return $tag;
    }

    protected function createTag($input, $output)
    {
        $name = $this->helper->ask($input, $output, new Question(
            "Enter a name for the new tag. Leave it blank to set one automatically: ",
            sprintf("Imported posts (%s)", date('d/m/Y H:i:s'))
        ));

        $desc = $this->helper->ask($input, $output, new Question(
            "Great! Now enter an optional description: ",
            ''
        ));

        return $this->api()->tags()->post([
            'data' => [
                'attributes' => [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => $desc,
                ],
            ],
        ])->request();
    }

    protected function importDiscussions($input, $output)
    {
        foreach ($this->cache->getTopics() as $topic) {
            for ($i = 0; $i < count($topic->posts); $i++) {
                $post = $this->cache->getPost($topic, $topic->posts[$i]);

                if ($i === 0) {
                    $topic->content = $post->content;

                    $discussion = $this->createDiscussion($topic);
                } else {
                    $this->createPost($discussion, $post);
                }
            }
        }
    }

    protected function createDiscussion($topic)
    {
        $actor = $this->userMap[$topic->author];

        return $this->api($actor)->discussions()->post([
            'data' => [
                'attributes' => [
                    'title' => $topic->title,
                    'content' => $this->md->convert($topic->content),
                    'time' => $topic->created_at,
                ],
            ],
        ])->request();
    }

    protected function createPost($discussion, $post)
    {
        $actor = $this->userMap[$post->author];

        return $this->api($actor)->posts()->post([
            'data' => [
                'attributes' => [
                    'content' => $this->md->convert($post->content),
                    'time' => $post->created_at,
                ],
                'relationships' => [
                    'discussion' => [
                        'data' => [
                            'id' => $discussion->id,
                        ],
                    ],
                ],
            ],
        ])->request();
    }

    protected function importUsers($input, $output)
    {
        $this->loadExistingUsers($input, $output);

        foreach ($this->cache->getUsers() as $profile) {
            $user = $this->createUser($input, $output, $profile);

            $this->userMap[$profile->id] = $user ? $user->id : null;
        }
    }

    protected function loadExistingUsers($input, $output)
    {
        $this->users = $this->api()->users()->request();

        while ($links = $this->api()->links) {
            if (!array_key_exists('next', $links)) {
                $output->writeLn("All users fetched!");
                break;
            }

            $params = explode('&', urldecode(parse_url($links['next'], PHP_URL_QUERY)));

            foreach ($params as $param) {
                list($k, $v) = explode('=', $param);

                if ($k != 'page[offset]') {
                    continue;
                }

                $offset = (int) $v;

                break;
            }

            $this->users->merge($batch = $this->api()->users()->offset($offset)->request());
        }
    }

    protected function createUser($input, $output, $user, $increment = 0)
    {
        $username = $increment ? sprintf("%s-%s", $user->username, $increment) : $user->username;
        $email = sprintf($this->config->email, str_replace(' ', '', $username));
        $collection = $this->users->collect();

        try {
            $previous = $username == $user->username ? null : " (originally {$user->username})";
            $output->writeLn(['', "Registering user {$username}{$previous} for account {$user->id}..."]);

            $user = $this->api()->users()->post([
                'data' => [
                    'attributes' => [
                        'username' => $username,
                        'email' => $email,
                        'password' => Str::random(20),
                        'isActivated' => true,
                    ],
                ],
            ])->request();

            $output->writeLn("[INFO] Successfully created user {$user->username}");
        } catch (ClientException $e) {
            // Reset the API first, or we get endpoint recursion (/api/users/users)
            $this->api()->fluent();

            $errors = $this->handleUserCreateException($input, $output, $e);

            if ($errors->unhandled) {
                $output->writeLn("[ERROR] Failed to register username {$username}. Skipping.");

                return;
            }

            if ($errors->user_taken && $errors->email_taken) {
                $output->writeLn("[INFO] User '{$username}' is already registered");

                $user = $this->getCachedUser($username, $collection);
            } elseif ($errors->user_taken || $errors->email_taken) {
                $field = $errors->user_taken ? 'username' : 'email';
                $other = $errors->user_taken ? 'email' : 'username';

                $output->writeLn("[ERROR] The {$field} '{$$field}' is already registered with a different {$other}.");

                $answer = $this->helper->ask($input, $output, new ChoiceQuestion(
                    "How would you like to proceed?",
                    ['c' => "Continue using the existing user", 'n' => 'Make a new, suffixed user'],
                    'n'
                ));

                if ($answer == 'n') {
                    $user = $this->createUser($input, $output, $user, $increment + 1);
                } else {
                    $user = $this->getCachedUser($username, $collection);
                }
            } elseif ($errors->user_invalid) {
                $output->writeLn("[INFO] Username {$username} is invalid, attempting to fix...");

                $user->username = preg_replace('/[^\pL\pM\pN_-]+/u', '-', $username);

                $user = $this->createUser($input, $output, $user, $increment);
            }
        }

        // If we've created a new user, make sure it's in our cache
        if ($user && !$collection->has($user->id)) {
            $this->users->collect()->put($user->id, $user);
        }

        return $user ?? null;
    }

    protected function getCachedUser($username, \Illuminate\Support\Collection $collection)
    {
        $id = $collection->search(function ($user, $id) use ($username) {
            return $user->username == $username;
        });

        return $id ? $collection->get($id) : null;
    }

    protected function handleUserCreateException($input, $output, $exception)
    {
        $response = $exception->getResponse();
        $body = json_decode($response->getBody());
        $errors = (object) [
            'email_taken' => false,
            'user_taken' => false,
            'unhandled' => false,
        ];

        if ($response->getStatusCode() != 422) {
            throw $e;
        }

        foreach ($body->errors as $error) {
            switch ($error->detail) {
                case 'The username has already been taken.':
                    $errors->user_taken = true; break;
                case 'The email has already been taken.':
                    $errors->email_taken = true; break;
                case 'The username may only contain letters, numbers, and dashes.':
                    $errors->user_invalid = true; break;
                default:
                    $output->writeLn("[DEBUG] {$error->detail}");
                    $errors->unhandled = true;
            }
        }

        return $errors;
    }
}
