<?php
/**
 * @package org.openpsa.documents
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.documents search handler and viewer class.
 *
 * @package org.openpsa.documents
 *
 */
class org_openpsa_documents_handler_search extends midcom_baseclasses_components_handler
{
    private $_datamanagers;

    public function _on_initialize()
    {
        $_MIDCOM->load_library('midcom.helper.datamanager2');
        $schema = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_document'));
        $this->_datamanagers['document'] = new midcom_helper_datamanager2_datamanager($schema);
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param Array $args The argument list.
     * @param Array &$data The local request data.
     */
    public function _handler_search($handler_id, $args, &$data)
    {
        $this->_request_data['results'] = array();
        if (array_key_exists('query', $_GET))
        {
            // Figure out where we are
            $nap = new midcom_helper_nav();
            $node = $nap->get_node($nap->get_current_node());

            // Instantiate indexer
            $indexer = $_MIDCOM->get_service('indexer');

            // Add the search parameters
            $query = $_GET['query'];
            $query .= " AND __TOPIC_URL:\"{$node[MIDCOM_NAV_FULLURL]}*\"";
            $query .= " AND __COMPONENT:org.openpsa.documents";
            // TODO: Metadata support

            // Run the search
            $this->_request_data['results'] = $indexer->query($query, null);
        }

        $this->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.documents/layout.css");

        $this->_populate_toolbar();
    }

    /**
     * Helper that adds toolbar items
     */
    private function _populate_toolbar()
    {
        if ($_MIDCOM->auth->can_do('midgard:create', $this->_request_data['directory']))
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => 'document/create/',
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('new document'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new-text.png',
                )
            );
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => 'create/',
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n->get('new directory'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/new-dir.png',
                )
            );
        }
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param mixed &$data The local request data.
     */
    public function _show_search($handler_id, &$data)
    {
        $displayed = 0;
        midcom_show_style('show-search-header');
        if (!empty($this->_request_data['results']))
        {
            midcom_show_style('show-search-results-header');
            foreach ($this->_request_data['results'] as $document)
            {
                try
                {
                    // $obj->RI will contain either document or attachment GUID depending on match,
                    // ->source will always contain the document GUID
                    $data['document'] = new org_openpsa_documents_document_dba($document->source);
                    $this->_datamanagers['document']->autoset_storage($data['document']);
                }
                catch (Exception $e)
                {
                    debug_log($e->getMessage());
                    continue;
                }
                $data['document_dm'] = $this->_datamanagers['document']->get_content_raw();
                $data['document_attachment'] = array_shift($this->_datamanagers['document']->types['document']->attachments_info);
                $data['document_search'] = $document;
                midcom_show_style('show-search-results-item');
                $displayed++;
            }
            midcom_show_style('show-search-results-footer');
        }
        if ($displayed == 0)
        {
            midcom_show_style('show-search-noresults');
        }
        midcom_show_style('show-search-footer');
    }
}
?>