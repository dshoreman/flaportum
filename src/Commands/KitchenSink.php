<?php namespace Flaportum\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class KitchenSink extends Command
{
    protected function configure()
    {
        $this->setName('run');
        $this->setDescription('Export from one forum and import to another in a single command');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn([
            '╔══════════════════════════╗',
            '║ Flaportum Forum Migrator ║',
            '╚══════════════════════════╝',
            '',
        ]);

        $app = $this->getApplication();

        $clearCache = $this->getHelper('question')->ask($input, $output, new ConfirmationQuestion(
            "Would you like to clear the cache before exporting? [y/N] ",
            false
        ));

        if ($clearCache) {
            $app->find('cache:clear')->run(new ArrayInput([]), $output);
        }

        $args = new ArrayInput(['--suppress-heading' => true]);

        $app->find('export')->run($args, $output);
        $app->find('import')->run($args, $output);
    }
}
