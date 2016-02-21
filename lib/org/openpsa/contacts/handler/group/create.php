<?php
/**
 * @package org.openpsa.contacts
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.contacts group handler and viewer class.
 *
 * @package org.openpsa.contacts
 */
class org_openpsa_contacts_handler_group_create extends midcom_baseclasses_components_handler
implements midcom_helper_datamanager2_interfaces_create
{
    /**
     * What type of group are we dealing with, organization or group?
     *
     * @var string
     */
    private $_type;

    /**
     * The group we're working with
     *
     * @var org_openpsa_contacts_group_dba
     */
    private $_group = null;

    /**
     * The parent group, if any
     *
     * @var org_openpsa_contacts_group_dba
     */
    private $_parent_group = null;

    public function load_schemadb()
    {
        return midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_group'));
    }

    public function get_schema_name()
    {
        return $this->_type;
    }

    public function get_schema_defaults()
    {
        $defaults = array();
        if (!is_null($this->_parent_group))
        {
            if ($this->_type == 'organization')
            {
                // Set the default type to "department"
                $defaults['object_type'] = org_openpsa_contacts_group_dba::DEPARTMENT;
            }
            else
            {
                $defaults['owner'] = org_openpsa_contacts_interface::find_root_group()->id;
            }
            if ($this->_parent_group)
            {
                $defaults['owner'] = $this->_parent_group->id;
            }
        }
        return $defaults;
    }

    /**
     * This is what Datamanager calls to actually create a group
     */
    public function & dm2_create_callback(&$datamanager)
    {
        $this->_group = new org_openpsa_contacts_group_dba();

        if (   $this->_type == 'organization'
            && $this->_parent_group)
        {
            $this->_group->owner = (int) $this->_parent_group->id;
        }
        else
        {
            $root_group = org_openpsa_contacts_interface::find_root_group();
            $this->_group->owner = (int) $root_group->id;
        }
        $this->_group->name = time();

        if (! $this->_group->create())
        {
            debug_print_r('We operated on this object:', $this->_group);
            throw new midcom_error("Failed to create a new group. Error: " . midcom_connection::get_error_string());
        }

        return $this->_group;
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_create($handler_id, array $args, array &$data)
    {
        $this->_type = $args[0];

        $this->_parent_group = false;
        if (count($args) > 1)
        {
            // Get the parent organization
            $this->_parent_group = new org_openpsa_contacts_group_dba($args[1]);
            $this->_parent_group->require_do('midgard:create');
        }
        else
        {
            // This is a root level organization, require creation permissions under the component root group
            midcom::get()->auth->require_user_do('midgard:create', null, 'org_openpsa_contacts_group_dba');
        }

        $data['controller'] = $this->get_controller('create');
        switch ($data['controller']->process_form())
        {
            case 'save':
                // Index the organization
                $indexer = new org_openpsa_contacts_midcom_indexer($this->_topic);
                $indexer->index($data['controller']->datamanager);

                // Relocate to group view
                return new midcom_response_relocate("group/" . $this->_group->guid . "/");

            case 'cancel':
                if ($this->_parent_group)
                {
                    return new midcom_response_relocate("group/" . $this->_parent_group->guid . "/");
                }
                return new midcom_response_relocate('');
        }

        midcom::get()->head->set_pagetitle($this->_l10n->get("create organization"));

        org_openpsa_contacts_viewer::add_breadcrumb_path_for_group($this->_parent_group, $this);
        $this->add_breadcrumb("", sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get($this->_type)));
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_create($handler_id, array &$data)
    {
        midcom_show_style('show-group-create');
    }
}
