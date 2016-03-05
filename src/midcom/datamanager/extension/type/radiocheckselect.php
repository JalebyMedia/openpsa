<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 */

namespace midcom\datamanager\extension\type;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Experimental select type
 */
class radiocheckselect extends ChoiceType
{
    /**
     *  Symfony 2.6 compat
     *
     * {@inheritDoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $this->configureOptions($resolver);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $map_options = function (Options $options)
        {
            if (isset($options['type_config']['options']))
            {
                return $options['type_config']['options'];
            }
        };
        $map_multiple = function (Options $options)
        {
            return !empty($options['type_config']['allow_multiple']);
        };

        $resolver->setDefaults(array
        (
            'choices' => $map_options,
            'choices_as_values' => true,
            'expanded' => true,
            'multiple' => $map_multiple,
            'placeholder' => false
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);
        $view->vars['attr']['class'] = $options['multiple'] ? 'checkbox' : 'radiobox';
    }

    /**
     * {@inheritdoc}
     *
     * Symfony < 2.8 compat
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'radiocheckselect';
    }
}