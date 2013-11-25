<?php

namespace Oro\Bundle\IntegrationBundle\Form\Type;

use Doctrine\Common\Collections\ArrayCollection;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use Oro\Bundle\IntegrationBundle\Manager\TypesRegistry;
use Oro\Bundle\IntegrationBundle\Form\EventListener\ChannelFormSubscriber;

class ChannelType extends AbstractType
{
    const NAME            = 'oro_integration_channel_form';
    const TYPE_FIELD_NAME = 'type';

    /** @var TypesRegistry */
    protected $registry;

    public function __construct(TypesRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new ChannelFormSubscriber($this->registry));

        $builder->add(
            self::TYPE_FIELD_NAME,
            'choice',
            [
                'required' => true,
                'choices'  => $this->registry->getAvailableChannelTypesChoiceList(),
                'label'    => 'Type'
            ]
        );
        $builder->add('name', 'text', ['required' => true, 'label' => 'Name']);

        // add transport type selector
        $builder->add(
            'transportType',
            'choice',
            [
                'label'       => 'Transport type',
                'choices'     => [], //will be filled in event listener
                'mapped'      => false,
                'constraints' => new NotBlank()
            ]
        );

        // add connectors
        $builder->add(
            'connectors',
            'choice',
            [
                'label'    => 'Connectors',
                'expanded' => true,
                'multiple' => true,
                'choices'  => [], //will be filled in event listener
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class' => 'Oro\\Bundle\\IntegrationBundle\\Entity\\Channel',
                'intention'  => 'channel',
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }
}
