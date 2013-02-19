<?php
/**
 * Created on 2006-08-09
 * @author Henri Bergius
 * @package org.openpsa.products
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * The midcom_baseclasses_components_handler class defines a bunch of helper vars
 *
 * @package org.openpsa.products
 * @see midcom_baseclasses_components_handler
 */
class org_openpsa_products_handler_group_list  extends midcom_baseclasses_components_handler
{
    /**
     * Can-Handle check against the current group GUID. We have to do this explicitly
     * in can_handle already, otherwise we would hide all subtopics as the request switch
     * accepts all argument count matches unconditionally.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     * @return boolean True if the request can be handled, false otherwise.
     */
    public function _can_handle_list($handler_id, array $args, array &$data)
    {
        if ($handler_id == 'index')
        {
            // We're in root-level product index
            if ($data['root_group'])
            {
                $data['group'] = org_openpsa_products_product_group_dba::get_cached($data['root_group']);
                $data['view_title'] = $data['group']->title;
            }
            else
            {
                $data['group'] = null;
                $data['view_title'] = $this->_l10n->get('product database');
            }
            $data['parent_group'] = $data['root_group'];
        }
        else
        {
            // We're in some level of groups
            $qb = org_openpsa_products_product_group_dba::new_query_builder();
            if (   $handler_id == 'list_intree'
                || $handler_id == 'listall')
            {
                $parentgroup_qb = org_openpsa_products_product_group_dba::new_query_builder();
                $parentgroup_qb->add_constraint('code', '=', $args[0]);
                $groups = $parentgroup_qb->execute();
                if (empty($groups))
                {
                    // No such parent group found
                    return false;
                }
                if (!empty($groups[0]->id))
                {
                    $qb->add_constraint('up', '=', $groups[0]->id);
                    if ($handler_id == 'listall')
                    {
                        $qb->add_constraint('code', '=', $args[1]);
                    }
                }
            }
            else
            {
                $qb->add_constraint('code', '=', $args[0]);
            }

            $results = $qb->execute();
            if (count($results) == 0)
            {
                try
                {
                    $data['group'] = new org_openpsa_products_product_group_dba($args[0]);
                }
                catch (midcom_error $e)
                {
                    return false;
                }
            }
            else
            {
                $data['group'] = $results[0];
            }

            $data['parent_group'] = $data['group']->id;

            if ($handler_id == 'listall')
            {
                try
                {
                    $group_up = new org_openpsa_products_product_group_dba($data['group']->up);
                    if (isset($group_up->title))
                    {
                        $data['group'] = $group_up;
                    }
                }
                catch (midcom_error $e){}
            }

            if ($this->_config->get('code_in_title'))
            {
                $data['view_title'] = "{$data['group']->code} {$data['group']->title}";
            }
            else
            {
                $data['view_title'] = $data['group']->title;
            }

            if ($handler_id == 'listall')
            {
                $data['view_title'] = sprintf($this->_l10n_midcom->get('All %s'), $data['view_title']);
            }

            $data['acl_object'] = $data['group'];
        }

        return true;
    }

