<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Manager;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Manager\SyncScheduler;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture\TestChannelType;
use Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture\TestConnector;
use Oro\Bundle\IntegrationBundle\Tests\Unit\Fixture\TestTwoWayConnector;

class SyncSchedulerTest extends \PHPUnit_Framework_TestCase
{
    /** @var EntityManager|\PHPUnit_Framework_MockObject_MockObject */
    protected $em;

    /** @var TypesRegistry */
    protected $typesRegistry;

    /** @var SyncScheduler */
    protected $scheduler;

    public function setUp()
    {
        $this->em            = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()->getMock();
        $this->typesRegistry = new TypesRegistry();
        $this->scheduler     = new SyncScheduler($this->em, $this->typesRegistry);
    }

    public function tearDown()
    {
        unset($this->em, $this->typesRegistry, $this->scheduler);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Connectors not found for channel "testType"
     */
    public function testScheduleRegistryError()
    {
        $channel = new Channel();
        $channel->setType('testType');

        $this->scheduler->schedule($channel, '');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Unable to schedule job for "testConnectorType" connector type
     */
    public function testScheduleConnectorError()
    {
        $testChannelType   = 'testChannelType';
        $testConnectorType = 'testConnectorType';

        $channel = new Channel();
        $channel->setType($testChannelType);
        $this->typesRegistry->addChannelType($testChannelType, new TestChannelType());
        $this->typesRegistry->addConnectorType($testConnectorType, $testChannelType, new TestConnector());

        $this->scheduler->schedule($channel, $testConnectorType);
    }

    /**
     */
    public function testSchedule()
    {
        $testChannelType   = 'testChannelType';
        $testConnectorType = 'testConnectorType';

        $channel = new Channel();
        $channel->setType($testChannelType);
        $this->typesRegistry->addChannelType($testChannelType, new TestChannelType());
        $this->typesRegistry->addConnectorType($testConnectorType, $testChannelType, new TestTwoWayConnector());
        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf('JMS\JobQueueBundle\Entity\Job'));
        $this->em->expects($this->once())->method('flush')
            ->with($this->isInstanceOf('JMS\JobQueueBundle\Entity\Job'));

        $this->scheduler->schedule($channel, $testConnectorType);
    }
}
