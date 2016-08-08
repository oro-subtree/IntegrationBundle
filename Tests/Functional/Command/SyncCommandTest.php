<?php
namespace Oro\Bundle\IntegrationBundle\Tests\Functional\Command;

use Oro\Bundle\IntegrationBundle\Async\Topics;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Tests\Functional\DataFixtures\LoadChannelData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Client\TraceableMessageProducer;

/**
 * @dbIsolationPerTest
 */
class SyncCommandTest extends WebTestCase
{
    public function setUp()
    {
        $this->initClient();
        $this->loadFixtures([LoadChannelData::class]);
    }

    public function testShouldOutputHelpForTheCommand()
    {
        $result = $this->runCommand('oro:cron:integration:sync', ['--help']);

        $this->assertContains("Usage:\n  oro:cron:integration:sync [options]", $result);
    }

    public function testShouldSendSyncIntegrationWithoutAnyAdditionalOptions()
    {
        /** @var Channel $integration */
        $integration = $this->getReference('oro_integration:foo_integration');

        $result = $this->runCommand('oro:cron:integration:sync', ['--integration='.$integration->getId()]);

        $this->assertContains('Run sync for "Foo Integration" integration.', $result);
        $this->assertContains('Completed', $result);

        $traces = $this->getMessageProducer()->getTopicTraces(Topics::SYNC_INTEGRATION);

        $this->assertCount(1, $traces);

        $this->assertEquals([
            'integrationId' => $integration->getId(),
            'connector_parameters' => [],
            'connector' => null,
            'transport_batch_size' => 100,
        ], $traces[0]['message']);
        $this->assertEquals(MessagePriority::VERY_LOW, $traces[0]['priority']);
    }

    public function testShouldSendSyncIntegrationWithCustomConnectorAndOptions()
    {
        /** @var Channel $integration */
        $integration = $this->getReference('oro_integration:foo_integration');

        $result = $this->runCommand('oro:cron:integration:sync', [
            '--integration='.$integration->getId(),
            '--connector' => 'theConnector',
            'fooConnectorOption=fooValue',
            'barConnectorOption=barValue',
        ]);

        $this->assertContains('Run sync for "Foo Integration" integration.', $result);
        $this->assertContains('Completed', $result);

        $traces = $this->getMessageProducer()->getTopicTraces(Topics::SYNC_INTEGRATION);

        $this->assertCount(1, $traces);

        $this->assertEquals([
            'integrationId' => $integration->getId(),
            'connector_parameters' => [
                'fooConnectorOption' => 'fooValue',
                'barConnectorOption' => 'barValue',
            ],
            'connector' => 'theConnector',
            'transport_batch_size' => 100,
        ], $traces[0]['message']);
        $this->assertEquals(MessagePriority::VERY_LOW, $traces[0]['priority']);
    }

    /**
     * @return TraceableMessageProducer
     */
    private function getMessageProducer()
    {
        return self::getContainer()->get('oro_message_queue.message_producer');
    }
}
