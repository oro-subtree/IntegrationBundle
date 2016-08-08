<?php
namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Async;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\IntegrationBundle\Async\ReversSyncIntegrationProcessor;
use Oro\Bundle\IntegrationBundle\Async\Topics;
use Oro\Bundle\IntegrationBundle\Entity\Channel as Integration;
use Oro\Bundle\IntegrationBundle\Exception\LogicException;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Bundle\IntegrationBundle\Provider\ConnectorInterface;
use Oro\Bundle\IntegrationBundle\Provider\ReverseSyncProcessor;
use Oro\Bundle\IntegrationBundle\Provider\TwoWaySyncConnectorInterface;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Test\JobExtensionTrait;
use Oro\Component\MessageQueue\Transport\Null\NullMessage;
use Oro\Component\MessageQueue\Transport\Null\NullSession;
use Oro\Component\MessageQueue\Util\JSON;
use Oro\Component\Testing\ClassExtensionTrait;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class ReversSyncIntegrationProcessorTest extends \PHPUnit_Framework_TestCase
{
    use ClassExtensionTrait;
    use JobExtensionTrait;

    public function testShouldImplementMessageProcessorInterface()
    {
        $this->assertClassImplements(MessageProcessorInterface::class, ReversSyncIntegrationProcessor::class);
    }

    public function testShouldImplementTopicSubscriberInterface()
    {
        $this->assertClassImplements(TopicSubscriberInterface::class, ReversSyncIntegrationProcessor::class);
    }

    public function testShouldImplementContainerAwareInterface()
    {
        $this->assertClassImplements(ContainerAwareInterface::class, ReversSyncIntegrationProcessor::class);
    }

    public function testShouldSubscribeOnReversSyncIntegrationTopic()
    {
        $this->assertEquals([Topics::REVERS_SYNC_INTEGRATION], ReversSyncIntegrationProcessor::getSubscribedTopics());
    }

    public function testCouldBeConstructedWithExpectedArguments()
    {
        new ReversSyncIntegrationProcessor(
            $this->createDoctrineHelperStub(),
            $this->createReversSyncProcessorMock(),
            $this->createTypeRegistryMock(),
            $this->createJobProcessorStub()
        );
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The message invalid. It must have integrationId set
     */
    public function testThrowIfMessageBodyMissIntegrationId()
    {
        $processor = new ReversSyncIntegrationProcessor(
            $this->createDoctrineHelperStub(),
            $this->createReversSyncProcessorMock(),
            $this->createTypeRegistryMock(),
            $this->createJobProcessorStub()
        );

        $message = new NullMessage();
        $message->setBody('[]');

        $processor->process($message, new NullSession());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The malformed json given.
     */
    public function testThrowIfMessageBodyInvalidJson()
    {
        $processor = new ReversSyncIntegrationProcessor(
            $this->createDoctrineHelperStub(),
            $this->createReversSyncProcessorMock(),
            $this->createTypeRegistryMock(),
            $this->createJobProcessorStub()
        );

        $message = new NullMessage();
        $message->setBody('[}');

        $processor->process($message, new NullSession());
    }
    
    public function testShouldRejectMessageIfIntegrationNotExist()
    {
        $entityManagerMock = $this->createEntityManagerStub();
        $entityManagerMock
            ->expects($this->once())
            ->method('find')
            ->with(Integration::class, 'theIntegrationId')
            ->willReturn(null);
        ;

        $doctrineHelperStub = $this->createDoctrineHelperStub($entityManagerMock);

        $processor = new ReversSyncIntegrationProcessor(
            $doctrineHelperStub,
            $this->createReversSyncProcessorMock(),
            $this->createTypeRegistryMock(),
            $this->createJobProcessorStub()
        );

        $message = new NullMessage();
        $message->setBody(JSON::encode(['integrationId' => 'theIntegrationId']));

        $status = $processor->process($message, new NullSession());

        $this->assertEquals(MessageProcessorInterface::REJECT, $status);
    }

    public function testShouldRejectMessageIfIntegrationIsNotEnabled()
    {
        $integration = new Integration();
        $integration->setEnabled(false);

        $entityManagerMock = $this->createEntityManagerStub();
        $entityManagerMock
            ->expects($this->once())
            ->method('find')
            ->with(Integration::class, 'theIntegrationId')
            ->willReturn($integration);
        ;

        $doctrineHelperStub = $this->createDoctrineHelperStub($entityManagerMock);

        $processor = new ReversSyncIntegrationProcessor(
            $doctrineHelperStub,
            $this->createReversSyncProcessorMock(),
            $this->createTypeRegistryMock(),
            $this->createJobProcessorStub()
        );

        $message = new NullMessage();
        $message->setBody(JSON::encode(['integrationId' => 'theIntegrationId']));

        $status = $processor->process($message, new NullSession());

        $this->assertEquals(MessageProcessorInterface::REJECT, $status);
    }
    
    public function testThrowIfConnectionIsNotInstanceOfTwoWaySyncConnector()
    {
        $integration = new Integration();
        $integration->setEnabled(true);
        $integration->setType('theIntegrationType');

        $entityManagerMock = $this->createEntityManagerStub();
        $entityManagerMock
            ->expects($this->once())
            ->method('find')
            ->with(Integration::class, 'theIntegrationId')
            ->willReturn($integration);
        ;

        $doctrineHelperStub = $this->createDoctrineHelperStub($entityManagerMock);

        $typeRegistryMock = $this->createTypeRegistryMock();
        $typeRegistryMock
            ->expects(self::once())
            ->method('getConnectorType')
            ->with('theIntegrationType', 'theConnector')
            ->willReturn($this->getMock(ConnectorInterface::class))
        ;

        $reversSyncProcessorMock = $this->createReversSyncProcessorMock();
        $reversSyncProcessorMock
            ->expects(self::never())
            ->method('process')
        ;

        $processor = new ReversSyncIntegrationProcessor(
            $doctrineHelperStub,
            $reversSyncProcessorMock,
            $typeRegistryMock,
            $this->createJobProcessorStub()
        );

        $message = new NullMessage();
        $message->setBody(JSON::encode(['integrationId' => 'theIntegrationId', 'connector' => 'theConnector']));

        $this->setExpectedException(LogicException::class, 'Unable to perform revers sync');
        $processor->process($message, new NullSession());
    }

    public function testShouldRunSyncAsUniqueJob()
    {
        $integration = new Integration();
        $integration->setEnabled(true);
        $integration->setType('theIntegrationType');

        $entityManagerMock = $this->createEntityManagerStub();
        $entityManagerMock
            ->expects($this->once())
            ->method('find')
            ->with(Integration::class, 'theIntegrationId')
            ->willReturn($integration);
        ;

        $doctrineHelperStub = $this->createDoctrineHelperStub($entityManagerMock);

        $typeRegistryMock = $this->createTypeRegistryMock();
        $typeRegistryMock
            ->expects(self::once())
            ->method('getConnectorType')
            ->willReturn($this->getMock(TwoWaySyncConnectorInterface::class))
        ;

        $jobRunner = $this->createJobRunner();

        $processor = new ReversSyncIntegrationProcessor(
            $doctrineHelperStub,
            $this->createReversSyncProcessorMock(),
            $typeRegistryMock,
            $this->createJobProcessorStub($jobRunner)
        );

        $message = new NullMessage();
        $message->setBody(JSON::encode(['integrationId' => 'theIntegrationId', 'connector' => 'theConnector']));
        $message->setMessageId('theMessageId');

        $processor->process($message, new NullSession());

        $uniqueJobs = $jobRunner->getRunUniqueJobs();
        self::assertCount(1, $uniqueJobs);
        self::assertEquals('oro_integration:revers_sync_integration:theIntegrationId', $uniqueJobs[0]['jobName']);
        self::assertEquals('theMessageId', $uniqueJobs[0]['ownerId']);
    }

    public function testShouldPerformReversSyncIfConnectorIsInstanceOfTwoWaySyncInterface()
    {
        $integration = new Integration();
        $integration->setEnabled(true);
        $integration->setType('theIntegrationType');

        $entityManagerMock = $this->createEntityManagerStub();
        $entityManagerMock
            ->expects($this->once())
            ->method('find')
            ->with(Integration::class, 'theIntegrationId')
            ->willReturn($integration);
        ;

        $doctrineHelperStub = $this->createDoctrineHelperStub($entityManagerMock);

        $typeRegistryMock = $this->createTypeRegistryMock();
        $typeRegistryMock
            ->expects(self::once())
            ->method('getConnectorType')
            ->with('theIntegrationType', 'theConnector')
            ->willReturn($this->getMock(TwoWaySyncConnectorInterface::class))
        ;

        $processor = new ReversSyncIntegrationProcessor(
            $doctrineHelperStub,
            $this->createReversSyncProcessorMock(),
            $typeRegistryMock,
            $this->createJobProcessorStub()
        );

        $message = new NullMessage();
        $message->setBody(JSON::encode(['integrationId' => 'theIntegrationId', 'connector' => 'theConnector']));

        $status = $processor->process($message, new NullSession());

        $this->assertEquals(MessageProcessorInterface::ACK, $status);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|TypesRegistry
     */
    private function createTypeRegistryMock()
    {
        return $this->getMock(TypesRegistry::class, [], [], '', false);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|ReverseSyncProcessor
     */
    private function createReversSyncProcessorMock()
    {
        return $this->getMock(ReverseSyncProcessor::class, [], [], '', false);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|EntityManagerInterface
     */
    private function createEntityManagerStub()
    {
        $configuration = new Configuration();

        $connectionMock = $this->getMock(Connection::class, [], [], '', false);
        $connectionMock
            ->expects($this->any())
            ->method('getConfiguration')
            ->willReturn($configuration)
        ;

        $entityManagerMock = $this->getMock(EntityManagerInterface::class);
        $entityManagerMock
            ->expects($this->any())
            ->method('getConnection')
            ->willReturn($connectionMock)
        ;

        return $entityManagerMock;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|DoctrineHelper
     */
    private function createDoctrineHelperStub($entityManager = null)
    {
        $helperMock = $this->getMock(DoctrineHelper::class, [], [], '', false);
        $helperMock
            ->expects($this->any())
            ->method('getEntityManagerForClass')
            ->willReturn($entityManager)
        ;

        return $helperMock;
    }
}
