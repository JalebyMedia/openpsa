<?php
/**
 * @package org.openpsa.projects
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * MidCOM wrapped access to the MgdSchema class, keep logic here
 *
 * @package org.openpsa.projects
 */
class org_openpsa_projects_task_dba extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'org_openpsa_task';

    public $autodelete_dependents = array
    (
        'org_openpsa_projects_task_status_dba' => 'task',
        'org_openpsa_projects_task_resource_dba' => 'task',
    );

    var $contacts = null; //Shorthand access for contact members
    var $resources = null; // --''--
    private $_locale_backup = '';
    public $_skip_acl_refresh = false;
    public $_skip_parent_refresh = false;
    private $_status = null;

    static function new_query_builder()
    {
        return $_MIDCOM->dbfactory->new_query_builder(__CLASS__);
    }

    static function new_collector($domain, $value)
    {
        return $_MIDCOM->dbfactory->new_collector(__CLASS__, $domain, $value);
    }

    static function &get_cached($src)
    {
        return $_MIDCOM->dbfactory->get_cached(__CLASS__, $src);
    }

    /**
     * Deny midgard:read by default
     */
    function get_class_magic_default_privileges()
    {
        $privileges = parent::get_class_magic_default_privileges();
        $privileges['EVERYONE']['midgard:read'] = MIDCOM_PRIVILEGE_DENY;
        return $privileges;
    }

    public function _on_creating()
    {
        $this->_locale_set();
        $this->orgOpenpsaObtype = ORG_OPENPSA_OBTYPE_TASK;
        if (!$this->manager)
        {
            $this->manager = midcom_connection::get_user();
        }
        return $this->_prepare_save();
    }

    public function _on_created()
    {
        $this->_locale_restore();
    }

    public function _on_loaded()
    {
        if ($this->title == "")
        {
            $this->title = "Task #{$this->id}";
        }

        if (!$this->status)
        {
            //Default to proposed if no status is set
            $this->status = org_openpsa_projects_task_status_dba::PROPOSED;
        }
    }

    public function refresh()
    {
        $this->contacts = null;
        $this->resources = null;
        $this->_status = null;
        parent::refresh();
    }

    public function __get($property)
    {
        if ($property == 'status_type')
        {
            return org_openpsa_projects_workflow::get_status_type($this->status);
        }
        else if ($property == 'status_comment'
                 || $property == 'status_time')
        {
            if(is_null($this->_status))
            {
                $this->_status = $this->_get_status();
            }
            return $this->_status[$property];
        }
        return parent::__get($property);
    }

    public function __set($name, $val)
    {
        parent::__set($name, $val);
    }

    public function _on_updating()
    {
        $this->_locale_set();
        if ($this->_prepare_save())
        {
            return true;
        }
        //If we return false here then _on_updated() never gets called
        $this->_locale_restore();
        return false;
    }

    public function _on_updated()
    {
        // Sync the object's ACL properties into MidCOM ACL system
        if (   !$this->_skip_acl_refresh)
        {
            if ($this->orgOpenpsaAccesstype
                && $this->orgOpenpsaOwnerWg)
            {
                debug_add("Synchronizing task ACLs to MidCOM");
                $sync = new org_openpsa_core_acl_synchronizer();
                $sync->write_acls($this, $this->orgOpenpsaOwnerWg, $this->orgOpenpsaAccesstype);
            }

            //Ensure manager can do stuff
            if ($this->manager)
            {
                $manager_person = self::pid_to_obj($this->manager);
                $this->set_privilege('midgard:read', $manager_person->id, MIDCOM_PRIVILEGE_ALLOW);
                $this->set_privilege('midgard:create', $manager_person->id, MIDCOM_PRIVILEGE_ALLOW);
                $this->set_privilege('midgard:delete', $manager_person->id, MIDCOM_PRIVILEGE_ALLOW);
                $this->set_privilege('midgard:update', $manager_person->id, MIDCOM_PRIVILEGE_ALLOW);
            }
        }

        $this->_update_parent();

        $this->_locale_restore();
    }

    public function _on_deleting()
    {
        $this->update_cache(false);
        if ($this->reportedHours > 0)
        {
            $_MIDCOM->uimessages->add($_MIDCOM->i18n->get_string('org.openpsa.projects', 'org.openpsa.projects'), $_MIDCOM->i18n->get_string('task deletion now allowed because of hour reports', 'org.openpsa.projects'), 'warning');
            return false;
        }

        return parent::_on_deleting();
    }

    public function get_parent()
    {
        try
        {
            $project = new org_openpsa_projects_project($this->project);
            return $project;
        }
        catch (midcom_error $e)
        {
            $e->log();
            return null;
        }
    }

    /**
     * Generate a user-readable label for the task using the task/project hierarchy
     */
    function get_label()
    {
        $label = '';
        $label_elements = array();
        $task = $this;
        while (   !is_null($task)
               && $task = $task->get_parent())
        {
            if (isset($task->title))
            {
                $label_elements[] = $task->title;
            }
        }

        $label_elements = array_reverse($label_elements);
        foreach ($label_elements as $element)
        {
            $label .= "{$element} / ";
        }
        $label .= $this->title;

        return trim($label);
    }

    function get_icon()
    {
        return org_openpsa_projects_workflow::get_status_type_icon($this->status_type);
    }

    /**
     * Populates contacts as resources lists
     */
    function get_members()
    {
        if (!$this->id)
        {
            return false;
        }

        if (!is_array($this->contacts))
        {
            $this->contacts = array();
        }
        if (!is_array($this->resources))
        {
            $this->resources = array();
        }

        $mc = org_openpsa_projects_task_resource_dba::new_collector('task', $this->id);
        $mc->add_value_property('orgOpenpsaObtype');
        $mc->add_value_property('person');
        $mc->add_constraint('orgOpenpsaObtype', '<>', ORG_OPENPSA_OBTYPE_PROJECTPROSPECT);
        $mc->execute();
        $ret = $mc->list_keys();

        if (   is_array($ret)
            && count($ret) > 0)
        {
            foreach ($ret as $guid => $empty)
            {
                switch ($mc->get_subkey($guid, 'orgOpenpsaObtype'))
                {
                    case ORG_OPENPSA_OBTYPE_PROJECTCONTACT:
                        $varName = 'contacts';
                        break;
                    default:
                        //fall-trough intentional
                    case ORG_OPENPSA_OBTYPE_PROJECTRESOURCE:
                        $varName = 'resources';
                        break;
                }
                $this->{$varName}[$mc->get_subkey($guid, 'person')] = true;
            }
        }
        return true;
    }

    /**
     * Adds new contacts or resources
     *
     * @param string $property Where should thy be added
     * @param array $ids The IDs of the contacts to add
     */
    function add_members($property, $ids)
    {
        if (   !is_array($ids)
            || empty ($ids))
        {
            return;
        }
        foreach ($ids as $id)
        {
            $resource = new org_openpsa_projects_task_resource_dba();
            switch ($property)
            {
                case 'contacts':
                    $resource->orgOpenpsaObtype = ORG_OPENPSA_OBTYPE_PROJECTCONTACT;
                    break;
                case 'resources':
                    $resource->orgOpenpsaObtype = ORG_OPENPSA_OBTYPE_PROJECTRESOURCE;
                    break;
                default:
                    continue;
            }
            $resource->task = $this->id;
            $resource->person = (int) $id;
            if ($resource->create())
            {
                $this->{$property}[$id] = true;
            }
        }
    }

    private function _prepare_save()
    {
        //Make sure we have start
        if (!$this->start)
        {
            $this->start = time();
        }
        //Make sure we have end
        if (!$this->end || $this->end == -1)
        {
            $this->end = time();
        }

        //Reset start and end to start/end of day
        $this->start = mktime(  0,
                                0,
                                0,
                                date('n', $this->start),
                                date('j', $this->start),
                                date('Y', $this->start));
        $this->end = mktime(23,
                            59,
                            59,
                            date('n', $this->end),
                            date('j', $this->end),
                            date('Y', $this->end));

        if ($this->start > $this->end)
        {
            debug_add("start ({$this->start}) is greater than end ({$this->end}), aborting", MIDCOM_LOG_ERROR);
            return false;
        }

        if ($this->orgOpenpsaWgtype == ORG_OPENPSA_OBTYPE_TASK)
        {
            $this->orgOpenpsaWgtype = ORG_OPENPSA_WGTYPE_NONE;
        }

        if ($this->agreement)
        {
            // Get customer company into cache from agreement's sales project
            try
            {
                $agreement = org_openpsa_sales_salesproject_deliverable_dba::get_cached($this->agreement);
                $this->hoursInvoiceableDefault = true;
                if (!$this->customer)
                {
                    $salesproject = org_openpsa_sales_salesproject_dba::get_cached($agreement->salesproject);
                    $this->customer = $salesproject->customer;
                }
            }
            catch (midcom_error $e){}
        }
        else
        {
            // No agreement, we can't be invoiceable
            $this->hoursInvoiceableDefault = false;
        }

        // Update hour caches
        $this->update_cache(false);

        return true;
    }

    private function _locale_set()
    {
        $this->_locale_backup = setlocale(LC_NUMERIC, '0');
        setlocale(LC_NUMERIC, 'C');
    }

    private function _locale_restore()
    {
        setlocale(LC_NUMERIC, $this->_locale_backup);
    }

    /**
     * Update hour report caches
     */
    function update_cache($update = true)
    {
        if (!$this->id)
        {
            return false;
        }

        debug_add("updating hour caches");

        $hours = $this->list_hours();
        $stat = true;

        $this->reportedHours = $hours['reported'];
        $this->approvedHours = $hours['approved'];
        $this->invoicedHours = $hours['invoiced'];
        $this->invoiceableHours = $hours['invoiceable'];

        try
        {
            $agreement = new org_openpsa_sales_salesproject_deliverable_dba($this->agreement);
            $agreement->update_units($this->id, $hours);
        }
        catch (midcom_error $e){}

        if ($update)
        {
            $this->_use_rcs = false;
            $this->_use_activitystream = false;
            $this->_skip_acl_refresh = true;
            $this->_skip_parent_refresh = true;
            $stat = $this->update();
            debug_add("saving updated values to database returned {$stat}");
        }
        return $stat;
    }

    function list_hours()
    {
        $hours = array
        (
            'reported'    => 0,
            'approved'    => 0,
            'invoiced'    => 0,
            'invoiceable' => 0,
        );

        // Check agreement for invoiceability rules
        $invoice_approved = false;

        try
        {
            $agreement = new org_openpsa_sales_salesproject_deliverable_dba($this->agreement);

            if ($agreement->invoiceApprovedOnly)
            {
                $invoice_approved = true;
            }
        }
        catch (midcom_error $e){}

        $report_mc = org_openpsa_projects_hour_report_dba::new_collector('task', $this->id);
        $report_mc->add_value_property('hours');
        $report_mc->add_value_property('invoice');
        $report_mc->add_value_property('invoiceable');
        $report_mc->add_value_property('metadata.isapproved');
        $report_mc->execute();

        $reports = $report_mc->list_keys();
        foreach ($reports as $guid => $empty)
        {
            $report_data = $report_mc->get($guid);

            $report_hours = $report_data['hours'];
            $is_approved = $report_data['isapproved'];

            $hours['reported'] += $report_hours;

            if ($is_approved)
            {
                $hours['approved'] += $report_hours;
            }

            if ($report_data['invoice'])
            {
                $hours['invoiced'] += $report_hours;
            }
            else if ($report_data['invoiceable'])
            {
                // Check agreement for invoiceability rules
                if ($invoice_approved)
                {
                    // Count only uninvoiced approved hours as invoiceable
                    if ($is_approved)
                    {
                        $hours['invoiceable'] += $report_hours;
                    }
                }
                else
                {
                    // Count all uninvoiced invoiceable hours as invoiceable regardless of approval status
                    $hours['invoiceable'] += $report_hours;
                }
            }
        }
        return $hours;
    }

    private function _update_parent()
    {
        if ($this->_skip_parent_refresh)
        {
            return true;
        }
        $project = $this->get_parent();
        if ($project)
        {
            $project->refresh_from_tasks();
        }
        return true;
    }

    static function pid_to_obj($pid)
    {
        return $_MIDCOM->auth->get_user($pid);
    }

    /**
     * Queries status objects and sets correct value to $this->status
     */
    private function _get_status()
    {
        $return = array
        (
            'status_comment' => '',
            'status_time' => false,
        );
        //Simplistic approach
        $mc = org_openpsa_projects_task_status_dba::new_collector('task', $this->id);
        $mc->add_value_property('type');
        $mc->add_value_property('comment');
        $mc->add_value_property('timestamp');

        if ($this->status > org_openpsa_projects_task_status_dba::PROPOSED)
        {
            //Only get proposed status objects here if are not over that phase
            $mc->add_constraint('type', '<>', org_openpsa_projects_task_status_dba::PROPOSED);
        }
        if (count($this->resources) > 0)
        {
            //Do not ever set status to declined if we still have resources left
            $mc->add_constraint('type', '<>', org_openpsa_projects_task_status_dba::DECLINED);
        }
        $mc->add_order('timestamp', 'DESC');
        $mc->add_order('type', 'DESC'); //Our timestamps are not accurate enough so if we have multiple with same timestamp suppose highest type is latest
        $mc->set_limit(1);

        $mc->execute();

        $ret = $mc->list_keys();

        if (   !is_array($ret)
            || count($ret) == 0)
        {
            //Failure to get status object

            //Default to last status if available
            debug_add('Could not find any status objects, defaulting to previous status');
            return $return;
        }

        $main_ret = key($ret);
        $type = $mc->get_subkey($main_ret, 'type');

        //Update the status cache if necessary
        if ($this->status != $type)
        {
            $this->status = $type;
            $this->update();
        }

        //TODO: Check various combinations of accept/decline etc etc

        $comment = $mc->get_subkey($main_ret, 'comment');
        $return['status_comment'] = $comment;

        $timestamp = $mc->get_subkey($main_ret, 'timestamp');
        $return['status_time'] = $timestamp;

        return $return;
    }

    /**
     * This function is called from the DM2 schema
     */
    static function get_task_resources()
    {
        $resource_array = array();
        $view_data =& $_MIDCOM->get_custom_context_data('request_data');
        if (!array_key_exists('task', $view_data))
        {
            return $resource_array;
        }

        $mc = org_openpsa_projects_task_resource_dba::new_collector('task', $view_data['task']->id);
        $mc->add_constraint('orgOpenpsaObtype', '=', ORG_OPENPSA_OBTYPE_PROJECTRESOURCE);
        $resources = $mc->get_values('person');

        foreach ($resources as $resource)
        {
            try
            {
                $person = org_openpsa_contacts_person_dba::get_cached($resource);
                $resource_array[$person->id] = $person->rname;
            }
            catch (midcom_error $e){}
        }
        return $resource_array;
    }
}
?>