<?php
namespace Oro\Bundle\IntegrationBundle\Async;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\IntegrationBundle\Exception\LogicException;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Bundle\IntegrationBundle\Provider\ReverseSyncProcessor;
use Oro\Bundle\IntegrationBundle\Provider\TwoWaySyncConnectorInterface;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\JobProcessor;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\MessageQueue\Util\JSON;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class ReversSyncIntegrationProcessor implements
    MessageProcessorInterface,
    ContainerAwareInterface,
    TopicSubscriberInterface
{
    use ContainerAwareTrait;

    /**
     * @var DoctrineHelper
     */
    private $doctrineHelper;
    
    /**
     * @var ReverseSyncProcessor
     */
    private $reverseSyncProcessor;

    /**
     * @var TypesRegistry
     */
    private $typesRegistry;

    /**
     * @var JobProcessor
     */
    private $jobProcessor;

    /**
     * @param DoctrineHelper $doctrineHelper
     * @param ReverseSyncProcessor $reverseSyncProcessor
     * @param TypesRegistry $typesRegistry
     * @param JobProcessor $jobProcessor
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        ReverseSyncProcessor $reverseSyncProcessor,
        TypesRegistry $typesRegistry,
        JobProcessor $jobProcessor
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->reverseSyncProcessor = $reverseSyncProcessor;
        $this->typesRegistry = $typesRegistry;
        $this->jobProcessor = $jobProcessor;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedTopics()
    {
        return [Topics::REVERS_SYNC_INTEGRATION];
    }

    /**
     * {@inheritdoc}
     */
    public function process(MessageInterface $message, SessionInterface $session)
    {
        // TODO CRM-5838 message could be redelivered on dbal transport if run for a long time.

        $body = JSON::decode($message->getBody());
        $body = array_replace_recursive([
            'integrationId' => null,
            'connector' => null,
            'connector_parameters' => [],
        ], $body);

        if (false == $body['integrationId']) {
            throw new \LogicException('The message invalid. It must have integrationId set');
        }

        $jobName = 'oro_integration:revers_sync_integration:'.$body['integrationId'];
        $ownerId = $message->getMessageId();

        $jobRunner = $this->jobProcessor->createJobRunner();
        $result = $jobRunner->runUnique($ownerId, $jobName, function () use ($body) {
            /** @var EntityManagerInterface $em */
            $em = $this->doctrineHelper->getEntityManagerForClass(Integration::class);

            /** @var Integration $integration */
            $integration = $em->find(Integration::class, $body['integrationId']);
            if (false == $integration) {
                return false;
            }
            if (false == $integration->isEnabled()) {
                return false;
            }

            $em->getConnection()->getConfiguration()->setSQLLogger(null);

            $connector = $this->typesRegistry->getConnectorType($integration->getType(), $body['connector']);
            if (!$connector instanceof TwoWaySyncConnectorInterface) {
                throw new LogicException(sprintf(
                    'Unable to perform revers sync for integration "%s" and connector type "%s"',
                    $integration->getId(),
                    $body['connector']
                ));
            }

            $this->reverseSyncProcessor->process(
                $integration,
                $body['connector'],
                $body['connector_parameters']
            );

            return true;
        });

        return $result ? self::ACK : self::REJECT;
    }
}
