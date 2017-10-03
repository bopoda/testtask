<?php

namespace AppBundle\Command\Users;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('users:import')
            ->setDescription('Creates new users from CSV file.')
            ->setHelp("This command allows you to import users from csv file.")
            ->addOption(
                'csvFileName',
                'f',
                InputOption::VALUE_REQUIRED,
                'Full path to CSV'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $csvFile = $input->getOption('csvFileName');

        $output->writeln(['Start importing...']);

        if (!file_exists($csvFile)) {
            throw new \Exception('file not found: ' . $csvFile);
        }
    }
}