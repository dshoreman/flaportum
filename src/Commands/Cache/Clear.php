<?php namespace Flaportum\Commands\Cache;

use Flaportum\Core\Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Clear extends Command
{
    protected function configure()
    {
        $this->setName('cache:clear');
        $this->setDescription('Clear the export cache');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fs = new Filesystem();
        $cachePath = (new Cache(null))->getPath('root', null, true);

        $caches = (new Finder)->directories()->depth(0)->in($cachePath);

        foreach ($caches as $cache) {
            $output->writeLn('Removing cache '.$cache->getFilename());

            $fs->remove($cache);
        }

        $question = new ConfirmationQuestion("Clear generated reports? [y/N] ", false);

        if (!$this->getHelper('question')->ask($input, $output, $question)) {
            $output->writeLn("Cache clear complete!");

            return;
        }

        foreach ((new Finder)->files()->depth(0)->in($cachePath) as $report) {
            $output->writeLn('Removing report '.$report->getFilename());

            $fs->remove($report->getPathname());
        }

        $output->writeLn(['', 'Cache clear complete!']);
    }
}
