<?php
/**
 * @package org.openpsa.projects
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Projects index handler
 *
 * @package org.openpsa.projects
 */
class org_openpsa_projects_handler_frontpage extends midcom_baseclasses_components_handler
{
    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_frontpage($handler_id, array $args, array &$data)
    {
        $_MIDCOM->auth->require_valid_user();

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => 'project/new/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("create project"),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new-dir.png',
                MIDCOM_TOOLBAR_ENABLED => $_MIDCOM->auth->can_user_do('midgard:create', null, 'org_openpsa_projects_project'),
            )
        );

        $this->_view_toolbar->add_item
        (
            array
            (
                MIDCOM_TOOLBAR_URL => 'task/new/',
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get("create task"),
                MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new_task.png',
                MIDCOM_TOOLBAR_ENABLED => $_MIDCOM->auth->can_user_do('midgard:create', null, 'org_openpsa_projects_task_dba'),
            )
        );

        // List current projects, sort by customer
        $data['customers'] = array();
        $project_qb = org_openpsa_projects_project::new_query_builder();
        $project_qb->add_constraint('status', '<', ORG_OPENPSA_TASKSTATUS_CLOSED);
        $project_qb->add_order('customer.official');
        $project_qb->add_order('end');
        $projects = $project_qb->execute();
        foreach ($projects as $project)
        {
            if (!isset($data['customers'][$project->customer]))
            {
                $data['customers'][$project->customer] = array();
            }

            $data['customers'][$project->customer][] = $project;
        }

        // Projects without customer have to be queried separately, see #97
        $nocustomer_qb = org_openpsa_projects_project::new_query_builder();
        $nocustomer_qb->add_constraint('status', '<', ORG_OPENPSA_TASKSTATUS_CLOSED);
        $nocustomer_qb->add_constraint('customer', '=', 0);
        $nocustomer_qb->add_order('end');
        if ($nocustomer_qb->count() > 0)
        {
            $data['customers'][0] = $nocustomer_qb->execute();
        }

        $closed_qb = org_openpsa_projects_project::new_query_builder();
        $closed_qb->add_constraint('status', '=', ORG_OPENPSA_TASKSTATUS_CLOSED);
        $data['closed_count'] = $closed_qb->count();

        $this->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.core/list.css");

        $_MIDCOM->add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.projects/frontpage.js');

        $_MIDCOM->set_pagetitle($this->_l10n->get('current projects'));
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_frontpage($handler_id, array &$data)
    {
        midcom_show_style("show-frontpage");
    }
}
?>