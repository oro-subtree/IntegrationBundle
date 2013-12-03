<?php

namespace Oro\Bundle\IntegrationBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Class SyncCommand
 * Console command implementation
 *
 * @package Oro\Bundle\IntegrationBundle\Command
 */
class SyncCommand extends ContainerAwareCommand
{
    const SYNC_PROCESSOR = 'oro_integration.sync.processor';

    /**
     * Console command configuration
     */
    public function configure()
    {
        $this
            ->setName('oro:integration:sync')
            ->setDescription('Sync entities (currently only importing magento customers)')
            ->addArgument('channelId', InputArgument::REQUIRED, 'Channel identification name')
            ->addOption('run', null, InputOption::VALUE_NONE, 'Do actual import, readonly otherwise');
    }

    /**
     * Runs command
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     *
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln($this->getDescription());

        $channelId = $input->getArgument('channelId');
        $force     = $input->getOption('run');

        $closure = function ($context) use ($output) {
            $context = $context[0]; // first arg
            $isSuccess  = $context['success'] === true;
            if ($isSuccess) {

            } else {
                $output->writeln('There was some errors:');
                foreach ($context['errors'] as $error) {
                    $output->writeln($error);
                }
            }
            $output->writeln(
                sprintf(
                    "Stats: read [%d], process [%d], updated [%d], added [%d], delete [%d]",
                    $context['counts']['read'],
                    $context['counts']['process'],
                    $context['counts']['update'],
                    $context['counts']['add'],
                    $context['counts']['delete']
                )
            );
        };

        $this->getContainer()
            ->get(self::SYNC_PROCESSOR)
            ->setLogClosure($closure)
            ->process($channelId, $force);

        $output->writeln('Completed');
    }
}
