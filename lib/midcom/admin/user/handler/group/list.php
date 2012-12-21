<?php
/**
 * @package midcom.admin.user
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * List groups
 *
 * @package midcom.admin.user
 */
class midcom_admin_user_handler_group_list extends midcom_baseclasses_components_handler
{
    public function _on_initialize()
    {
        $this->add_stylesheet(MIDCOM_STATIC_URL . '/midcom.admin.user/usermgmt.css');

        midgard_admin_asgard_plugin::prepare_plugin($this->_l10n->get('midcom.admin.user'), $this->_request_data);
    }

    /**
     * Populate breadcrumb
     */
    private function _update_breadcrumb($handler_id)
    {
        $this->add_breadcrumb("__mfa/asgard_midcom.admin.user/", $this->_l10n->get('midcom.admin.user'));
        $this->add_breadcrumb("__mfa/asgard_midcom.admin.user/group/", $this->_l10n->get('groups'));

        if (preg_match('/group_move$/', $handler_id))
        {
            $this->add_breadcrumb("__mfa/asgard_midcom.admin.user/group/{$this->_request_data['group']->guid}/", $this->_request_data['group']->official);
            $this->add_breadcrumb("__mfa/asgard_midcom.admin.user/group/move/{$this->_request_data['group']->guid}/", $this->_l10n_midcom->get_string('move'));
        }
    }

    /**
     * Handle the moving of a group phase
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_move($handler_id, array $args, array &$data)
    {
        $data['group'] = new midcom_db_group($args[0]);
        // Get the prefix
        $data['prefix'] = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

        if (isset($_POST['f_cancel']))
        {
            return new midcom_response_relocate("__mfa/asgard_midcom.admin.user/group/edit/{$data['group']->guid}/");
        }

        if (isset($_POST['f_submit']))
        {
            echo "<pre>\n";
            print_r($_POST);
            echo "</pre>\n";
            $data['group']->owner = (int) $_POST['midcom_admin_user_move_group'];

            if ($data['group']->update())
            {
                midcom::get('uimessages')->add($this->_l10n->get('midcom.admin.user'), midcom::get('i18n')->get_string('updated', 'midcom'));
                return new midcom_response_relocate("__mfa/asgard_midcom.admin.user/group/edit/{$data['group']->guid}/");
            }
            else
            {
                debug_add('Failed to update the group, last midcom_connection::get_error_string was '. midgard_connection::get_error_string(), MIDCOM_LOG_ERROR);
                debug_print_r('We operated on this object', $data['group'], MIDCOM_LOG_ERROR);

                throw new midcom_error('Failed to update the group, see error level log for details');
            }
        }

        $data['view_title'] = sprintf($this->_l10n->get('move %s'), $data['group']->official);
        midcom::get('head')->set_pagetitle($data['view_title']);

        $this->_update_breadcrumb($handler_id);
    }

    /**
     * Show the moving of a group phase
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_move($handler_id, array &$data)
    {
        midgard_admin_asgard_plugin::asgard_header();
        midcom_show_style('midcom-admin-user-group-list-start');

        // Show the form headers
        midcom_show_style('midcom-admin-user-move-group-start');

        // Show the recursive listing
        self::list_groups(0, $data, true);

        // Show the form footers
        midcom_show_style('midcom-admin-user-move-group-end');

        midcom_show_style('midcom-admin-user-group-list-end');
        midgard_admin_asgard_plugin::asgard_footer();
    }

    /**
     * Handle the listing phase
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_list($handler_id, array $args, array &$data)
    {
        // Get the prefix
        $data['prefix'] = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

        $data['view_title'] = midcom::get('i18n')->get_string('groups', 'midcom.admin.user');

        midcom::get('head')->set_pagetitle($data['view_title']);

        $this->_update_breadcrumb($handler_id);
    }

    /**
     * Show the group listing
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_list($handler_id, array &$data)
    {
        midgard_admin_asgard_plugin::asgard_header();
        midcom_show_style('midcom-admin-user-group-list-start');

        // Show the recursive listing
        self::list_groups(0, $data);

        midcom_show_style('midcom-admin-user-group-list-end');
        midgard_admin_asgard_plugin::asgard_footer();
    }

    /**
     * Internal helper for showing the groups recursively
     *
     * @param int $id
     * @param array &$data
     */
    public static function list_groups($id, &$data, $move = false)
    {
        $mc = midcom_db_group::new_collector('owner', (int) $id);
        $mc->add_value_property('name');
        $mc->add_value_property('official');
        $mc->add_value_property('id');

        // Set the order
        $mc->add_order('metadata.score', 'DESC');
        $mc->add_order('official');
        $mc->add_order('name');

        // Get the results
        $mc->execute();
        $keys = $mc->list_keys();

        // Hide empty groups
        if ($mc->count() === 0)
        {
            return;
        }

        $data['parent_id'] = $id;

        // Group header
        midcom_show_style('midcom-admin-user-group-list-header');

        // Show the groups
        foreach ($keys as $guid => $array)
        {
            $data['guid'] = $guid;
            $data['id'] = $mc->get_subkey($guid, 'id');
            $data['name'] = $mc->get_subkey($guid, 'name');

            if (($title = $mc->get_subkey($guid, 'official')))
            {
                $data['title'] = $title;
            }
            else
            {
                $data['title'] = $data['name'];
            }

            if (!$data['title'])
            {
                $data['title'] = midcom::get('i18n')->get_string('unknown', 'midcom.admin.user');
            }

            // Show the group
            if ($move)
            {
                // Prevent moving owner to any of its children
                $data['disabled'] = false;
                if (self::belongs_to($data['id'], $data['group']->id))
                {
                    $data['disabled'] = true;
                }

                midcom_show_style('midcom-admin-user-group-list-group-move');
            }
            else
            {
                midcom_show_style('midcom-admin-user-group-list-group');
            }
        }

        // Group footer
        midcom_show_style('midcom-admin-user-group-list-footer');
    }

    /**
     * Internal helper to check if the requested group belongs to the haystack
     *
     * @param int $id
     * @param int $owner
     */
    public static function belongs_to($id, $owner)
    {
        do
        {
            if ($id === $owner)
            {
                return true;
            }

            $mc = midcom_db_group::new_collector('id', $id);
            $mc->set_limit(1);
            $keys = $mc->get_values('owner');

            // Get the first array key
            foreach ($keys as $key)
            {
                if ($key === 0)
                {
                    return false;
                }

                $id = $key;
            }
        }
        while ($mc->count() > 0);

        return false;
    }
}
?>