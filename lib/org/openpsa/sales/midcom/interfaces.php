<?php
/**
 * @package org.openpsa.sales
 * @author Nemein Oy, http://www.nemein.com/
 * @copyright Nemein Oy, http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA Sales management component
 *
 * @package org.openpsa.sales
 */
class org_openpsa_sales_interface extends midcom_baseclasses_components_interface
implements midcom_services_permalinks_resolver
{
    public function resolve_object_link(midcom_db_topic $topic, midcom_core_dbaobject $object)
    {
        if ($object instanceof org_openpsa_sales_salesproject_dba)
        {
            return "salesproject/{$object->guid}/";
        }
        if ($object instanceof org_openpsa_sales_salesproject_deliverable_dba)
        {
            return "deliverable/{$object->guid}/";
        }
        return null;
    }

    /**
     * Used by org_openpsa_relatedto_suspect::find_links_object to find "related to" information
     *
     * Currently handles persons
     */
    function org_openpsa_relatedto_find_suspects($object, $defaults, &$links_array)
    {
        if (   !is_array($links_array)
            || !is_object($object))
        {
            debug_add('$links_array is not array or $object is not object, make sure you call this correctly', MIDCOM_LOG_ERROR);
            return;
        }

        switch(true)
        {
            case midcom::get('dbfactory')->is_a($object, 'midcom_db_person'):
                //List all projects and tasks given person is involved with
                $this->_org_openpsa_relatedto_find_suspects_person($object, $defaults, $links_array);
                break;
            case midcom::get('dbfactory')->is_a($object, 'midcom_db_event'):
            case midcom::get('dbfactory')->is_a($object, 'org_openpsa_calendar_event_dba'):
                $this->_org_openpsa_relatedto_find_suspects_event($object, $defaults, $links_array);
                break;
                //TODO: groups ? other objects ?
        }
    }

    /**
     * Used by org_openpsa_relatedto_find_suspects to in case the given object is a person
     *
     * Current rule: all participants of event must be either manager,contact or resource in task
     * that overlaps in time with the event.
     */
    private function _org_openpsa_relatedto_find_suspects_event(&$object, &$defaults, &$links_array)
    {
        if (   !is_array($object->participants)
            || count($object->participants) < 2)
        {
            //We have invalid list or less than two participants, abort
            return;
        }
        $mc = org_openpsa_contacts_role_dba::new_collector('role', org_openpsa_sales_salesproject_dba::ROLE_MEMBER);
        $mc->add_constraint('person', 'IN', array_keys($object->participants));
        $guids = $mc->get_values('objectGuid');

        $qb = org_openpsa_sales_salesproject_dba::new_query_builder();

        // Target sales project starts or ends inside given events window or starts before and ends after
        $qb->add_constraint('start', '<=', $object->end);
        $qb->begin_group('OR');
            $qb->add_constraint('end', '>=', $object->start);
            $qb->add_constraint('end', '=', 0);
        $qb->end_group();

        //Target sales project is active
        $qb->add_constraint('state', '=', org_openpsa_sales_salesproject_dba::STATE_ACTIVE);

        //Each event participant is either manager or member (resource/contact) in task
        $qb->begin_group('OR');
            $qb->add_constraint('owner', 'IN', array_keys($object->participants));
            if (!empty($guids))
            {
                $qb->add_constraint('guid', 'IN', $guids);
            }
        $qb->end_group();

        $qbret = $qb->execute();

        foreach ($qbret as $salesproject)
        {
            $to_array = array('other_obj' => false, 'link' => false);
            $link = new org_openpsa_relatedto_dba();
            org_openpsa_relatedto_suspect::defaults_helper($link, $defaults, $this->_component, $salesproject);
            $to_array['other_obj'] = $salesproject;
            $to_array['link'] = $link;

            $links_array[] = $to_array;
        }
    }

    /**
     * Used by org_openpsa_relatedto_find_suspects to in case the given object is a person
     */
    private function _org_openpsa_relatedto_find_suspects_person(&$object, &$defaults, &$links_array)
    {
        $seen_sp = array();
        $mc = org_openpsa_contacts_role_dba::new_collector('role', org_openpsa_sales_salesproject_dba::ROLE_MEMBER);
        $mc->add_constraint('person', '=', array_keys($object->id));
        $guids = $mc->get_values('objectGuid');

        if (!empty($guids))
        {
            $qb = org_openpsa_sales_salesproject_dba::new_query_builder();
            $qb->add_constraint('state', '=', org_openpsa_sales_salesproject_dba::STATE_ACTIVE);
            $qb->add_constraint('guid', 'IN', $guids);
            $qbret = $qb->execute();
            foreach ($qbret as $salesproject)
            {
                $seen_sp[$salesproject->id] = true;
                $to_array = array('other_obj' => false, 'link' => false);
                $link = new org_openpsa_relatedto_dba();
                org_openpsa_relatedto_suspect::defaults_helper($link, $defaults, $this->_component, $salesproject);
                $to_array['other_obj'] = $salesproject;
                $to_array['link'] = $link;

                $links_array[] = $to_array;
            }
        }
        $qb2 = org_openpsa_sales_salesproject_dba::new_query_builder();
        $qb2->add_constraint('owner', '=', $object->id);
        $qb2->add_constraint('state', '=', org_openpsa_sales_salesproject_dba::STATE_ACTIVE);
        if (!empty($seen_sp))
        {
            $qb2->add_constraint('id', 'NOT IN', array_keys($seen_sp));
        }
        $qb2ret = $qb2->execute();
        foreach ($qb2ret as $sp)
        {
            $to_array = array('other_obj' => false, 'link' => false);
            $link = new org_openpsa_relatedto_dba();
            org_openpsa_relatedto_suspect::defaults_helper($link, $defaults, $this->_component, $sp);
            $to_array['other_obj'] = $sp;
            $to_array['link'] = $link;

            $links_array[] = $to_array;
        }
    }

    /**
     * AT handler for handling subscription cycles.
     *
     * @param array $args handler arguments
     * @param object &$handler reference to the cron_handler object calling this method.
     * @return boolean indicating success/failure
     */
    function new_subscription_cycle($args, &$handler)
    {
        if (   !isset($args['deliverable'])
            || !isset($args['cycle']))
        {
            $handler->print_error('deliverable GUID or cycle number not set, aborting');
            return false;
        }

        try
        {
            $deliverable = new org_openpsa_sales_salesproject_deliverable_dba($args['deliverable']);
        }
        catch (midcom_error $e)
        {
            $msg = "Deliverable {$args['deliverable']} not found, error " . midcom_connection::get_error_string();
            $handler->print_error($msg);
            return false;
        }
        $scheduler = new org_openpsa_invoices_scheduler($deliverable);

        return $scheduler->run_cycle($args['cycle']);
    }

    /**
     * Function to send a notification to owner of the deliverable - guid of deliverable is passed
     */
    public function new_notification_message($args, &$handler)
    {
        if (!isset($args['deliverable']))
        {
            $handler->print_error('deliverable GUID not set, aborting');
            return false;
        }
        try
        {
            $deliverable = new org_openpsa_sales_salesproject_deliverable_dba($args['deliverable']);
        }
        catch (midcom_error $e)
        {
            $handler->print_error('no deliverable with passed GUID: ' . $args['deliverable'] . ', aborting');
            return false;
        }

        //get the owner of the salesproject the deliverable belongs to
        try
        {
            $project = new org_openpsa_sales_salesproject_dba($deliverable->salesproject);
        }
        catch (midcom_error $e)
        {
            $handler->print_error('Failed to load salesproject: ' . $e->getMessage());
            return false;
        }

        $content = sprintf
        (
            $this->_l10n->get('agreement %s ends on %s. click here: %s'),
            $deliverable->title,
            strftime('%x', $deliverable->end),
            midcom::get('permalinks')->create_permalink($deliverable->guid)
        );

        $message = array
        (
            'title' => sprintf($this->_l10n->get('notification for agreement %s'), $deliverable->title),
            'content' => $content,
        );

        return org_openpsa_notifications::notify('org.openpsa.sales:new_notification_message', $project->owner, $message);
    }
}
?>