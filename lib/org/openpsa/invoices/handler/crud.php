<?php
/**
 * @package org.openpsa.invoices
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Invoice create/read/update/delete handler
 *
 * @package org.openpsa.invoices
 */
class org_openpsa_invoices_handler_crud extends midcom_baseclasses_components_handler_crud
{
    protected $_dba_class = 'org_openpsa_invoices_invoice_dba';

    public function _load_schemadb()
    {
        $this->_schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb'));
        if (   $this->_mode == 'create'
            && count($this->_master->_handler['args']) == 1)
        {
            // We're creating invoice for chosen customer
            try
            {
                $this->_request_data['customer'] = new org_openpsa_contacts_group_dba($this->_master->_handler['args'][0]);
            }
            catch (midcom_error $e)
            {
                $this->_request_data['customer'] = new org_openpsa_contacts_person_dba($this->_master->_handler['args'][0]);
            }
        }
        $this->_modify_schema();
    }

    /**
     * Helper function to alter the schema based on the current operation
     */
    private function _modify_schema()
    {
        $fields =& $this->_schemadb['default']->fields;
        // Fill VAT select
        $vat_array = explode(',', $this->_config->get('vat_percentages'));
        if (!empty($vat_array))
        {
            $vat_values = array();
            foreach ($vat_array as $vat)
            {
                $vat_values[$vat] = "{$vat}%";
            }
            $fields['vat']['type_config']['options'] = $vat_values;
        }

        if ($this->_config->get('invoice_pdfbuilder_class'))
        {
            $fields['pdf_file']['hidden'] = false;
        }
        $fields['due']['hidden'] = empty($this->_object->sent);
        $fields['sent']['hidden'] = empty($this->_object->sent);
        $fields['paid']['hidden'] = empty($this->_object->paid);

        if (!empty($this->_object->customerContact))
        {
            $this->_populate_schema_customers_for_contact($this->_object->customerContact);
        }
        else if (array_key_exists('customer', $this->_request_data))
        {
            if (is_a($this->_request_data['customer'], 'org_openpsa_contacts_group_dba'))
            {
                $this->_populate_schema_contacts_for_customer($this->_request_data['customer']);
            }
            else
            {
                $this->_populate_schema_customers_for_contact($this->_request_data['customer']->id);
            }
        }
        else if (!empty($this->_object->customer))
        {
            try
            {
                $this->_request_data['customer'] = org_openpsa_contacts_group_dba::get_cached($this->_object->customer);
                $this->_populate_schema_contacts_for_customer($this->_request_data['customer']);
            }
            catch (midcom_error $e)
            {
                $fields['customer']['hidden'] = true;
                $e->log();
            }
        }
        else
        {
            // We don't know company, present customer contact as chooser and hide customer field
            $fields['customer']['hidden'] = true;
        }
    }

    /**
     * List customer contact's groups
     */
    private function _populate_schema_customers_for_contact($contact_id)
    {
        $fields =& $this->_schemadb['default']->fields;
        $organizations = array(0 => '');
        $member_mc = org_openpsa_contacts_member_dba::new_collector('uid', $contact_id);
        $member_mc->add_constraint('gid.orgOpenpsaObtype', '>', org_openpsa_contacts_group_dba::MYCONTACTS);
        $groups = $member_mc->get_values('gid');
        if (!empty($groups))
        {
            $qb = org_openpsa_contacts_group_dba::new_query_builder();
            $qb->add_constraint('id', 'IN', $groups);
            $qb->add_order('official');
            $qb->add_order('name');
            foreach ($qb->execute() as $group)
            {
                $organizations[$group->id] = $group->official;
            }
        }

        //Fill the customer field to DM
        $fields['customer']['type_config']['options'] = $organizations;
    }

    private function _populate_schema_contacts_for_customer($customer)
    {
        $fields =& $this->_schemadb['default']->fields;
        // We know the customer company, present contact as a select widget
        $persons_array = array();
        $member_mc = midcom_db_member::new_collector('gid', $customer->id);
        $members = $member_mc->get_values('uid');
        foreach ($members as $member)
        {
            try
            {
                $person = org_openpsa_contacts_person_dba::get_cached($member);
                $persons_array[$person->id] = $person->rname;
            }
            catch (midcom_error $e){}
        }
        asort($persons_array);
        $fields['customerContact']['widget'] = 'select';
        $fields['customerContact']['type_config']['options'] = $persons_array;

        // And display the organization too
        $organization_array = Array();
        $organization_array[$customer->id] = $customer->official;

        $fields['customer']['widget'] = 'select';
        $fields['customer']['type_config']['options'] = $organization_array;
    }

