<?php namespace Flaportum\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

        $args = new ArrayInput(['--suppress-heading' => true]);

        $app->find('export')->run($args, $output);
        $app->find('import')->run($args, $output);
    }
}
