<?php
/**
 * @package org.openpsa.invoices
 * @author Nemein Oy, http://www.nemein.com/
 * @copyright Nemein Oy, http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * MidCOM wrapped base class, keep logic here
 *
 * @package org.openpsa.invoices
 */
class org_openpsa_invoices_invoice_dba extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'org_openpsa_invoice';

    public $autodelete_dependents = array
    (
        'org_openpsa_invoices_invoice_item_dba' => 'invoice'
    );

    private $_billing_data = false;

    function get_status()
    {
        if ($this->sent == 0)
        {
            return 'unsent';
        }
        else if ($this->paid > 0)
        {
            return 'paid';
        }
        else if ($this->due < time())
        {
            return 'overdue';
        }
        return 'open';
    }

    function get_icon()
    {
        return 'printer.png';
    }

    public static function get_by_number($number)
    {
        $qb = org_openpsa_invoices_invoice_dba::new_query_builder();
        $qb->add_constraint('number', '=', $number);
        $result = $qb->execute();
        if (count($result) == 1)
        {
            return $result[0];
        }
        return false;
    }

    /**
     * Generate "Send invoice" task
     */
    function generate_invoicing_task($invoicer)
    {
        try
        {
            $invoice_sender = new midcom_db_person($invoicer);
        }
        catch (midcom_error $e)
        {
            return;
        }

        $config = midcom_baseclasses_components_configuration::get('org.openpsa.invoices', 'config');
        $task = new org_openpsa_projects_task_dba();
        $task->get_members();
        $task->resources[$invoice_sender->id] = true;
        $task->manager = midcom_connection::get_user();
        // TODO: Connect the customer as the contact?
        $task->title = sprintf(midcom::get('i18n')->get_string('send invoice %s', 'org.openpsa.invoices'), sprintf($config->get('invoice_number_format'), sprintf($config->get('invoice_number_format'), $this->number)));
        // TODO: Store link to invoice into description
        $task->end = time() + 24 * 3600;
        if ($task->create())
        {
            org_openpsa_relatedto_plugin::create($task, 'org.openpsa.projects', $this, 'org.openpsa.invoices');
            midcom::get('uimessages')->add(midcom::get('i18n')->get_string('org.openpsa.invoices', 'org.openpsa.invoices'), sprintf(midcom::get('i18n')->get_string('created "%s" task to %s', 'org.openpsa.invoices'), $task->title, $invoice_sender->name), 'ok');
        }
    }

    /**
     * Human-readable label for cases like Asgard navigation
     */
    function get_label()
    {
        $config = midcom_baseclasses_components_configuration::get('org.openpsa.invoices', 'config');
        return sprintf($config->get('invoice_number_format'), $this->number);
    }

    /**
     * Label property (for Asgard chooser and the likes)
     */
    function get_label_property()
    {
        return 'number';
    }

    public function _on_creating()
    {
        $this->_pre_write_operations();
        return true;
    }

    public function _on_updating()
    {
        $this->_pre_write_operations();
        return true;
    }

    private function _pre_write_operations()
    {
        if ($this->sent > 0)
        {
            $time = time();
            if (!$this->date)
            {
                $this->date = $time;
            }
            if (!$this->deliverydate)
            {
                $this->deliverydate = $time;
            }
            if ($this->due == 0)
            {
                $this->due = ($this->get_default('due') * 3600 * 24) + $this->date;
            }
        }
    }

    /**
     * Deletes all invoice_hours related to the invoice
     */
    public function _on_deleting()
    {
        if (! midcom::get('auth')->request_sudo('org.openpsa.invoices'))
        {
            debug_add('Failed to get SUDO privileges, skipping invoice hour deletion silently.', MIDCOM_LOG_ERROR);
            return false;
        }

        // Delete invoice_hours
        $tasks_to_update = array();

        $qb = org_openpsa_projects_hour_report_dba::new_query_builder();
        $qb->add_constraint('invoice', '=', $this->id);
        $hours = $qb->execute();
        foreach ($hours as $hour)
        {
            $hour->invoice = 0;
            $hour->_skip_parent_refresh = true;
            $tasks_to_update[] = $hour->task;
            if (!$hour->update())
            {
                debug_add("Failed to remove invoice hour record {$hour->id}, last Midgard error was: " . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
            }
        }

        foreach (array_unique($tasks_to_update) as $id)
        {
            try
            {
                $task = new org_openpsa_projects_task_dba($id);
                $task->update_cache();
            }
            catch (midcom_error $e){}
        }

        midcom::get('auth')->drop_sudo();
        return parent::_on_deleting();
    }

    /**
     * By default all authenticated users should be able to do
     * whatever they wish with relatedto objects, later we can add
     * restrictions on object level as necessary.
     */
    function get_class_magic_default_privileges()
    {
        $privileges = parent::get_class_magic_default_privileges();
        $privileges['ANONYMOUS']['midgard:read'] = MIDCOM_PRIVILEGE_DENY;
        return $privileges;
    }

    /**
     * function to get the default value for invoice
     * @param string $attribute
     */
    public function get_default($attribute)
    {
        $billing_data = $this->get_billing_data();
        return $billing_data->{$attribute};
    }

    /**
     * an invoice is cancelable if it is no cancelation invoice
     * itself and got no related cancelation invoice
     *
     * @return boolean
     */
    public function is_cancelable()
    {
        return (!$this->cancelationInvoice && !$this->get_canceled_invoice());
    }

    /**
     * returns the invoice that got canceled through this invoice, if any
     *
     * @return org_openpsa_invoices_invoice_dba|false
     */
    public function get_canceled_invoice()
    {
        $qb = org_openpsa_invoices_invoice_dba::new_query_builder();
        $qb->add_constraint('cancelationInvoice', '=', $this->id);
        $results = $qb->execute();

        if (count($results) == 0)
        {
            return false;
        }
        return $results[0];
    }

    /**
     * Helper function to create & recalculate existing invoice_items by tasks
     *
     * @param array $tasks array containing the task id's to recalculate for - if empty all tasks will be recalculated
     */
    public function _recalculate_invoice_items($tasks = array(), $skip_invoice_update = false)
    {
        $result_items = array();
        $result_tasks = array();

        //get hour_reports for this invoice - mc ?
        $qb_hour_reports = org_openpsa_projects_hour_report_dba::new_query_builder();
        $qb_hour_reports->add_constraint('invoice', '=', $this->id);
        if (!empty($tasks))
        {
            $qb_hour_reports->add_constraint('task', 'IN', $tasks);
            //if there is a task passed it must be calculated even
            //if it doesn't have associated hour_reports
            foreach ($tasks as $task_id)
            {
                $result_tasks[$task_id] = 0;
            }
        }
        $hour_reports = $qb_hour_reports->execute();

        // sums up the hours of hour_reports for each task
        foreach ($hour_reports as $hour_report)
        {
            if (!array_key_exists($hour_report->task, $result_tasks))
            {
                $result_tasks[$hour_report->task] = 0;
            }

            //only add invoiceable hour_reports
            if ($hour_report->invoiceable)
            {
                $result_tasks[$hour_report->task] += $hour_report->hours;
            }
        }

        foreach ($result_tasks as $task_id => $hours)
        {
            $invoice_item = $this->_probe_invoice_item_for_task($task_id);

            //get deliverable for this task
            $mc_task_agreement = new midgard_collector('org_openpsa_task', 'id', $task_id);
            $mc_task_agreement->set_key_property('id');
            $mc_task_agreement->add_value_property('title');
            $mc_task_agreement->add_value_property('agreement');
            $mc_task_agreement->add_constraint('agreement', '<>', 0);
            $mc_task_agreement->execute();

            $mc_task_key = $mc_task_agreement->list_keys();
            $deliverable = null;
            foreach ($mc_task_key as $key => $empty)
            {
                try
                {
                    $deliverable = new org_openpsa_sales_salesproject_deliverable_dba((int)$mc_task_agreement->get_subkey($key, 'agreement'));
                    $invoice_item->pricePerUnit = $deliverable->pricePerUnit;
                    $invoice_item->deliverable = $deliverable->id;
                    //calculate price
                    if (   $deliverable->invoiceByActualUnits
                        || $deliverable->plannedUnits == 0)
                    {
                        $invoice_item->units = $hours;
                    }
                    else
                    {
                        $invoice_item->units = $deliverable->plannedUnits;
                    }
                }
                catch (midcom_error $e)
                {
                    $e->log();
                    $invoice_item->units = $hours;
                }
            }

            if ($invoice_item->description == '')
            {
                $invoice_item->description = $mc_task_agreement->get_subkey($task_id, 'title');
            }

            $invoice_item->skip_invoice_update = $skip_invoice_update;

            $invoice_item->update();
            $result_items[] = $invoice_item;
        }
        return $result_items;
    }

    /**
     * Helper function to get corresponding invoice_items indexed by GUID
     */
    function get_invoice_items()
    {
        $qb = org_openpsa_invoices_invoice_item_dba::new_query_builder();
        $qb->add_constraint('invoice', '=', $this->id);
        $qb->add_order('position', 'ASC');
        $result = $qb->execute();

        $items = array();
        foreach ($result as $item)
        {
            $items[$item->guid] = $item;
        }
        return $items;
    }

    /**
     * helper function to get the billing data for given contact if any
     * @param string $dba_class
     * @param mixed $contact_id
     */
    private function _get_billing_data($dba_class, $contact_id)
    {
        if ($contact_id == 0)
        {
            return false;
        }

        try
        {
            $contact = call_user_func(array($dba_class, 'get_cached'), $contact_id);
            $qb = org_openpsa_invoices_billing_data_dba::new_query_builder();
            $qb->add_constraint('linkGuid', '=', $contact->guid);
            $billing_data = $qb->execute();
            if (count($billing_data) == 0)
            {
                return false;
            }

            // call set_address so the billing_data contains address of the linked contact
            // if the property useContactAddress is set
            $billing_data[0]->set_address();
            return $billing_data[0];
        }
        catch (midcom_error $e)
        {
            $e->log();
        }
    }

    /**
     * helper function to get the billing data for the invoice
     */
    public function get_billing_data()
    {
        // check if we got the billing data cached already
        if ($this->_billing_data)
        {
            return $this->_billing_data;
        }

        // check if there is a customer set with invoice_data
        $bd = $this->_get_billing_data('org_openpsa_contacts_group_dba', $this->customer);
        if ($bd)
        {
            $this->_billing_data = $bd;
            return $bd;
        }
        // check if the customerContact is set and has invoice_data
        $bd = $this->_get_billing_data('org_openpsa_contacts_person_dba', $this->customerContact);
        if ($bd)
        {
            $this->_billing_data = $bd;
            return $bd;
        }

        // set the default-values for vat and due from config
        $bd = new org_openpsa_invoices_billing_data_dba();
        $due = midcom_baseclasses_components_configuration::get('org.openpsa.invoices', 'config')->get('default_due_days');
        $vat = explode(',', midcom_baseclasses_components_configuration::get('org.openpsa.invoices', 'config')->get('vat_percentages'));

        $bd->vat = (int) $vat[0];
        $bd->due = $due;

        $this->_billing_data = $bd;
        return $bd;
    }

    public function get_customer()
    {
        try
        {
            $customer = org_openpsa_contacts_group_dba::get_cached($this->customer);
        }
        catch (midcom_error $e)
        {
            try
            {
                $customer = org_openpsa_contacts_person_dba::get_cached($this->customerContact);
            }
            catch (midcom_error $e)
            {
                $customer = null;
                $e->log();
            }
        }
        return $customer;
    }

    /**
     * Helper function to get invoice_item for the passed task id, if there is no item
     * it will return a new created one
     */
    private function _probe_invoice_item_for_task($task_id)
    {
        //check if there is already an invoice_item for this task
        $qb_invoice_item = org_openpsa_invoices_invoice_item_dba::new_query_builder();
        $qb_invoice_item->add_constraint('invoice', '=', $this->id);
        $qb_invoice_item->add_constraint('task', '=', $task_id);

        $invoice_items = $qb_invoice_item->execute();
        if (count($invoice_items) == 1)
        {
            $invoice_item = $invoice_items[0];
        }
        else if (count($invoice_items) > 1)
        {
            debug_add('More than one item found for task #' . $task_id . ', only returning the first', MIDCOM_LOG_INFO);
            $invoice_item = $invoice_items[0];
        }
        else
        {
            $invoice_item = new org_openpsa_invoices_invoice_item_dba();
            $invoice_item->task = $task_id;
            $invoice_item->invoice = $this->id;
            $invoice_item->create();
        }

        return $invoice_item;
    }

    public function generate_invoice_number()
    {
        $client_class = midcom_baseclasses_components_configuration::get('org.openpsa.sales', 'config')->get('calculator');
        $calculator = new $client_class;
        return $calculator->generate_invoice_number();
    }
}
?>