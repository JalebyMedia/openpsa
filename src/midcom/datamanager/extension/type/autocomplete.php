<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
 */

namespace midcom\datamanager\extension\type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\Form\AbstractType;
use midcom\datamanager\extension\transformer\autocomplete as transformer;
use midcom\datamanager\extension\transformer\json as jsontransformer;
use midcom\datamanager\extension\helper;
use midcom;
use midcom_error;
use midcom_connection;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use midcom\datamanager\extension\compat;

/**
 * Experimental autocomplete type
 */
class autocomplete extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setNormalizer('widget_config', function (Options $options, $value)
        {
            $widget_defaults = array
            (
                'creation_mode_enabled' => false,
                'class' => null,
                'component' => null,
                'id_field' => 'guid',
                'constraints' => array(),
                'result_headers' => array(),
                'orders' => array(),
                'auto_wildcards' => 'both',
                'creation_handler' => null,
                'creation_default_key' => null,
                'get_label_for' => null,
                'categorize_by_parent_label' => false,
                'searchfields' => array(),
                'min_chars' => 2,
                'sortable' => false
            );
            return helper::resolve_options($widget_defaults, $value);
        });
        $resolver->setNormalizer('type_config', function (Options $options, $value)
        {
            $type_defaults = array
            (
                'options' => array(),
                'allow_other' => false,
                'allow_multiple' => ($options['dm2_type'] == 'mnrelation'),
                'require_corresponding_option' => true,
                'multiple_storagemode' => 'serialized',
                'multiple_separator' => '|'
            );
            return helper::resolve_options($type_defaults, $value);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new transformer($options));
        $builder->add('selection', compat::get_type_name('hidden'));
        $builder->get('selection')->addViewTransformer(new jsontransformer);
        $builder->add('search_input', compat::get_type_name('search'), array('mapped' => false));

        $head = midcom::get()->head;
        $head->enable_jquery();

        $theme_files = array('autocomplete', 'menu');
        $head->add_stylesheet(MIDCOM_STATIC_URL . '/midcom.helper.datamanager2/autocomplete.css');

        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/core.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/widget.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/position.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/menu.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/autocomplete.min.js');
        $head->add_jsfile(MIDCOM_STATIC_URL . '/midcom.helper.datamanager2/autocomplete.js');

        if ($options['widget_config']['sortable'])
        {
            $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/mouse.min.js');
            $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/sortable.min.js');
        }

        if ($options['widget_config']['creation_mode_enabled'])
        {
            $theme_files[] = 'dialog';
            $theme_files[] = 'button';
            $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/mouse.min.js');
            $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/draggable.min.js');
            $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/resizable.min.js');
            $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/button.min.js');
            $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/dialog.min.js');
        }

        $head->add_jquery_ui_theme($theme_files);
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $handler_url = midcom_connection::get_url('self') . 'midcom-exec-midcom.helper.datamanager2/autocomplete_handler.php';

        $preset = array();
        if (!empty($view->vars['data']['selection']))
        {
            foreach ($view->vars['data']['selection'] as $identifier)
            {
                if ($options['widget_config']['id_field'] == 'id')
                {
                    $identifier = (int) $identifier;
                }
                try
                {
                    $object = new $options['widget_config']['class']($identifier);
                    $preset[$identifier] = \midcom_helper_datamanager2_widget_autocomplete::create_item_label($object, $options['widget_config']['result_headers'], $options['widget_config']['get_label_for']);
                }
                catch (midcom_error $e)
                {
                    $e->log();
                }
            }
        }

        $handler_options = $options['widget_config'];
        $handler_options['handler_url'] = $handler_url;
        $handler_options['allow_multiple'] = $options['type_config']['allow_multiple'];
        $handler_options['preset'] = $preset;
        $handler_options['preset_order'] = array_reverse(array_keys($preset));

        $view->vars['min_chars'] = $options['widget_config']['min_chars'];
        $view->vars['handler_options'] = json_encode($handler_options);
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
        return 'autocomplete';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return compat::get_type_name('form');
    }
}