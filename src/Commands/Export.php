<?php namespace Flaportum\Commands;

use Flaportum\Services\ServiceManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

class Export extends Command
{
    protected function configure()
    {
        $this->setName('export');
        $this->setDescription('Exports data from a supported forum');
    }

    protected function execute(InputInterface $input, OutputInterface $out)
    {
        $out->writeLn([
            '╔══════════════════════════╗',
            '║ Flaportum Forum Exporter ║',
            '╚══════════════════════════╝',
            '',
        ]);

        $helper = $this->getHelper('question');

        $service = $helper->ask($input, $out, new ChoiceQuestion(
            "Select the type of forum to export from:",
            (new ServiceManager)->exportServices,
            0
        ));

        $out->writeLn(["Selected service: ".$service, '']);

        $service = new $service;
        $serviceData = [];

        if ($requirements = $service->getRequirements()) {
            $out->writeLn([
                "Your chosen service requires some information before proceeding.",
                "Please fill in the following...",
            ]);

            foreach ($service->getRequirements() as $key => $description) {
                $serviceData[$key] = $helper->ask($input, $out, new Question($description));
            }
        }

        $service->init($serviceData);

        $service->run();

        $out->writeLn(sprintf(
            "Found %s topics across %s pages, containing a total of %s posts.",
            $service->topicCount,
            $service->pageCount,
            $service->postCount
        ));
    }
}
