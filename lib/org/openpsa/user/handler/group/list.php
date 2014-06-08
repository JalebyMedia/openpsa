<?php
/**
 * @package org.openpsa.user
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * Group listing class for user management
 *
 * @package org.openpsa.user
 */
class org_openpsa_user_handler_group_list extends midcom_baseclasses_components_handler
{
    /**
     * Handle the group listing
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_list($handler_id, array $args, array &$data)
    {
        midcom::get()->auth->require_user_do('org.openpsa.user:access', null, 'org_openpsa_user_interface');

        $tree = new org_openpsa_widgets_tree('midcom_db_group', 'owner');
        $tree->title_fields = array('official', 'name');
        $tree->link_callback = array(__CLASS__, 'render_link');
        $data['tree'] = $tree;

        $this->add_breadcrumb("", $this->_l10n->get('groups'));
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "group/create/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create group'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_people-new.png',
                MIDCOM_TOOLBAR_ENABLED => midcom::get()->auth->can_user_do('midgard:create', null, 'midcom_db_group'),
            )
        );
    }

    public static function render_link($guid)
    {
        $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

        return $prefix . 'group/' . $guid . '/';
    }

    /**
     * Show the group listing
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_list($handler_id, array &$data)
    {
        midcom_show_style('group-list');
    }
}
?>