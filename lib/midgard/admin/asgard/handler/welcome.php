<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use midcom\grid\provider;

/**
 * Welcome interface
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_handler_welcome extends midcom_baseclasses_components_handler
{
    private function _list_revised($since, $type = null, $only_mine = false)
    {
        $classes = [];
        $revised = [];

        // List installed MgdSchema types and convert to DBA classes
        foreach ($this->_request_data['schema_types'] as $schema_type) {
            if (   !is_null($type)
                && $schema_type != $type) {
                // Skip
                continue;
            }

            $mgdschema_class = midcom_helper_reflector::class_rewrite($schema_type);
            $dummy_object = new $mgdschema_class();
            $midcom_dba_classname = midcom::get()->dbclassloader->get_midcom_class_name_for_mgdschema_object($dummy_object);
            if (empty($midcom_dba_classname)) {
                continue;
            }

            $classes[] = $midcom_dba_classname;
        }

        // List all revised objects
        foreach ($classes as $class) {
            $qb_callback = [$class, 'new_query_builder'];
            $qb = call_user_func($qb_callback);
            $qb->add_constraint('metadata.revised', '>=', $since);

            if (   $only_mine
                && midcom::get()->auth->user) {
                $qb->add_constraint('metadata.authors', 'LIKE', '|' . midcom::get()->auth->user->guid . '|');
            }

            $qb->add_order('metadata.revision', 'DESC');

            foreach ($qb->execute() as $object) {
                $revisor = midcom::get()->auth->get_user($object->metadata->revisor);

                $revised["{$object->metadata->revised}_{$object->guid}_{$object->metadata->revision}"] = [
                    'object' => $object,
                    'revisor' => $revisor
                ];
            }
        }

        krsort($revised);

        return $revised;
    }

    /**
     * Object editing view
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_welcome($handler_id, array $args, array &$data)
    {
        $data['schema_types'] = array_diff(midcom_connection::get_schema_types(), $this->_config->get('skip_in_filter'));

        $data['view_title'] = $this->_l10n->get('asgard');

        if (    !empty($_POST['action'])
             && !empty($_POST['entries'])) {
            $method_name = "_mass_{$_POST['action']}";
            $this->$method_name($_POST['entries']);
        }

        if (isset($_REQUEST['revised_after'])) {
            $data['revised_after'] = date('Y-m-d H:i:s\Z', $_REQUEST['revised_after']);

            $data['type_filter'] = null;
            if (   isset($_REQUEST['type_filter'])
                && $_REQUEST['type_filter'] != 'any') {
                $data['type_filter'] = $_REQUEST['type_filter'];
            }

            $data['only_mine'] = !empty($_REQUEST['only_mine']);

            $objects = $this->_list_revised($data['revised_after'], $data['type_filter'], $data['only_mine']);
        } elseif (class_exists('midcom_helper_activitystream_activity_dba')) {
            $objects = $this->_load_activities();
        } else {
            $data['revised_after'] = date('Y-m-d H:i:s\Z', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')));
            $objects = $this->_list_revised($data['revised_after']);
        }

        $this->_prepare_tabledata($objects);

        $data['action_options'] = [
            'none' => ['label' => $this->_l10n->get('apply to selected')],
            'delete' => [
                'label' => $this->_l10n_midcom->get('delete'),
            ],
            'approve' => [
                'label' => $this->_l10n_midcom->get('approve'),
            ]
        ];

        $this->_populate_toolbar();
        return new midgard_admin_asgard_response($this, '_show_welcome');
    }

    private function _load_activities()
    {
        $objects = [];
        $activities = midcom_helper_activitystream_activity_dba::get($this->_config->get('last_visited_size'));
        foreach ($activities as $activity) {
            try {
                $object = midcom::get()->dbfactory->get_object_by_guid($activity->target);
            } catch (midcom_error $e) {
                if (midcom_connection::get_error() == MGD_ERR_OBJECT_DELETED) {
                    // TODO: Visualize deleted objects somehow
                }
                continue;
            }
            try {
                $actor = midcom_db_person::get_cached($activity->actor);
            } catch (midcom_error $e) {
                $actor = null;
            }

            $objects[] = [
                'object' => $object,
                'revisor' => $actor
            ];
        }
        return $objects;
    }

    private function _prepare_tabledata(array $objects)
    {
        $rows = [];
        foreach ($objects as $data) {
            $object = $data['object'];
            $reflector = midcom_helper_reflector::get($object);

            $row = [
                'revision' => $object->metadata->revision,
                'revised' => $object->metadata->revised,
                'id' => $object->guid,
                'class' => get_class($object),
            ];

            $row['approved'] = ($object->is_approved()) ? strftime('%x %X', $object->metadata->approved) : $this->_l10n->get('not approved');

            $title = $reflector->get_object_label($object);
            $link = $this->router->generate('object_' . $this->_request_data['default_mode'], ['guid' => $object->guid]);
            $row['index_title'] = ($title) ?: '[' . $this->_l10n->get('no title') . ']';
            $row['title'] = '<a href="' . $link . '" title="' . $row['class'] . '">' . $reflector->get_object_icon($object) . ' ' . $row['index_title'] . '</a>';

            if (empty($data['revisor'])) {
                $row['revisor'] = $this->_l10n_midcom->get('unknown');
            } else {
                $row['revisor'] = $data['revisor']->name;
            }
            $rows[] = $row;
        }
        $provider = new provider($rows, 'local');
        $this->_request_data['grid'] = $provider->get_grid('revised');
    }

    private function _populate_toolbar()
    {
        $buttons = [];
        $buttons[] = [
            MIDCOM_TOOLBAR_URL => $this->router->generate('preferences'),
            MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('user preferences'),
            MIDCOM_TOOLBAR_GLYPHICON => 'sliders',
        ];

        if (midcom::get()->auth->admin) {
            $buttons[] = [
                MIDCOM_TOOLBAR_URL => $this->router->generate('shell'),
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('shell'),
                MIDCOM_TOOLBAR_GLYPHICON => 'terminal',
            ];
            $buttons[] = [
                MIDCOM_TOOLBAR_URL => $this->router->generate('trash'),
                MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('trash'),
                MIDCOM_TOOLBAR_GLYPHICON => 'trash',
            ];
        }

        $buttons[] = [
            MIDCOM_TOOLBAR_URL => $this->router->generate('components'),
            MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('components'),
            MIDCOM_TOOLBAR_GLYPHICON => 'puzzle-piece',
        ];

        // Add link to site
        $buttons[] = [
            MIDCOM_TOOLBAR_URL => midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX),
            MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('back to site'),
            MIDCOM_TOOLBAR_GLYPHICON => 'home',
        ];

        $buttons[] = [
            MIDCOM_TOOLBAR_URL => midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX) . "midcom-logout-",
            MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('logout'),
            MIDCOM_TOOLBAR_GLYPHICON => 'sign-out',
        ];
        $this->_request_data['asgard_toolbar']->add_items($buttons);
    }

    private function _mass_delete($guids)
    {
        foreach ($guids as $guid) {
            try {
                $object = midcom::get()->dbfactory->get_object_by_guid($guid);
            } catch (midcom_error $e) {
                continue;
            }

            if ($object->delete()) {
                midcom::get()->uimessages->add($this->_l10n->get('midgard.admin.asgard'), sprintf($this->_l10n->get('object %s removed'), $object->guid));
            }
        }
    }

    private function _mass_approve($guids)
    {
        foreach ($guids as $guid) {
            try {
                $object = midcom::get()->dbfactory->get_object_by_guid($guid);
            } catch (midcom_error $e) {
                continue;
            }

            if (   $object->can_do('midgard:update')
                && $object->can_do('midcom:approve')) {
                $object->metadata->approve();
                midcom::get()->uimessages->add($this->_l10n->get('midgard.admin.asgard'), sprintf($this->_l10n->get('object %s approved'), $object->guid));
            }
        }
    }

    /**
     * Shows the loaded object in editor.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_welcome($handler_id, array &$data)
    {
        if (midcom::get()->auth->can_user_do('midgard.admin.asgard:manage_objects', null, 'midgard_admin_asgard_plugin')) {
            midcom_show_style('midgard_admin_asgard_welcome');
        }
    }
}
