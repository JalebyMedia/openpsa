<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 */

namespace midcom\datamanager\extension\type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use midcom;
use Symfony\Component\Form\FormView;
use midcom\datamanager\controller;

/**
 * Experimental autocomplete type
 */
class toolbar extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array
        (
            'operations' => array(),
            'mapped' => false
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $l10n = midcom::get()->i18n->get_l10n('midcom.helper.datamanager2');
        foreach ($options['operations'] as $operation => $button_labels)
        {
            foreach ((array) $button_labels as $key => $label)
            {
                if ($label == '')
                {
                    $label = "form submit: {$operation}";
                }
                $attributes = array
                (
                    'operation' => $operation,
                    'label' => $l10n->get($label),
                    'attr' => array('class' => 'submit ' . $operation)
                );
                if ($operation == controller::SAVE)
                {
                    //@todo Move to template?
                    $attributes['attr']['accesskey'] = 's';
                    $attributes['attr']['class'] .= ' save_' . $key;
                }
                else if ($operation == controller::CANCEL)
                {
                    //@todo Move to template?
                    $attributes['attr']['accesskey'] = 'd';
                    $attributes['attr']['formnovalidate'] = true;
                }

                $builder->add($operation . $key, 'submit', $attributes);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'toolbar';
    }
}