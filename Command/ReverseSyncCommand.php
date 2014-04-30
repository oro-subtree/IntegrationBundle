<?php

namespace Oro\Bundle\IntegrationBundle\Command;

use JMS\JobQueueBundle\Entity\Job;

use Doctrine\ORM\QueryBuilder;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use Oro\Bundle\CronBundle\Command\Logger\OutputLogger;

/**
 * Class ReverseSyncCommand
 * Console command implementation
 *
 * @package Oro\Bundle\IntegrationBundle\Command
 */
class ReverseSyncCommand extends ContainerAwareCommand implements CronCommandInterface
{
    const SYNC_PROCESSOR = 'oro_integration.sync.processor';
    const COMMAND_NAME = 'oro:integration:reverse:sync';
    const CHANNEL_ARG_NAME = 'channel';
    const CONNECTOR_ARG_NAME = 'connector';
    const PARAMETERS_ARG_NAME = 'params';

    /**
     * {@inheritdoc}
     */
    public function getDefaultDefinition()
    {
        return '*/5 * * * *';
    }

    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName(static::COMMAND_NAME)
            ->addOption(self::CHANNEL_ARG_NAME, null, InputOption::VALUE_REQUIRED, 'Channel id')
            ->addOption(self::CONNECTOR_ARG_NAME, null, InputOption::VALUE_REQUIRED, 'Connector type')
            ->addOption(self::PARAMETERS_ARG_NAME, null, InputOption::VALUE_REQUIRED, 'Parameters');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $channelId      = $input->getOption(self::CHANNEL_ARG_NAME);
        $connectorType  = $input->getOption(self::CONNECTOR_ARG_NAME);
        $params         = $input->getOption(self::PARAMETERS_ARG_NAME);
        $logger         = new OutputLogger($output);
        $processor      = $this->getService(self::SYNC_PROCESSOR);
        $repository     = $this->getService('doctrine.orm.entity_manager')
            ->getRepository('OroIntegrationBundle:Channel');

        if (empty($channelId)) {
            throw new \InvalidArgumentException('Channel id require.');
        }

        if (empty($connectorType)) {
            throw new \InvalidArgumentException('Connector type require.');
        }

        if (empty($params)) {
            throw new \InvalidArgumentException('Parameters require.');
        }

        $processor->getLoggerStrategy()->setLogger($logger);

        $this->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getConnection()
            ->getConfiguration()
            ->setSQLLogger(null);

        if ($this->isJobRunning($channelId, $connectorType, $params)) {
            $logger->warning('Job already running. Terminating....');
            return 0;
        }

        $channel = $repository->getOrLoadById($channelId);

        if (empty($channel)) {
            throw new \InvalidArgumentException('Channel with given ID not found');
        }

        try {
            $logger->notice(
                sprintf(
                    'Run sync for "%s" channel and "%s" connector.',
                    $channel->getName(),
                    $connectorType
                )
            );

            $processor->process($channel, $connectorType, $params);
        } catch (\Exception $e) {
            $logger->critical($e->getMessage(), ['exception' => $e]);
        }

        $logger->notice('Completed');

        return 0;
    }

    /**
     * Get service from DI container by id
     *
     * @param string $id
     *
     * @return object
     */
    protected function getService($id)
    {
        return $this->getContainer()->get($id);
    }

    /**
     * @param int $channelId
     * @param string $connectorTypeType
     * @param string $params
     *
     * @return bool
     */
    protected function isJobRunning($channelId, $connectorTypeType, $params)
    {
        /** @var QueryBuilder $qb */
        $qb = $this->getService('doctrine.orm.entity_manager')
            ->getRepository('JMSJobQueueBundle:Job')
            ->createQueryBuilder('j');

        $query = $qb->select('count(j.id)')
            ->andWhere('j.command=:commandName')
            ->andWhere('j.state=:stateName')
            ->setParameter('commandName', $this->getName())
            ->setParameter('stateName', Job::STATE_RUNNING)
            ->andWhere(
                $qb->expr()->eq('j.args', ':args')
            )
            ->setParameter(
                'args',
                '["--channel=' . $channelId . '",' .
                ' "--connector=' . $connectorTypeType . '",' .
                ' "--params=\'' . $params . '\'"]'
            );

        $running = $query->getQuery()->getSingleScalarResult();

        return $running > 0;
    }
}
