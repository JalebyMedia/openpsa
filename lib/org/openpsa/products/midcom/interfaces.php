<?php
/**
 * @package org.openpsa.products
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * @package org.openpsa.products
 */
class org_openpsa_products_interface extends midcom_baseclasses_components_interface
implements midcom_services_permalinks_resolver
{
    /**
     * @inheritdoc
     */
    public function resolve_object_link(midcom_db_topic $topic, midcom_core_dbaobject $object)
    {
        if ($object instanceof org_openpsa_products_product_dba)
        {
            return $this->_resolve_product($object, $topic);
        }
        if ($object instanceof org_openpsa_products_product_group_dba)
        {
            return $this->_resolve_productgroup($object, $topic);
        }
        return null;
    }

    private function _resolve_productgroup($product_group, $topic)
    {
        $intree = false;
        $real_config = new midcom_helper_configuration($topic, 'org.openpsa.products');

        if (   $real_config->get('root_group') != null
            && $real_config->get('root_group') != 0)
        {
            $root_group = new org_openpsa_products_product_group_dba($real_config->get('root_group'));
            if ($root_group->id == $product_group->id)
            {
                $intree = true;
            }
            else
            {
                $qb_intree = org_openpsa_products_product_group_dba::new_query_builder();
                $qb_intree->add_constraint('up', 'INTREE', $root_group->id);
                $qb_intree->add_constraint('id', '=', $product_group->id);

                $intree = ($qb_intree->count() > 0);
            }

            if ($intree)
            {
                if ($product_group->code)
                {
                    return "{$product_group->code}/";
                }
                return "{$product_group->guid}/";
            }
        }
        else
        {
            if ($product_group->code)
            {
                return "{$product_group->code}/";
            }
            return "{$product_group->guid}/";
        }
    }

    private function _resolve_product($product, $topic)
    {
        if (!$product->productGroup)
        {
            return null;
        }
        $real_config = new midcom_helper_configuration($topic, 'org.openpsa.products');

        if (   $real_config->get('root_group') != null
            && $real_config->get('root_group') != 0)
        {
            $root_group = new org_openpsa_products_product_group_dba($real_config->get('root_group'));
            if ($root_group->id != $product->productGroup)
            {
                $qb_intree = org_openpsa_products_product_group_dba::new_query_builder();
                $qb_intree->add_constraint('up', 'INTREE', $root_group->id);
                $qb_intree->add_constraint('id', '=', $product->productGroup);

                if ($qb_intree->count() == 0)
                {
                    return null;
                }
            }

            $category_qb = org_openpsa_products_product_group_dba::new_query_builder();
            $category_qb->add_constraint('id', '=', $product->productGroup);
            $category = $category_qb->execute_unchecked();
            //Check if the product is in a nested category.
            if (!empty($category[0]->up))
            {
                $parent_category_qb = org_openpsa_products_product_group_dba::new_query_builder();
                $parent_category_qb->add_constraint('id', '=', $category[0]->up);
                $parent_category = $parent_category_qb->execute_unchecked();
                if (!empty($parent_category[0]->code))
                {
                    return "product/{$parent_category[0]->code}/{$product->code}/";
                }
            }
        }
        if ($product->code)
        {
            return "product/{$product->code}/";
        }

        return "product/{$product->guid}/";
    }

    /**
     * Iterate over all articles and create index record using the datamanager indexer
     * method.
     */
    public function _on_reindex($topic, $config, &$indexer)
    {
        if (   !$config->get('index_products')
            && !$config->get('index_groups'))
        {
            debug_add("No indexing to groups and products, skipping", MIDCOM_LOG_WARN);
            return true;
        }
        $dms = array();
        $schemadb_group = midcom_helper_datamanager2_schema::load_database($config->get('schemadb_group'));
        $dms['group'] = new midcom_helper_datamanager2_datamanager($schemadb_group);

        $schemadb_product = midcom_helper_datamanager2_schema::load_database($config->get('schemadb_product'));
        $dms['product'] = new midcom_helper_datamanager2_datamanager($schemadb_product);

        $qb = org_openpsa_products_product_group_dba::new_query_builder();
        $topic_root_group_guid = $topic->get_parameter('org.openpsa.products', 'root_group');
        if (!mgd_is_guid($topic_root_group_guid))
        {
            $qb->add_constraint('up', '=', 0);
        }
        else
        {
            $root_group = new org_openpsa_products_product_group_dba($topic_root_group_guid);
            $qb->add_constraint('id', '=', $root_group->id);
        }
        $root_groups = $qb->execute();
        foreach ($root_groups as $group)
        {
            $this->_on_reindex_tree_iterator($indexer, $dms, $topic, $group, $config);
        }

        return true;
    }

    public function _on_reindex_tree_iterator(&$indexer, array $dms, $topic, $group, $config)
    {
        if ($dms['group']->autoset_storage($group))
        {
            if ($config->get('index_groups'))
            {
                org_openpsa_products_viewer::index($dms['group'], $indexer, $topic, $config);
            }
        }
        else
        {
            debug_add("Warning, failed to initialize datamanager for product group {$group->id}. Skipping it.", MIDCOM_LOG_WARN);
        }

        if ($config->get('index_products'))
        {
            $qb_products = org_openpsa_products_product_dba::new_query_builder();
            $qb_products->add_constraint('productGroup', '=', $group->id);
            $products = $qb_products->execute();

            foreach ($products as $product)
            {
                if (!$dms['product']->autoset_storage($product))
                {
                    debug_add("Warning, failed to initialize datamanager for product {$product->id}. Skipping it.", MIDCOM_LOG_WARN);
                    continue;
                }
                org_openpsa_products_viewer::index($dms['product'], $indexer, $topic, $config);
            }
        }

        $subgroups = array();
        $qb_groups = org_openpsa_products_product_group_dba::new_query_builder();
        $qb_groups->add_constraint('up', '=', $group->id);
        $subgroups = $qb_groups->execute();

        foreach ($subgroups as $subgroup)
        {
            $this->_on_reindex_tree_iterator($indexer, $dms, $topic, $subgroup, $config);
        }

        return true;
    }
}
