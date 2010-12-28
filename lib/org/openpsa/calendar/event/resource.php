<?php
/**
 * @package org.openpsa.calendar
 * @author Nemein Oy, http://www.nemein.com/
 * @copyright Nemein Oy, http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * MidCOM wrapped class for access to stored queries
 *
 * @package org.openpsa.calendar
 */
class org_openpsa_calendar_event_resource_dba extends midcom_core_dbaobject
{
    public $__midcom_class_name__ = __CLASS__;
    public $__mgdschema_class_name__ = 'org_openpsa_calendar_event_resource';

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
     * Human-readable label for cases like Asgard navigation
     */
    function get_label()
    {
        if ($this->resource)
        {
            $resource = new org_openpsa_calendar_resource_dba($this->resource);
            $event = new org_openpsa_calendar_event_dba($this->event);
            return sprintf($_MIDCOM->i18n->get_string('%s for %s', 'midcom'), $resource->title, $event->title);
        }
        return "member #{$this->id}";
    }

    /**
     * Function to check whether we can reserve the resource we are trying to
     *
     * @return boolean indicating state
     * @todo cache results
     */
    function verify_can_reserve()
    {
        if (empty($this->resource))
        {
            debug_add("Resource is set to empty value returning true");
            return true;
        }
        try
        {
            $resource = new org_openpsa_calendar_resource_dba($this->resource);
        }
        catch (midcom_error $e)
        {
            debug_add("Cannot fetch resource #{$this->resource} returning false", MIDCOM_LOG_INFO);
            return false;
        }
        $stat =  $resource->can_do('org.openpsa.calendar:reserve');
        if (!$stat)
        {
            debug_add("\$resource->can_do('org.openpsa.calendar:reserve'), returned false, so will we", MIDCOM_LOG_INFO);
        }
        return $stat;
    }

    public function _on_creating()
    {
        if (!$this->verify_can_reserve())
        {
            midcom_connection::set_error(MGD_ERR_ACCESS_DENIED);
            return false;
        }
        return true;
    }

    public function _on_updating()
    {
        if (!$this->verify_can_reserve())
        {
            midcom_connection::set_error(MGD_ERR_ACCESS_DENIED);
            return false;
        }
        return true;
    }

    function get_parent_guid_uncached()
    {
        if ($this->event != 0)
        {
            try
            {
                $parent = new org_openpsa_calendar_event_dba($this->event);
                return $parent->guid;
            }
            catch (midcom_error $e)
            {
                return null;
            }
        }
        else
        {
            return null;
        }
    }

    /**
     * @todo Send notification to resource owner
     */
    function notify($repeat_handler = 'this', $event = false)
    {
        return true;
    }
}
?>