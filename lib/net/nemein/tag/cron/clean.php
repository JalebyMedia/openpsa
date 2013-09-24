<?php
/**
 * @package net.nemein.tag
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Cron handler for periodical gallery sync
 * @package net.nemein.tag
 */
class net_nemein_tag_cron_clean extends midcom_baseclasses_components_cron_handler
{
    /**
     * Find all old temporary reports and clear them.
     */
    public function _on_execute()
    {
        debug_add('_on_execute called');

        midcom::get('auth')->request_sudo('net.nemein.tag');
        $qb_tags = net_nemein_tag_tag_dba::new_query_builder();
        $tags = $qb_tags->execute_unchecked();

        foreach ($tags as $tag)
        {
            debug_add("Processing tag #{$tag->id} ('{$tag->tag}')");
            $qb_links = net_nemein_tag_link_dba::new_query_builder();
            $qb_links->add_constraint('tag', '=', $tag->id);
            $count = $qb_links->count_unchecked();

            if ($count > 0)
            {
                // Tag has links, skip
                debug_add("Tag has links to it, do not clean");
                continue;
            }
            debug_add("Cleaning dangling tag #{$tag->id} ('{$tag->tag}')", MIDCOM_LOG_INFO);
            if (!$tag->delete())
            {
                debug_add("Could not delete dangling tag #{$tag->id} ('{$tag->tag}'), errstr: " . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
            }
        }

        debug_add('done');
        midcom::get('auth')->drop_sudo();
    }
}
?>