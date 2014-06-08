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
 * @see midcom_baseclasses_components_handler
 * @package org.openpsa.products
 */
class org_openpsa_products_handler_product_latest extends midcom_baseclasses_components_handler
{
    private function _list_products($limit = 5, $product_group = '')
    {
        $product_qb = new org_openpsa_qbpager('org_openpsa_products_product_dba', 'latest_products');
        $this->_request_data['product_qb'] = $product_qb;
        $product_qb->results_per_page = $limit;
        $product_qb->set_limit($limit);
        $product_qb->add_order('metadata.published', 'DESC');

        if ($product_group != '')
        {
            $group_qb = org_openpsa_products_product_group_dba::new_query_builder();
            $group_qb->add_constraint('code', '=', $product_group);
            $groups = $group_qb->execute();
            if (count($groups) == 0)
            {
                return false;
                // No matching group
            }
            $categories_mc = org_openpsa_products_product_group_dba::new_collector('up', $groups[0]->id);
            $categories = $categories_mc->get_values('id');

            if (count($categories) == 0)
            {
                /* No matching categories belonging to this group
                 * So we can search for the application using only
                 * this group id
                 */
                $product_qb->add_constraint('productGroup', 'INTREE', $groups[0]->id);
            }
            else
            {
                $product_qb->add_constraint('productGroup', 'IN', $categories);
            }
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
        $this->_request_data['product_group'] = $product_group;
    }

    /**
     * The handler for the group_list article.
     *
     * @param mixed $handler_id the array key from the request array
     * @param array $args the arguments given to the handler
     * @param array &$data The local request data.
     */
    public function _handler_updated($handler_id, array $args, array &$data)
    {
        if ($handler_id == 'updated_products_intree')
        {
            $product_group = $args[0];
            $show_products = (int) $args[1];
        }
        else
        {
            $show_products = (int) $args[0];
            $product_group = '';
        }
        $this->_list_products($show_products, $product_group);

        // Prepare datamanager
        $data['datamanager_product'] = new midcom_helper_datamanager2_datamanager($data['schemadb_product']);
    }

    /**
     * This function does the output.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_updated($handler_id, array &$data)
    {
        $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

        if (count($data['products']) > 0)
        {
            midcom_show_style('updated_products_header');

            foreach ($data['products'] as $product)
            {
                $data['product'] = $product;
                if (! $data['datamanager_product']->autoset_storage($product))
                {
                    debug_add("The datamanager for product #{$product->id} could not be initialized, skipping it.");
                    debug_print_r('Object was:', $product);
                    continue;
                }
                $data['view_product'] = $data['datamanager_product']->get_content_html();

                if ($product->code)
                {
                    if ($handler_id == 'updated_products_intree')
                    {
                        $data['view_product_url'] = "{$prefix}product/". $data['product_group'] ."/{$product->code}/";
                    }
                    else
                    {
                        $data['view_product_url'] = "{$prefix}product/{$product->code}/";
                    }
                }
                else
                {
                    $data['view_product_url'] = "{$prefix}product/{$product->guid}/";
                }

                midcom_show_style('updated_products_item');
            }

            midcom_show_style('updated_products_footer');
        }

        midcom_show_style('group_footer');
    }

    /**
     * The handler for the group_list article.
     *
     * @param mixed $handler_id the array key from the request array
     * @param array $args the arguments given to the handler
     * @param array &$data The local request data.
     */
    public function _handler_feed($handler_id, array $args, array &$data)
    {
        midcom::get()->cache->content->content_type("text/xml; charset=UTF-8");
        midcom::get()->header("Content-type: text/xml; charset=UTF-8");
        midcom::get()->skip_page_style = true;

        if ($handler_id == 'updated_products_feed_intree')
        {
            $this->_list_products($this->_config->get('show_items_in_feed'), $args[0]);
        }
        else
        {
            $this->_list_products($this->_config->get('show_items_in_feed'));
        }

        // Prepare datamanager
        $data['datamanager_product'] = new midcom_helper_datamanager2_datamanager($data['schemadb_product']);
    }

    /**
     * This function does the output.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_feed($handler_id, array &$data)
    {
        $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

        $data['rss_creator'] = new UniversalFeedCreator();
        $data['rss_creator']->title = $this->_topic->extra;
        $data['rss_creator']->link = $prefix;
        $data['rss_creator']->syndicationURL = "{$prefix}rss.xml";
        $data['rss_creator']->cssStyleSheet = false;

        if (count($data['products']) > 0)
        {
            foreach ($data['products'] as $product)
            {
                $data['product'] = $product;
                if (! $data['datamanager_product']->autoset_storage($product))
                {
                    debug_add("The datamanager for product #{$product->id} could not be initialized, skipping it.");
                    debug_print_r('Object was:', $product);
                    continue;
                }
                $data['view_product'] = $data['datamanager_product']->get_content_html();

                if ($product->code)
                {
                    $data['view_product_url'] = "{$prefix}product/{$product->code}/";
                }
                else
                {
                    $data['view_product_url'] = "{$prefix}product/{$product->guid}/";
                }

                midcom_show_style('feed_products_item');
            }
        }

        $data['rss'] = $data['rss_creator']->createFeed('RSS2.0');
        echo $data['rss'];
    }
}
?>