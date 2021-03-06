<?php
/**
 * @package org.openpsa.expenses
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midcom\grid\grid;

/**
 * @package org.openpsa.expenses
 */
class org_openpsa_expenses_handler_index extends midcom_baseclasses_components_handler
{
    use org_openpsa_expenses_handler;

    /**
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    private function prepare_dates(array $args, array &$data)
    {
        if (isset($args[0])) {
            $requested_time = $args[0];
        } else {
            $requested_time = date('Y-m-d');
        }
        $date = new DateTime($requested_time);
        $offset = $date->format('N') - 1;

        $date->modify('-' . $offset . ' days');
        $data['week_start'] = (int) $date->format('U');

        $date->modify('+7 days -1 second');
        $data['week_end'] = (int) $date->format('U');

        $date->modify('+1 day');
        $data['next_week'] = $date->format('Y-m-d');

        $date->modify('-14 days');
        $data['previous_week'] = $date->format('Y-m-d');
    }

    /**
     * The handler for the index view.
     *
     * @param mixed $handler_id the array key from the request array
     * @param array $args the arguments given to the handler
     * @param array &$data The local request data.
     */
    public function _handler_index($handler_id, array $args, array &$data)
    {
        $this->prepare_dates($args, $data);

        $hours_mc = org_openpsa_expenses_hour_report_dba::new_collector();
        $this->add_list_filter($hours_mc);
        $hours_mc->add_constraint('date', '>=', $data['week_start']);
        $hours_mc->add_constraint('date', '<=', $data['week_end']);
        $hours_mc->add_order('task');
        $hours_mc->add_order('date');

        $data['rows'] = $this->_get_sorted_reports($hours_mc);

        $this->_populate_toolbar($data['previous_week'], $data['next_week']);

        grid::add_head_elements();

        $data['view_title'] = sprintf($this->_l10n->get("expenses in week %s"), strftime("%V %G", $data['week_start']));
        $this->add_breadcrumb('', $data['view_title']);
        midcom::get()->head->set_pagetitle($data['view_title']);
        $data['grid'] = new grid('hours_week', 'local');
        $data['group_options'] = [
            'task' => $this->_l10n->get('task'),
            'person' => $this->_l10n->get('person')
        ];

        return $this->show('hours_week');
    }

    private function _populate_toolbar($previous_week, $next_week)
    {
        $week_start = strftime('%Y-%m-%d', $this->_request_data['week_start']);
        $week_end = strftime('%Y-%m-%d', $this->_request_data['week_end']);
        $buttons = [
            [
                MIDCOM_TOOLBAR_URL => $this->router->generate('list_hours') . '?date[from]=' . $week_start . '&date[to]=' . $week_end,
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('list view'),
                MIDCOM_TOOLBAR_GLYPHICON => 'list',
            ],
            [
                MIDCOM_TOOLBAR_URL => $this->router->generate('index_timestamp', ['timestamp' => $previous_week]),
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('previous week'),
                MIDCOM_TOOLBAR_GLYPHICON => 'chevron-left',
            ],
            [
                MIDCOM_TOOLBAR_URL => $this->router->generate('index_timestamp', ['timestamp' => $next_week]),
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('next week'),
                MIDCOM_TOOLBAR_GLYPHICON => 'chevron-right',
            ]
        ];
        $this->_view_toolbar->add_items($buttons);
    }

    /**
     * Sort the reports by task and day
     */
    private function _get_sorted_reports(midcom_core_collector $hours_mc)
    {
        $workflow = $this->get_workflow('datamanager');
        $reports = [];
        $hours = $hours_mc->get_rows(['task', 'hours', 'date', 'person']);
        $formatter = $this->_l10n->get_formatter();
        foreach ($hours as $guid => $row) {
            try {
                $task = org_openpsa_projects_task_dba::get_cached($row['task']);
            } catch (midcom_error $e) {
                // Task couldn't be loaded, probably because of ACL
                continue;
            }

            $date_identifier = date('Y-m-d', $row['date']);
            $row_identifier = $task->id . '-' .  $row['person'];

            if (!isset($reports[$row_identifier])) {
                try {
                    $person_object = org_openpsa_contacts_person_dba::get_cached($row['person']);
                    $person_label = $this->_get_list_link($person_object->name, null, null, $row['person']);
                    $person_name = $person_object->name;
                } catch (midcom_error $e) {
                    $person_label = $this->_l10n->get('no person');
                    $person_name = '';
                }

                $reports[$row_identifier] = [
                    'task' => $this->_get_list_link($task->get_label(), null, $task->guid),
                    'index_task' => $task->get_label(),
                    'person' => $person_label,
                    'index_person' => $person_name
                ];
            }
            if (!isset($reports[$row_identifier][$date_identifier])) {
                $link = $this->router->generate('hours_edit', ['guid' => $guid]);
                $reports[$row_identifier]['index_' . $date_identifier] = $row['hours'];
                $number = $formatter->number($reports[$row_identifier]['index_' . $date_identifier]);
                $reports[$row_identifier][$date_identifier] = '<a href="' . $link . '" ' . $workflow->render_attributes() . '>' . $number . '</a>';
            } else {
                $reports[$row_identifier]['index_' . $date_identifier] += $row['hours'];
                $reports[$row_identifier][$date_identifier] = $this->_get_list_link($formatter->number($reports[$row_identifier]['index_' . $date_identifier]), $date_identifier, $task->guid, $row['person']);
            }
        }

        return array_values($reports);
    }

    private function _get_list_link($label, $date = null, $task_guid = null, $person_id = null)
    {
        if ($task_guid !== null) {
            $url = $this->router->generate('list_hours_task', ['guid' => $task_guid]);
        } else {
            $url = $this->router->generate('list_hours');
        }

        $filters = [];

        if ($date !== null) {
            $filters['date'] = ['from' => $date, 'to' => $date];
        } else {
            $start = strftime('%Y-%m-%d', $this->_request_data['week_start']);
            $end = strftime('%Y-%m-%d', $this->_request_data['week_end']);
            $filters['date'] = ['from' => $start, 'to' => $end];
        }
        if ($person_id !== null) {
            $filters['person'] = [$person_id];
        }
        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }

        return "<a href=\"{$url}\">" . $label . "</a>";
    }
}