    /**
     * This is what Datamanager calls to actually create an invoice
     */
    function & dm2_create_callback(&$datamanager)
    {
        $this->_object = new org_openpsa_invoices_invoice_dba();

        if (! $this->_object->create())
        {
            debug_print_r('We operated on this object:', $this->_object);
            throw new midcom_error("Failed to create a new invoice. Error: " . midcom_connection::get_error_string());
        }

        return $this->_object;
    }

    function _load_defaults()
    {
        $this->_defaults['date'] = time();
        $this->_defaults['deliverydate'] = time();

        // Set default due date and copy customer remarks to invoice description
        if (array_key_exists('customer', $this->_request_data))
        {
            $dummy = new org_openpsa_invoices_invoice_dba();
            $dummy->customer = $this->_request_data['customer']->id;
            $this->_defaults['vat'] = $dummy->get_default('vat');

            if (is_a($this->_request_data['customer'], 'org_openpsa_contacts_person_dba'))
            {
                $this->_defaults['customerContact'] = $this->_request_data['customer']->id;
            }

            // we got a customer, set description default
            $this->_defaults['description'] = $dummy->get_default('remarks');
        }
        else
        {
            $due_date = ($this->_config->get('default_due_days') * 3600 * 24) + time();
            $this->_defaults['due'] = $due_date;
        }

        // Generate invoice number
        $client_class = midcom_baseclasses_components_configuration::get('org.openpsa.sales', 'config')->get('calculator');
        $calculator = new $client_class;
        $this->_defaults['number'] = $calculator->generate_invoice_number();

        $this->_defaults['owner'] = midcom_connection::get_user();
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_callback($handler_id, array $args, array &$data)
    {
        if ($this->_mode == 'read')
        {
            $qb = org_openpsa_projects_hour_report_dba::new_query_builder();
            $qb->add_constraint('invoice', '=', $this->_object->id);
            $this->_request_data['reports'] = $qb->execute();

            org_openpsa_widgets_grid::add_head_elements();

            $this->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.core/list.css");
            midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . "/" . $this->_component . "/invoices.js");
        }
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_delete($handler_id, array $args, array &$data)
    {
        $this->_load_object($handler_id, $args, $data);
        $workflow = new midcom\workflow\delete($this->_object);
        if ($workflow->run())
        {
            $indexer = midcom::get()->indexer;
            $indexer->delete($this->_object->guid);
            return new midcom_response_relocate('');
        }

        return new midcom_response_relocate("invoice/{$this->_object->guid}/");
    }

    function _populate_toolbar($handler_id)
    {
        if ($this->_mode == 'read')
        {
            $this->_populate_read_toolbar($handler_id);
        }
        else
        {
            // Add toolbar items
            org_openpsa_helpers::dm2_savecancel($this);
        }
    }

    private function _populate_read_toolbar($handler_id)
    {
        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "invoice/edit/{$this->_object->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('edit'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                MIDCOM_TOOLBAR_ENABLED => $this->_object->can_do('midgard:update'),
                MIDCOM_TOOLBAR_ACCESSKEY => 'e',
            )
        );

