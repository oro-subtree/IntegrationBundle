<?php

namespace Oro\Bundle\IntegrationBundle\Provider;

use Doctrine\ORM\EntityManager;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\IntegrationBundle\Entity\Repository\ChannelRepository;
use Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink;

class ConnectorContextMediator
{
    /** @var TypesRegistry */
    protected $registry;

    /** @var ChannelRepository */
    protected $channelRepository;

    public function __construct(ServiceLink $registryLink, EntityManager $em)
    {
        $this->registryLink      = $registryLink;
        $this->channelRepository = $em->getRepository('OroIntegrationBundle:Channel');
    }

    /**
     * Get prepared transport
     *
     * @param ContextInterface|Channel $source
     *
     * @throws \LogicException
     * @return TransportInterface
     */
    public function getTransport($source)
    {
        if ($source instanceof ContextInterface) {
            $source = $this->getChannel($source);
        } elseif (!$source instanceof Channel) {
            throw new \LogicException('Expected type ContextInterface or Channel');
        }

        return clone $this->registryLink->getService()
            ->getTransportTypeBySettingEntity($source->getTransport(), $source->getType());
    }

    /**
     * Get channel instance
     *
     * @param ContextInterface $context
     *
     * @return Channel
     */
    public function getChannel(ContextInterface $context)
    {
        return $this->channelRepository->getOrLoadById($context->getOption('channel'));
    }
}