    /**
     * The handler for the group_list article.
     *
     * @param mixed $handler_id the array key from the request array
     * @param array $args the arguments given to the handler
     * @param array &$data The local request data.
     */
    public function _handler_list($handler_id, array $args, array &$data)
    {
        // Query for sub-objects
        if ($handler_id == 'list_intree')
        {
            $this->_handle_list_intree($args);
        }
        else if ($handler_id == 'listall')
        {
            $this->_handle_listall($args);
        }
        else if ($handler_id == 'list')
        {
            $this->_handle_list($args);
        }

        $group_qb = org_openpsa_products_product_group_dba::new_query_builder();
        $group_qb->add_constraint('up', '=', $data['parent_group']);

        foreach ($this->_config->get('groups_listing_order') as $ordering)
        {
            $this->_add_ordering($group_qb, $ordering);
        }

        $this->_request_data['linked_products'] = array();
        if ($this->_config->get('enable_productlinks'))
        {
            $mc_productlinks = org_openpsa_products_product_link_dba::new_collector('productGroup', $data['parent_group']);
            $this->_request_data['linked_products'] = $mc_productlinks->get_values('product');
        }

        $data['groups'] = $group_qb->execute();
        $data['products'] = array();
        if ($this->_config->get('group_list_products'))
        {
            $this->_list_group_products();
        }

        // Prepare datamanager
        $data['datamanager_group'] = new midcom_helper_datamanager2_datamanager($data['schemadb_group']);
        $data['datamanager_product'] = new midcom_helper_datamanager2_datamanager($data['schemadb_product']);

        $this->_populate_toolbar();

        if ($data['group'])
        {
            if (midcom::get('config')->get('enable_ajax_editing'))
            {
                $data['controller'] = midcom_helper_datamanager2_controller::create('ajax');
                $data['controller']->schemadb =& $data['schemadb_group'];
                $data['controller']->set_storage($data['group']);
                $data['controller']->process_ajax();
                $data['datamanager_group'] =& $data['controller']->datamanager;
            }
            else
            {
                $data['controller'] = null;
                if (!$data['datamanager_group']->autoset_storage($data['group']))
                {
                    throw new midcom_error("Failed to create a DM2 instance for product group {$data['group']->guid}.");
                }
            }
            $this->bind_view_to_object($data['group'], $data['datamanager_group']->schema->name);
        }

        $this->_update_breadcrumb_line();

        // Set the active leaf
        if (   $this->_config->get('display_navigation')
            && $this->_request_data['group'])
        {
            $group = $this->_request_data['group'];

            // Loop as long as it is possible to get the parent group
            while ($group->guid)
            {
                // Break to the requested level (probably the root group of the products content topic)
                if (   $group->id === $this->_config->get('root_group')
                    || $group->guid === $this->_config->get('root_group'))
                {
                    break;
                }
                $temp = $group->id;
                if ($group->up == 0)
                {
                    break;
                }
                $group = new org_openpsa_products_product_group_dba($group->up);
            }

            if (isset($temp))
            {
                // Active leaf of the topic
                $this->set_active_leaf($temp);
            }
        }

        midcom::get('head')->set_pagetitle($this->_request_data['view_title']);
    }

    private function _handle_list_intree($args)
    {
        $parentgroup_qb = org_openpsa_products_product_group_dba::new_query_builder();
        $parentgroup_qb->add_constraint('code', '=', $args[0]);
        $groups = $parentgroup_qb->execute();
        if (count($groups) == 0)
        {
            throw new midcom_error_notfound('No matching group');
        }
        else
        {
            $categories_qb = org_openpsa_products_product_group_dba::new_query_builder();
            $categories_qb->add_constraint('up', '=', $groups[0]->id);
            $categories_qb->add_constraint('code', '=', $args[1]);
            $categories = $categories_qb->execute();

            $this->_request_data['parent_category_id'] = $categories[0]->id;
            $this->_request_data['parent_category'] = $groups[0]->code;
        }
    }

    private function _handle_listall($args)
    {
        $parentgroup_qb = org_openpsa_products_product_group_dba::new_query_builder();
        $parentgroup_qb->add_constraint('code', '=', $args[0]);
        $groups = $parentgroup_qb->execute();

        if (count($groups) == 0)
        {
            throw new midcom_error_notfound('No matching group');
        }
        else
        {
            $this->_request_data['group'] = $groups[0];
        }
    }

    private function _handle_list($args)
    {
        $guidgroup_qb = org_openpsa_products_product_group_dba::new_query_builder();
        $guidgroup_qb->add_constraint('guid', '=', $args[0]);
        $groups = $guidgroup_qb->execute();

        if (count($groups) > 0)
        {
            $categories_qb = org_openpsa_products_product_group_dba::new_query_builder();
            $categories_qb->add_constraint('id', '=', $groups[0]->up);
            $categories = $categories_qb->execute();

            if (count($categories) > 0)
            {
                $this->_request_data['parent_category'] = $categories[0]->code;
            }
        }
        else
        {
            //do not set the parent category. The category is already a top category.
        }
        org_openpsa_widgets_grid::add_head_elements();
    }

    private function _add_ordering(&$qb, $ordering)
    {
        if (preg_match('/\s*reversed?\s*/', $ordering))
        {
            $reversed = true;
            $ordering = preg_replace('/\s*reversed?\s*/', '', $ordering);
        }
        else
        {
            $reversed = false;
        }

        if ($reversed)
        {
            $qb->add_order($ordering, 'DESC');
        }
        else
        {
            $qb->add_order($ordering);
        }
    }