        if ($this->_object->can_do('midgard:delete'))
        {
            $workflow = new midcom\workflow\delete($this->_object);
            $workflow->add_button($this->_view_toolbar, "invoice/delete/{$this->_object->guid}/");
        }

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => "invoice/items/{$this->_object->guid}/",
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('edit invoice items'),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                MIDCOM_TOOLBAR_ENABLED => $this->_object->can_do('midgard:update'),
            )
        );

        if (!$this->_object->sent)
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "invoice/process/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('mark sent'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_mail-reply.png',
                    MIDCOM_TOOLBAR_POST => true,
                    MIDCOM_TOOLBAR_POST_HIDDENARGS => array
                    (
                        'action' => 'mark_sent',
                        'id' => $this->_object->id,
                        'relocate' => true
                    ),
                    MIDCOM_TOOLBAR_ENABLED => $this->_object->can_do('midgard:update'),
                )
            );
        }
        else if (!$this->_object->paid)
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "invoice/process/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('mark paid'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/ok.png',
                    MIDCOM_TOOLBAR_POST => true,
                    MIDCOM_TOOLBAR_POST_HIDDENARGS => array
                    (
                        'action' => 'mark_paid',
                        'id' => $this->_object->id,
                        'relocate' => true
                    ),
                    MIDCOM_TOOLBAR_ENABLED => $this->_object->can_do('midgard:update'),
                )
            );
        }

        if (   !$this->_object->paid
            && $this->_config->get('invoice_pdfbuilder_class'))
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "invoice/pdf/{$this->_object->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create pdf'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/printer.png',
                )
            );
            // sending per email enabled in billing data?
            $billing_data = $this->_object->get_billing_data();
            if (intval($billing_data->sendingoption) == 2)
            {
                $this->_view_toolbar->add_item
                (
                    array
                    (
                        MIDCOM_TOOLBAR_URL => "invoice/process/",
                        MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('mark sent_per_mail'),
                        MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_mail-reply.png',
                        MIDCOM_TOOLBAR_POST => true,
                        MIDCOM_TOOLBAR_POST_HIDDENARGS => array
                        (
                            'action' => 'send_by_mail',
                            'id' => $this->_object->id,
                            'relocate' => true
                        ),
                        MIDCOM_TOOLBAR_ENABLED => $this->_object->can_do('midgard:update'),
                    )
                );
            }
        }

        if ($this->_object->is_cancelable())
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "invoice/process/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('create cancelation for invoice'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/cancel.png',
                    MIDCOM_TOOLBAR_POST => true,
                    MIDCOM_TOOLBAR_POST_HIDDENARGS => array
                    (
                        'action' => 'create_cancelation',
                        'id' => $this->_object->id,
                        'relocate' => true
                    ),
                    MIDCOM_TOOLBAR_ENABLED => $this->_object->can_do('midgard:update'),
                )
            );
        }
        org_openpsa_relatedto_plugin::add_button($this->_view_toolbar, $this->_object->guid);

        $this->_master->add_next_previous($this->_object, $this->_view_toolbar, 'invoice/');
    }

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     *
     * @param string $handler_id The current handler
     */
    function _update_breadcrumb($handler_id)
    {
        $customer = false;
        if ($this->_object)
        {
            $customer = $this->_object->get_customer();
        }
        if (   !$customer
            && array_key_exists('customer', $this->_request_data))
        {
            $customer = $this->_request_data['customer'];
        }

        if ($customer)
        {
            $this->add_breadcrumb("list/customer/all/{$customer->guid}/", $customer->get_label());
        }

        if ($this->_mode != 'create')
        {
            $this->add_breadcrumb("invoice/" . $this->_object->guid . "/", $this->_l10n->get('invoice') . ' ' . $this->_object->get_label());
        }

        if ($this->_mode != 'read')
        {
            $action = $this->_mode;
            if ($action == 'update')
            {
                $action = 'edit';
            }
            $this->add_breadcrumb("", sprintf($this->_l10n_midcom->get($action . ' %s'), $this->_l10n->get('invoice')));
        }
    }

    /**
     * Method for updating title for current object and handler
     *
     * @param mixed $handler_id The ID of the handler.
     */
    public function _update_title($handler_id)
    {
        switch ($this->_mode)
        {
            case 'create':
                $view_title = $this->_l10n->get('create invoice');
                break;
            case 'read':
                $view_title = $this->_l10n->get('invoice') . ' ' . $this->_object->get_label();
                break;
            case 'update':
                $view_title = sprintf($this->_l10n_midcom->get('edit %s'), $this->_l10n->get('invoice') . ' ' . $this->_object->get_label());
                break;
        }

        midcom::get()->head->set_pagetitle($view_title);
    }

    function _prepare_request_data()
    {
        $this->_request_data['object'] = $this->_object;
        $this->_request_data['datamanager'] = $this->_datamanager;
        $this->_request_data['controller'] = $this->_controller;

        if (!empty($this->_object))
        {
            $this->_request_data['invoice_items'] = $this->_object->get_invoice_items();
        }
    }

    /**
     * Method for adding or updating the invoice to the MidCOM indexer service.
     *
     * @param $dm Datamanager2 instance containing the object
     */
    public function _index_object(&$dm)
    {
        $indexer = new org_openpsa_invoices_midcom_indexer($this->_topic);
        return $indexer->index($dm);
    }
}
