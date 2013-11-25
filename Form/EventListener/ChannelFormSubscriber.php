<?php

namespace Oro\Bundle\IntegrationBundle\Form\EventListener;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormInterface;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;

class ChannelFormSubscriber implements EventSubscriberInterface
{
    /** @var TypesRegistry */
    protected $registry;

    public function __construct(TypesRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::PRE_SET_DATA  => 'preSet',
            FormEvents::POST_SET_DATA => 'postSet',
            FormEvents::PRE_SUBMIT    => 'preSubmit'
        ];
    }

    /**
     * Modifies form based on data that comes from DB
     *
     * @param FormEvent $event
     */
    public function preSet(FormEvent $event)
    {
        $form = $event->getForm();
        /** @var Channel $data */
        $data = $event->getData();

        if ($data === null) {
            return;
        }

        $typeChoices = array_keys($form->get('type')->getConfig()->getOption('choices'));
        $firstChoice = reset($typeChoices);

        $type                  = $data->getType() ? : $firstChoice;
        $transportTypeModifier = $this->getTransportTypeModifierClosure($type);
        $transportTypeModifier($form);

        $connectorsModifier = $this->getConnectorsModifierClosure($type);
        $connectorsModifier($form);

        $typeChoices = array_keys($form->get('transportType')->getConfig()->getOption('choices'));
        $firstChoice = reset($typeChoices);
        if ($transport = $data->getTransport()) {
            $transportType = $this->registry->getTransportTypeBySettingEntity($transport, $type, true);
        } else {
            $transportType = $firstChoice;
        }
        $transportModifier = $this->getTransportModifierClosure($type, $transportType);
        $transportModifier($form);

        $data->setType($type);
        $event->setData($data);
    }

    /**
     * Set not mapped field
     *
     * @param FormEvent $event
     */
    public function postSet(FormEvent $event)
    {
        $form = $event->getForm();
        /** @var Channel $data */
        $data = $event->getData();

        if ($data === null) {
            return;
        }

        $typeChoices = array_keys($form->get('transportType')->getConfig()->getOption('choices'));
        $firstChoice = reset($typeChoices);
        if ($transport = $data->getTransport()) {
            $transportType = $this->registry->getTransportTypeBySettingEntity($transport, $data->getType(), true);
        } else {
            $transportType = $firstChoice;
        }
        $form->get('transportType')->setData($transportType);
    }

    /**
     * Modifies form based on submitted data
     *
     * @param FormEvent $event
     */
    public function preSubmit(FormEvent $event)
    {
        $form = $event->getForm();

        /** @var Channel $originalData */
        $originalData = $form->getData();
        $data         = $event->getData();

        if (!empty($data['type'])) {
            $type                  = $data['type'];
            $transportTypeModifier = $this->getTransportTypeModifierClosure($type);
            $transportTypeModifier($form);

            $connectorsModifier = $this->getConnectorsModifierClosure($type);
            $connectorsModifier($form);

            /*
             * If transport type changed we have to modify ViewData(it's already saved entity)
             * due to it's not matched the 'data_class' option of newly added form type
             */
            if ($transport = $originalData->getTransport()) {
                $transportType = $this->registry->getTransportTypeBySettingEntity(
                    $transport,
                    $originalData->getType(),
                    true
                );
                if ($transportType !== $data['transportType']) {
                    /** @var Channel $setEntity */
                    $setEntity = $form->getViewData();
                    $setEntity->setTransport(null);
                }
            }


            $transportModifier = $this->getTransportModifierClosure($type, $data['transportType']);
            $transportModifier($form);
        }
    }

    /**
     * Returns closure that fills transport type choices depends on selected channel type
     *
     * @param string $type
     *
     * @return callable
     */
    protected function getTransportTypeModifierClosure($type)
    {
        $registry = $this->registry;

        return function (FormInterface $form) use ($type, $registry) {
            if (!$type) {
                return;
            }

            if ($form->has('transportType')) {
                $config = $form->get('transportType')->getConfig()->getOptions();
                unset($config['choice_list']);
                unset($config['choices']);
            } else {
                $config = array();
            }

            if (array_key_exists('auto_initialize', $config)) {
                $config['auto_initialize'] = false;
            }

            $choices = $registry->getAvailableTransportTypesChoiceList($type);

            $form->add('transportType', 'choice', array_merge($config, ['choices' => $choices]));
        };
    }

    /**
     * Returns closure that fills connectors choices depends on selected channel type
     *
     * @param string $type
     *
     * @return callable
     */
    protected function getConnectorsModifierClosure($type)
    {
        $registry = $this->registry;

        return function (FormInterface $form) use ($type, $registry) {
            if (!$type) {
                return;
            }

            if ($form->has('connectors')) {
                $config = $form->get('connectors')->getConfig()->getOptions();
                unset($config['choice_list']);
                unset($config['choices']);
            } else {
                $config = array();
            }

            if (array_key_exists('auto_initialize', $config)) {
                $config['auto_initialize'] = false;
            }

            $choices = $registry->getAvailableConnectorsTypesChoiceList($type);

            $form->add('connectors', 'choice', array_merge($config, ['choices' => $choices]));
        };
    }

    /**
     * Returns closure that adds transport field dependent on rest form data
     *
     * @param string $channelType
     * @param string $transportType
     *
     * @return callable
     */
    protected function getTransportModifierClosure($channelType, $transportType)
    {
        $registry = $this->registry;

        return function (FormInterface $form) use ($channelType, $transportType, $registry) {
            if (!($channelType && $transportType)) {
                return;
            }

            $formType = $registry->getTransportType($channelType, $transportType)->getSettingsFormType();
            $form->add('transport', $formType);
        };
    }
}
