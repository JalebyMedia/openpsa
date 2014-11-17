<?php
/**
 * @package org.openpsa.sales
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Sales project list handler
 *
 * @package org.openpsa.sales
 */
class org_openpsa_sales_handler_list extends midcom_baseclasses_components_handler
{
    /**
     * The list of salesprojects.
     *
     * @var Array
     */
    private $_salesprojects = array();

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_list($handler_id, array $args, array &$data)
    {
        // Locate Contacts node for linking
        $siteconfig = org_openpsa_core_siteconfig::get_instance();
        $data['contacts_url'] = $siteconfig->get_node_full_url('org.openpsa.contacts');
        $data['reports_url'] = $siteconfig->get_node_full_url('org.openpsa.reports');

        $qb = org_openpsa_sales_salesproject_dba::new_query_builder();

        if ($handler_id == 'list_customer')
        {
            $qb = $this->_add_customer_constraint($args[0], $qb);
            $data['mode'] = 'customer';
            $data['list_title'] = sprintf($this->_l10n->get('salesprojects with %s'), $data['customer']->get_label());
            $this->add_breadcrumb("", $data['list_title']);
        }
        else
        {
            $data['mode'] = $this->get_list_mode($args);
            $qb = $this->_add_state_constraint($data['mode'], $qb);
            $data['list_title'] = $this->_l10n->get('salesprojects ' . $data['mode']);
            $this->set_active_leaf($this->_topic->id . ':' . $data['mode']);
        }

        $this->_salesprojects = $qb->execute();

        foreach ($this->_salesprojects as $salesproject)
        {
            // Populate previous/next actions in the project
            $salesproject->get_actions();
        }
        // TODO: Filtering

        $data['grid'] = new org_openpsa_widgets_grid($data['mode'] . '_salesprojects_grid', 'local');
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.core/table2csv.js');

        $this->add_toolbar_buttons();
    }

    private function get_list_mode(array $args)
    {
        $person = midcom::get()->auth->user->get_storage();
        $mode = $person->get_parameter($this->_component, 'list_mode');
        if (!empty($args[0]))
        {
            if ($mode !== $args[0])
            {
                $person->set_parameter($this->_component, 'list_mode', $args[0]);
            }
            return $args[0];
        }
        if (!empty($mode))
        {
            return $mode;
        }
        return 'active';
    }

    private function add_toolbar_buttons()
    {
        $create_url = 'salesproject/new/';

        if (!empty($this->_request_data['customer']))
        {
            $create_url .= $this->_request_data['customer']->guid . '/';

            if ($this->_request_data['contacts_url'])
            {
                $url_prefix = $this->_request_data['contacts_url'] . (is_a($this->_request_data['customer'], 'org_openpsa_contacts_group_dba') ? 'group' : 'person') . "/";
                $this->_view_toolbar->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => $url_prefix . $this->_request_data['customer']->guid . '/',
                        MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('go to customer'),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/jump-to.png',
                    )
                );
            }
        }
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => $create_url,
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create salesproject'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_people.png',
                MIDCOM_TOOLBAR_ENABLED => midcom::get()->auth->can_user_do('midgard:create', null, 'org_openpsa_sales_salesproject_dba'),
            )
        );
    }

    private function _add_state_constraint($state, midcom_core_query $qb)
    {
        $code = 'org_openpsa_sales_salesproject_dba::STATE_' . strtoupper($state);
        if (!defined($code))
        {
            throw new midcom_error('Unknown list type ' . $state);
        }

        $qb->add_constraint('state', '=', constant($code));
        return $qb;
    }

    private function _add_customer_constraint($guid, midcom_core_query $qb)
    {
        try
        {
            $this->_request_data['customer'] = new org_openpsa_contacts_group_dba($guid);
            $qb->add_constraint('customer', '=', $this->_request_data['customer']->id);
        }
        catch (midcom_error $e)
        {
            $this->_request_data['customer'] = new org_openpsa_contacts_person_dba($guid);
            $qb->add_constraint('customerContact', '=', $this->_request_data['customer']->id);
        }
        return $qb;
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_list($handler_id, array &$data)
    {
        $data['salesprojects'] = $this->_salesprojects;

        midcom_show_style('show-salesproject-grid');
    }
}
