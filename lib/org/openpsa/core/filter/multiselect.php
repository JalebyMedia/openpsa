<?php
/**
 * @package org.openpsa.core
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Helper class that encapsulates a multiselect query filter
 *
 * @package org.openpsa.core
 */
class org_openpsa_core_filter_multiselect extends org_openpsa_core_filter_select
{
    public function add_head_elements()
    {
        $head = midcom::get()->head;
        $head->enable_jquery();
        $head->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.core/jquery-ui-multiselect-widget/jquery.multiselect.css");
        $head->add_jquery_ui_theme(array('widget'));

        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/core.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/widget.min.js');
        $head->add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.core/jquery-ui-multiselect-widget/src/jquery.multiselect.min.js');

        $lang = midcom::get()->i18n->get_current_language();
        if (!file_exists(MIDCOM_STATIC_ROOT . "/org.openpsa.core/jquery-ui-multiselect-widget/i18n/jquery.multiselect.{$lang}.js"))
        {
            $lang = midcom::get()->i18n->get_fallback_language();
            if (!file_exists(MIDCOM_STATIC_ROOT . "/org.openpsa.core/jquery-ui-multiselect-widget/i18n/jquery.multiselect.{$lang}.js"))
            {
                $lang = false;
            }
        }

        if ($lang)
        {
            $head->add_jsfile(MIDCOM_STATIC_URL . "/org.openpsa.core/jquery-ui-multiselect-widget/i18n/jquery.multiselect.{$lang}.js");
        }
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $options = $this->_get_options();

        if (!empty($options))
        {
            echo '<select class="filter_input" id="select_' . $this->name . '" name="' . $this->name . '[]" size="1" multiple="multiple" >';

            foreach ($options as $option)
            {
                echo '<option value="' . $option['id'] . '"';
                if ($option['selected'] == true)
                {
                    echo " selected=\"selected\"";
                }
                echo '>' . $option['title'] . "</option>\n";
            }
            echo "\n</select>\n";

            $this->_render_actions();

            $config = array
            (
                'height' => 200,
                'noneSelectedText' => $this->_label,
                'selectedList' => 2
            );

            echo '<script type="text/javascript">';
            echo "\$('#select_" . $this->name . "').multiselect(\n";
            echo json_encode($config) . " );\n";
            echo "\n</script>\n";
        }
    }
}
