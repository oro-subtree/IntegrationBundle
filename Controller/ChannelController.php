<?php

namespace Oro\Bundle\IntegrationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\IntegrationBundle\Entity\Channel;

/**
 * @Route("/channel")
 */
class ChannelController extends Controller
{
    /**
     * @Route("/index")
     * @Acl(
     *      id="oro_integration_channel_index",
     *      type="entity",
     *      permission="VIEW",
     *      class="OroIntegrationBundle:Channel"
     * )
     * @Template()
     */
    public function indexAction()
    {
        return [];
    }

    /**
     * @Route("/create")
     * @Acl(
     *      id="oro_integration_channel_create",
     *      type="entity",
     *      permission="CREATE",
     *      class="OroIntegrationBundle:Channel"
     * )
     * @Template("OroIntegrationBundle:Channel:update.html.twig")
     */
    public function createAction()
    {
        return $this->update(new Channel());
    }

    /**
     * @Route("/update/{id}", requirements={"id"="\d+"}))
     * @Acl(
     *      id="oro_integration_channel_update",
     *      type="entity",
     *      permission="EDIT",
     *      class="OroIntegrationBundle:Channel"
     * )
     * @Template()
     */
    public function updateAction(Channel $channel)
    {
        return $this->update($channel);
    }

    /**
     * @param Channel $channel
     *
     * @return array
     */
    protected function update(Channel $channel)
    {
        if ($this->get('oro_integration.form.handler.channel')->process($channel)) {
            $this->get('session')->getFlashBag()->add(
                'success',
                $this->get('translator')->trans('oro.integration.controller.channel.message.saved')
            );

            return $this->get('oro_ui.router')->actionRedirect(
                ['route' => 'oro_integration_channel_update', 'parameters' => ['id' => $channel->getId()]],
                ['route' => 'oro_integration_channel_index']
            );
        }

        return [
            'form' => $this->get('oro_integration.form.channel')->createView()
        ];
    }
}
