<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Manager;

use Oro\Bundle\IntegrationBundle\Async\Topics;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Manager\GenuineSyncScheduler;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Client\TraceableMessageProducer;

class GenuineSyncSchedulerTest extends \PHPUnit_Framework_TestCase
{
    public function testCouldBeConstructedWithRegistryAsFirstArgument()
    {
        new GenuineSyncScheduler($this->createTraceableMessageProducer());
    }

    public function testShouldSendSyncIntegrationMessageWithIntegrationIdOnly()
    {
        $messageProducer = $this->createTraceableMessageProducer();

        $scheduler = new GenuineSyncScheduler($messageProducer);

        $scheduler->schedule('theIntegrationId');

        $traces = $messageProducer->getTopicTraces(Topics::SYNC_INTEGRATION);

        $this->assertCount(1, $traces);

        $this->assertEquals(Topics::SYNC_INTEGRATION, $traces[0]['topic']);
        $this->assertEquals(MessagePriority::VERY_LOW, $traces[0]['priority']);
        $this->assertEquals([
            'integrationId' => 'theIntegrationId',
            'connector' => null,
            'connector_parameters' => [],
            'transport_batch_size' => 100,
        ], $traces[0]['message']);
    }

    public function testShouldAllowPassConnectorNameAndOptions()
    {
        $messageProducer = $this->createTraceableMessageProducer();

        $scheduler = new GenuineSyncScheduler($messageProducer);

        $scheduler->schedule('theIntegrationId', 'theConnectorName', ['theOption' => 'theValue']);

        $traces = $messageProducer->getTopicTraces(Topics::SYNC_INTEGRATION);

        $this->assertCount(1, $traces);

        $this->assertEquals(Topics::SYNC_INTEGRATION, $traces[0]['topic']);
        $this->assertEquals(MessagePriority::VERY_LOW, $traces[0]['priority']);
        $this->assertEquals([
            'integrationId' => 'theIntegrationId',
            'connector' => 'theConnectorName',
            'connector_parameters' => ['theOption' => 'theValue'],
            'transport_batch_size' => 100,
        ], $traces[0]['message']);
    }

    /**
     * @return TraceableMessageProducer
     */
    protected function createTraceableMessageProducer()
    {
        /** @var MessageProducerInterface $internalMessageProducer */
        $internalMessageProducer = $this->getMock(MessageProducerInterface::class);

        return new TraceableMessageProducer($internalMessageProducer);
    }
}
