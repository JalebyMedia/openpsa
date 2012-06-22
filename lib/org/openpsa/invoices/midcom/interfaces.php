<?php
/**
 * @package org.openpsa.invoices
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Invoice management MidCOM interface class.
 *
 * @package org.openpsa.invoices
 */
class org_openpsa_invoices_interface extends midcom_baseclasses_components_interface
{
    public function _on_resolve_permalink($topic, $config, $guid)
    {
        try
        {
            $invoice = new org_openpsa_invoices_invoice_dba($guid);
            return "invoice/{$invoice->guid}/";
        }
        catch (midcom_error $e)
        {
            return null;
        }
    }

    /**
     * Handle deletes of "parent" objects
     *
     * @param mixed $object The object triggering the watch
     */
    public function _on_watched_dba_delete($object)
    {
        midcom::get('auth')->request_sudo();
        $qb_billing_data = org_openpsa_invoices_billing_data_dba::new_query_builder();
        $qb_billing_data->add_constraint('linkGuid', '=', $object->guid);
        $result = $qb_billing_data->execute();
        if (count($result) > 0)
        {
            foreach ($result as $billing_data)
            {
                debug_add("Delete billing data with guid:" . $billing_data->guid . " for object with guid:" . $object->guid);
                $billing_data->delete();
            }
        }
        midcom::get('auth')->drop_sudo();
    }

    /**
     * Prepare the indexer client
     */
    public function _on_reindex($topic, $config, &$indexer)
    {
        $qb = org_openpsa_invoices_invoice_dba::new_query_builder();
        $schemadb = midcom_helper_datamanager2_schema::load_database($config->get('schemadb'));

        $indexer = new org_openpsa_invoices_midcom_indexer($topic, $indexer);
        $indexer->add_query('invoices', $qb, $schemadb);

        return $indexer;
    }
}
?>