<?php
/**
 * @package net.nemein.rss
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * @package net.nemein.rss
 */
class net_nemein_rss_helpers extends midcom_baseclasses_components_purecode
{
    /**
     * Add default RSS config options to component config schema.
     *
     * Used with array_merge
     *
     * @param string $component the component to insert to
     * @return array of DM2 schema fields
     */
    public static function default_rss_config_schema_fields($component)
    {
        return array
        (
            'rss_enable' => array
            (
                'title' => midcom::get('i18n')->get_string('rss_enable', 'net.nemein.rss'),
                'storage' => array
                (
                    'location' => 'configuration',
                    'domain' => $component,
                    'name' => 'rss_enable',
                ),
                'type' => 'select',
                'type_config' => array
                (
                    'options' => array
                    (
                        '' => 'default setting',
                        '1' => 'yes',
                        '0' => 'no',
                    ),
                ),
                'widget' => 'select',
                'start_fieldset' => array
                (
                    'title' =>  midcom::get('i18n')->get_string('rss output settings', 'net.nemein.rss'),
                ),
            ),
            'rss_count' => array
            (
                'title' => midcom::get('i18n')->get_string('rss_count', 'net.nemein.rss'),
                'storage' => array
                (
                    'location' => 'configuration',
                    'domain' => $component,
                    'name' => 'rss_count',
                ),
                'type' => 'number',
                'widget' => 'text',
            ),
            'rss_title' => array
            (
                'title' => midcom::get('i18n')->get_string('rss_title', 'net.nemein.rss'),
                'storage' => array
                (
                    'location' => 'configuration',
                    'domain' => $component,
                    'name' => 'rss_title',
                ),
                'type' => 'text',
                'widget' => 'text',
            ),
            'rss_description' => array
            (
                'title' => midcom::get('i18n')->get_string('rss_description', 'net.nemein.rss'),
                'storage' => array
                (
                    'location' => 'configuration',
                    'domain' => $component,
                    'name' => 'rss_description',
                ),
                'type' => 'text',
                'widget' => 'text',
            ),
            'rss_webmaster' => array
            (
                'title' => midcom::get('i18n')->get_string('rss_webmaster', 'net.nemein.rss'),
                'storage' => array
                (
                    'location' => 'configuration',
                    'domain' => $component,
                    'name' => 'rss_webmaster',
                ),
                'type' => 'text',
                'widget' => 'text',
            ),
            'rss_language' => array
            (
                'title' => midcom::get('i18n')->get_string('rss_language', 'net.nemein.rss'),
                'storage' => array
                (
                    'location' => 'configuration',
                    'domain' => $component,
                    'name' => 'rss_language',
                ),
                'type' => 'text',
                'widget' => 'text',
            ),
        );
    }
}