<?php
/**
 * @package net.nemein.rss
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Feed management class.
 *
 * @package net.nemein.rss
 */
class net_nemein_rss_manage extends midcom_baseclasses_components_plugin
{
    public function _on_initialize()
    {
        // Make sure constants are available
        midcom::get('componentloader')->load_library($this->_component);
        // Ensure we get the correct styles
        midcom::get('style')->prepend_component_styledir($this->_component);

        $this->_request_data['node'] = $this->_topic;
    }

    public static function register_plugin(midcom_baseclasses_components_request $viewer)
    {
        $viewer->register_plugin_namespace('__feeds', array('rss' => array('class' => __CLASS__)));
    }

    public static function add_toolbar_buttons(midcom_helper_toolbar $toolbar, $enabled = true)
    {
        $l10n = midcom::get('i18n')->get_l10n('net.nemein.rss');
        $toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => '__feeds/rss/subscribe/',
                MIDCOM_TOOLBAR_LABEL => $l10n->get('subscribe feeds'),
                MIDCOM_TOOLBAR_ICON => 'net.nemein.rss/rss-16.png',
                MIDCOM_TOOLBAR_ENABLED => $enabled,
            )
        );
        $toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => '__feeds/rss/list/',
                MIDCOM_TOOLBAR_LABEL => $l10n->get('manage feeds'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/properties.png',
                MIDCOM_TOOLBAR_ENABLED => $enabled,
            )
        );
        $toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "__feeds/rss/fetch/all/",
                MIDCOM_TOOLBAR_LABEL => $l10n->get('refresh all feeds'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_refresh.png',
                MIDCOM_TOOLBAR_ENABLED => $enabled,
            )
        );
    }
}
?>