    private function _populate_toolbar()
    {
        if ($this->_request_data['group'])
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "edit/{$this->_request_data['group']->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('edit'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                    MIDCOM_TOOLBAR_ENABLED => $this->_request_data['group']->can_do('midgard:update'),
                    MIDCOM_TOOLBAR_ACCESSKEY => 'e',
                )
            );
            $allow_create_group = $this->_request_data['group']->can_do('midgard:create');
            $allow_create_product = $this->_request_data['group']->can_do('midgard:create');

            if ($this->_request_data['group']->orgOpenpsaObtype == org_openpsa_products_product_group_dba::TYPE_SMART)
            {
                $allow_create_product = false;
            }
        }
        else
        {
            $allow_create_group = midcom::get('auth')->can_user_do('midgard:create', null, 'org_openpsa_products_product_group_dba');
            $allow_create_product = midcom::get('auth')->can_user_do('midgard:create', null, 'org_openpsa_products_product_dba');
        }

        $this->_add_schema_buttons('schemadb_group', 'new-dir', '', $allow_create_group);
        $this->_add_schema_buttons('schemadb_product', 'new-text', 'product/', $allow_create_product);

        if (   $this->_config->get('enable_productlinks')
            && isset($this->_request_data['schemadb_productlink']))
        {
            $this->_request_data['datamanager_productlink'] = new midcom_helper_datamanager2_datamanager($this->_request_data['schemadb_productlink']);
            $this->_add_schema_buttons('schemadb_productlink', 'new-text', 'productlink/', $allow_create_product);
        }
    }

    private function _add_schema_buttons($schemadb_name, $default_icon, $prefix, $allowed)
    {
        foreach (array_keys($this->_request_data[$schemadb_name]) as $name)
        {
            if (isset($this->_request_data[$schemadb_name][$name]->customdata['icon']))
            {
                $icon = $this->_request_data[$schemadb_name][$name]->customdata['icon'];
            }
            else
            {
                $icon = 'stock-icons/16x16/' . $default_icon . '.png';
            }
            $create_url = $name;

            if ($this->_request_data['parent_group'])
            {
                $create_url = $this->_request_data['parent_group'] . '/' . $create_url;
            }
            else if ($schemadb_name == 'schemadb_group')
            {
                $create_url = '0/' . $create_url;
            }

            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => $prefix . "create/{$create_url}/",
                    MIDCOM_TOOLBAR_LABEL => sprintf
                    (
                        $this->_l10n_midcom->get('create %s'),
                        $this->_l10n->get($this->_request_data[$schemadb_name][$name]->description)
                    ),
                    MIDCOM_TOOLBAR_ICON => $icon,
                    MIDCOM_TOOLBAR_ENABLED => $allowed,
                )
            );
        }
    }

    private function _list_group_products()
    {
        $product_qb = new org_openpsa_qbpager('org_openpsa_products_product_dba', 'org_openpsa_products_product_dba');
        $product_qb->results_per_page = $this->_config->get('products_per_page');

        if (count($this->_request_data['linked_products']) > 0)
        {
            $product_qb->begin_group('OR');
        }

        if (   $this->_request_data['group']
            && $this->_request_data['group']->orgOpenpsaObtype == org_openpsa_products_product_group_dba::TYPE_SMART)
        {
            // Smart group, query products by stored constraints
            $constraints = $this->_request_data['group']->list_parameters('org.openpsa.products:constraints');
            if (empty($constraints))
            {
                $product_qb->add_constraint('productGroup', '=', $this->_request_data['parent_group']);
            }

            $reflector = new midgard_reflection_property('org_openpsa_products_product');

            foreach ($constraints as $constraint_string)
            {
                $constraint_members = explode(',', $constraint_string);
                if (count($constraint_members) != 3)
                {
                    throw new midcom_error("Invalid constraint '{$constraint_string}'");
                }

                // Reflection is needed here for safety
                $field_type = $reflector->get_midgard_type($constraint_members[0]);
                switch ($field_type)
                {
                    case 4:
                        throw new midcom_error("Invalid constraint: '{$constraint_members[0]}' is not a Midgard property");
                    case MGD_TYPE_INT:
                        $constraint_members[2] = (int) $constraint_members[2];
                        break;
                    case MGD_TYPE_FLOAT:
                        $constraint_members[2] = (float) $constraint_members[2];
                        break;
                    case MGD_TYPE_BOOLEAN:
                        $constraint_members[2] = (boolean) $constraint_members[2];
                        break;
                }
                $product_qb->add_constraint($constraint_members[0], $constraint_members[1], $constraint_members[2]);
            }
        }
        else if ($this->_request_data['handler_id'] == 'list_intree')
        {
            $product_qb->add_constraint('productGroup', '=', $this->_request_data['parent_category_id']);
        }
        else if ($this->_request_data['handler_id'] == 'listall')
        {
            $categories_qb = org_openpsa_products_product_group_dba::new_query_builder();
            $categories_qb->add_constraint('up', '=', $this->_request_data['group']->id);
            $categories = $categories_qb->execute();
            for ($i = 0; $i < count($categories); $i++)
            {
                $categories_in[$i] = $categories[$i]->id;
            }

            $product_qb->add_constraint('productGroup', 'IN', $categories_in);
        }
        else
        {
            $product_qb->add_constraint('productGroup', '=', $this->_request_data['parent_group']);
        }
        if (count($this->_request_data['linked_products']) > 0)
        {
            $product_qb->add_constraint('id', 'IN', $this->_request_data['linked_products']);
            $product_qb->end_group();
        }

        // This should be a helper function, same functionality, but with different config-parameter is used in /handler/product/search.php
        foreach ($this->_config->get('products_listing_order') as $ordering)
        {
            $this->_add_ordering($product_qb, $ordering);
        }

        if ($this->_config->get('enable_scheduling'))
        {
            $product_qb->add_constraint('start', '<=', time());
            $product_qb->begin_group('OR');
            /*
             * List products that either have no defined end-of-market dates
             * or are still in market
             */
            $product_qb->add_constraint('end', '=', 0);
            $product_qb->add_constraint('end', '>=', time());
            $product_qb->end_group();
        }

        $this->_request_data['products'] = $product_qb->execute();

        $this->_request_data['products_qb'] =& $product_qb;
    }

    /**
     * This function does the output.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_list($handler_id, array &$data)
    {
        if ($data['group'])
        {
            if ($data['controller'])
            {
                $data['view_group'] = $data['controller']->get_content_html();
            }
            else
            {
                $data['view_group'] = $data['datamanager_group']->get_content_html();
            }
        }

        $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

        if (   count($data['groups']) >= 1
            && (   count($data['products']) == 0
                || $this->_config->get('listing_primary') == 'groups'))
        {
            if ($this->_config->get('disable_subgroups_on_frontpage') !== true)
            {
                midcom_show_style('group_header');

                $groups_counter = 0;
                $data['groups_count'] = count($data['groups']);

                midcom_show_style('group_subgroups_header');

                foreach ($data['groups'] as $group)
                {
                    $groups_counter++;
                    $data['groups_counter'] = $groups_counter;

                    $data['group'] = $group;
                    if (! $data['datamanager_group']->autoset_storage($group))
                    {
                        debug_add("The datamanager for group #{$group->id} could not be initialized, skipping it.");
                        debug_print_r('Object was:', $group);
                        continue;
                    }
                    $data['view_group'] = $data['datamanager_group']->get_content_html();

                    if ($group->code)
                    {
                        if (isset($data["parent_category"]))
                        {
                            $data['view_group_url'] = "{$prefix}" . $data["parent_category"] . "/{$group->code}/";
                        }
                        else
                        {
                            $data['view_group_url'] = "{$prefix}{$group->code}/";
                        }
                    }
                    else
                    {
                        $data['view_group_url'] = "{$prefix}{$group->guid}/";
                    }

                    midcom_show_style('group_subgroups_item');
                }

                midcom_show_style('group_subgroups_footer');
                midcom_show_style('group_footer');
            }
        }
        else if (count($data['products']) > 0)
        {
            midcom_show_style('group_header');

            $products_counter = 0;
            $data['products_count'] = count($data['products']);

            midcom_show_style('group_products_grid');
            midcom_show_style('group_products_footer');
            midcom_show_style('group_footer');
        }
        else
        {
            midcom_show_style('group_empty');
        }
    }

    /**
     * Helper, updates the context so that we get a complete breadcrumb line towards the current
     * location.
     */
    private function _update_breadcrumb_line()
    {
        $tmp = Array();

        $group = $this->_request_data['group'];
        $root_group = $this->_config->get('root_group');

        if (!$group)
        {
            return false;
        }

        $parent = $group;

        while ($parent)
        {
            $group = $parent;

            if ($group->guid === $root_group)
            {
                break;
            }

            if ($group->code)
            {
                $url = "{$group->code}/";
            }
            else
            {
                $url = "{$group->guid}/";
            }


            $tmp[] = Array
            (
                MIDCOM_NAV_URL => $url,
                MIDCOM_NAV_NAME => $group->title,
            );
            $parent = $group->get_parent();
        }

        // If navigation is configured to display product groups, remove the lowest level
        // parent to prevent duplicate entries in breadcrumb display
        if (   $this->_config->get('display_navigation')
            && isset($tmp[count($tmp) - 1]))
        {
            unset($tmp[count($tmp) - 1]);
        }

        $tmp = array_reverse($tmp);
        midcom_core_context::get()->set_custom_key('midcom.helper.nav.breadcrumb', $tmp);
    }
}
?>