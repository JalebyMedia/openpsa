<?php
/**
 * @package org.openpsa.expenses
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This is the class that defines which URLs should be handled by this module.
 *
 * @package org.openpsa.expenses
 */
class org_openpsa_expenses_viewer extends midcom_baseclasses_components_request
{
    /**
     * Populates the node toolbar depending on the user's rights.
     */
    private function _populate_view_toolbar($task)
    {
        midcom::get('componentloader')->load('org.openpsa.projects');
        $schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_hours'));

        foreach (array_keys($schemadb) as $name)
        {
            $create_url = "hours/create/{$name}/";

            if ($task)
            {
                $create_url .= $task . "/";
            }

            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => $create_url,
                    MIDCOM_TOOLBAR_LABEL => sprintf
                    (
                        $this->_l10n_midcom->get('create %s'),
                        $this->_l10n->get($schemadb[$name]->description)
                    ),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/stock_new-event.png',
                )
            );
        }
    }

    /**
     * The handle callback populates the toolbars.
     */
    public function _on_handle($handler, $args)
    {
        $task = false;
        if (   $handler == 'list_hours_task'
            || $handler == 'list_hours_task_all')
        {
            $task = $args[0];
        }
        if (   strpos($handler, 'index') !== false
            || strpos($handler, 'list') !== false)
        {
            $this->_populate_view_toolbar($task);
        }
    }

    /**
     * Apply user filters to hour lists
     *
     * @param midcom_core_query $query The query object to work on
     */
    public function add_list_filter(midcom_core_query $query)
    {
        $qf = new org_openpsa_core_queryfilter('org_openpsa_expenses_list');
        $person_filter = new org_openpsa_core_filter('person');
        $person_filter->set('option_callback', array($this, 'get_person_options'));
        $person_filter->set('mode', 'multiselect');
        $person_filter->set('helptext', $this->_l10n->get("choose user"));
        $qf->add_filter($person_filter);

        if (   $this->_request_data['handler_id'] != 'index_timestamp'
            && $this->_request_data['handler_id'] != 'index')
        {
            $date_filter = new org_openpsa_core_filter('date');
            $date_filter->set('mode', 'timeframe');
            $date_filter->set('helptext', $this->_l10n->get("timeframe"));
            $qf->add_filter($date_filter);
        }
        $qf->apply_filters($query);
        $this->_request_data["qf"] = $qf;
    }

    /**
     * Helper function that lists options for the person filter
     */
    public function get_person_options()
    {
        $qb_persons = midcom_db_person::new_query_builder();
        midcom_core_account::add_username_constraint($qb_persons, '<>', '');

        $person_array = array();

        $persons = $qb_persons->execute();
        foreach ($persons as $person)
        {
            $person_array[$person->id] = $person->get_label();
        }
        return $person_array;
    }
}
?>