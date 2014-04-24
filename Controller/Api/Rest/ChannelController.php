<?php

namespace Oro\Bundle\IntegrationBundle\Controller\Api\Rest;

use Symfony\Component\HttpFoundation\Response;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;

use FOS\Rest\Util\Codes;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\Controller\Annotations\RouteResource;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SoapBundle\Entity\Manager\ApiEntityManager;
use Oro\Bundle\IntegrationBundle\Form\EventListener\ChannelFormSubscriber;

/**
 * @RouteResource("channel")
 * @NamePrefix("oro_api_")
 */
class ChannelController extends FOSRestController
{
    /**
     * REST DELETE
     *
     * @param int $id
     *
     * @ApiDoc(
     *      description="Delete Channel",
     *      resource=true
     * )
     * @Acl(
     *      id="oro_integration_channel_delete",
     *      type="entity",
     *      permission="DELETE",
     *      class="OroIntegrationBundle:Channel"
     * )
     * @return Response
     */
    public function deleteAction($id)
    {
        $entity   = $this->getManager()->find($id);
        if (!$entity) {
            return $this->handleView($this->view(null, Codes::HTTP_NOT_FOUND));
        }
        $this->get('oro_integration.channel_delete_manager')->deleteChannel($entity);
        return $this->handleView($this->view(null, Codes::HTTP_NO_CONTENT));
    }

    /**
     * Get entity Manager
     *
     * @return ApiEntityManager
     */
    public function getManager()
    {
        return $this->get('oro_integration.channel.manager.api');
    }
}
