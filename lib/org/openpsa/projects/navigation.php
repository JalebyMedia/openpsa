<?php
/**
 * @package org.openpsa.projects
 * @author Nemein Oy, http://www.nemein.com/
 * @copyright Nemein Oy, http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.projects NAP interface class.
 *
 * @package org.openpsa.projects
 */
class org_openpsa_projects_navigation extends midcom_baseclasses_components_navigation
{
    /**
     * Returns a static leaf list with access to different task lists.
     */
    public function get_leaves()
    {
        $leaves = array();

        $leaves["{$this->_topic->id}:tasks_open"] = array
        (
            MIDCOM_NAV_URL => "task/list/all/open/",
            MIDCOM_NAV_NAME => $this->_l10n->get('open tasks'),
        );

        $leaves["{$this->_topic->id}:tasks_closed"] = array
        (
            MIDCOM_NAV_URL => "task/list/all/closed/",
            MIDCOM_NAV_NAME => $this->_l10n->get('closed tasks'),
        );

        $leaves["{$this->_topic->id}:tasks_invoiceable"] = array
        (
            MIDCOM_NAV_URL => "task/list/all/invoiceable/",
            MIDCOM_NAV_NAME => $this->_l10n->get('invoiceable tasks'),
        );

        $leaves["{$this->_topic->id}:tasks_invoiced"] = array
        (
            MIDCOM_NAV_URL => "task/list/all/invoiced/",
            MIDCOM_NAV_NAME => $this->_l10n->get('invoiced tasks'),
        );

        return $leaves;
    }
}
?>