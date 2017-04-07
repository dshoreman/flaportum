<?php namespace Flaportum\Commands;

use Flagrow\Flarum\Api\Flarum;
use Flaportum\Core\Cache;
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

        $this->helper = $this->getHelper('question');

        $this->cache = $this->chooseCache($input, $output);

        $forum = $this->chooseHost($input, $output);

        $tag = $this->chooseTag($input, $output, $forum->tags);

        $this->processUsers($input, $output);
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
            $host = $this->helper->ask($input, $output, new Question(
                "Enter the URL to import into, or leave blank for localhost: ",
                'http://localhost'
            ));

            $this->api = new Flarum($host, [
                'token' => "Token {$this->config->token}; userId=1"
            ]);

            $forum = $this->api->forum()->request();

            $output->writeLn("We found a forum called '{$forum->title}' at that link!");

            if ($this->helper->ask($input, $output, new ConfirmationQuestion("Is that right? [y/N] ", false))) {
                return $forum;
            }

            unset($forum);
        }
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

        return $this->api->tags()->post([
            'data' => [
                'attributes' => [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => $desc,
                ],
            ],
        ])->request();
    }

    protected function processUsers($input, $output)
    {
        $this->users = $this->api->users()->request();

        foreach ($this->cache->getUsers() as $profile) {
            $user = $this->createUser($input, $output, $profile);

            if (!$this->users->collect()->has($user->id)) {
                $this->users->collect()->put($user->id, $user);
            }

            $this->userMap[$profile->id] = $user->id;
        }
    }

    protected function createUser($input, $output, $user, $increment = 0)
    {
        $username = $increment ? sprintf("%s-%s", $user->username, $increment) : $user->username;

        try {
            return $this->api->users()->post([
                'data' => [
                    'attributes' => [
                        'username' => $username,
                        'email' => sprintf($this->config->email, $username),
                        'password' => Str::random(20),
                        'isActivated' => true,
                    ],
                ],
            ])->request();
        } catch (ClientException $e) {
            $res = $e->getResponse();
            $body = json_decode($res->getBody());

            if ($res->getStatusCode() != 422) {
                throw $e;
            }

            foreach ($body->errors as $error) {
                if ($error->detail != 'The email has already been taken.'
                 && $error->detail != 'The username has already been taken.') {
                    continue;
                }

                $answer = $this->helper->ask($input, $output, new ChoiceQuestion(
                    sprintf("%s What should we do? ", $error->detail),
                    ['c' => "Continue using the existing user ({$username})", 'n' => 'Make a new, suffixed user'],
                    'n'
                ));

                if ($answer == 'n') {
                    // Reset the API first, or we get endpoint recursion (/api/users/users)
                    $this->api->fluent();

                    return $this->createUser($input, $output, $user, $increment + 1);
                } else {
                    $userId = $this->users->collect()->search(function ($user, $id) use ($username) {
                        return $user->username == $username;
                    });

                    return $this->users->collect()->get($userId);
                }

                break;
            }
        }
    }
}
