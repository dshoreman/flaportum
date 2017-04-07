<?php namespace Flaportum\Commands;

use Flagrow\Flarum\Api\Flarum;
use Flaportum\Services\ServiceManager;
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

    protected $helper;

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

        $this->helper = $this->getHelper('question');

        $this->api = new Flarum('http://localhost', [
            'token' => 'Token wicwqnvRqmGNp4LBAMHWp3nKRbh19dsCbfiCgp7N; userId=1'
        ]);

        $forum = $this->api->forum()->request();

        $output->writeLn("We found a forum called '{$forum->title}' at that link!");

        if (!$this->helper->ask($input, $output, new ConfirmationQuestion("Is that right? [y/N] ", false))) {
            $output->writeLn("Aborting.");
            return;
        }

        $tag = $this->chooseTag($input, $output, $forum->tags);
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
}
