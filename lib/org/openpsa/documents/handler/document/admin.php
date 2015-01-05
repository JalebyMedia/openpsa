<?php
/**
 * @package org.openpsa.documents
 * @author Nemein Oy http://www.nemein.com/
 * @copyright Nemein Oy http://www.nemein.com/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * org.openpsa.documents document handler and viewer class.
 *
 * @package org.openpsa.documents
 */
class org_openpsa_documents_handler_document_admin extends midcom_baseclasses_components_handler
{
    /**
     * The document we're working with (if any).
     *
     * @var org_openpsa_documents_documen_dba
     */
    private $_document = null;

    /**
     * The Controller of the document used for creating or editing
     *
     * @var midcom_helper_datamanager2_controller_simple
     */
    private $_controller = null;

    /**
     * The schema database in use, available only while a datamanager is loaded.
     *
     * @var Array
     */
    private $_schemadb = null;

    /**
     * The schema to use for the new document.
     *
     * @var string
     */
    private $_schema = 'default';

    public function _on_initialize()
    {
        midcom::get()->auth->require_valid_user();
        $this->_schemadb = midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_document'));
    }

    /**
     * Internal helper, loads the controller for the current document. Any error triggers a 500.
     */
    private function _load_edit_controller()
    {
        $this->_controller = midcom_helper_datamanager2_controller::create('simple');
        $this->_controller->schemadb =& $this->_schemadb;
        $this->_controller->set_storage($this->_document, $this->_schema);
        if (! $this->_controller->initialize())
        {
            throw new midcom_error( "Failed to initialize a DM2 controller instance for document {$this->_document->id}.");
        }
    }

    private function _load_document($guid)
    {
        $document = new org_openpsa_documents_document_dba($guid);

        // if the document doesn't belong to the current topic, we don't
        // show it, because otherwise folder-based permissions would be useless
        if ($document->topic != $this->_topic->id)
        {
            throw new midcom_error_notfound("The document '{$guid}' could not be found in this folder.");
        }

        return $document;
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_edit($handler_id, array $args, array &$data)
    {
        $this->_document = $this->_load_document($args[0]);
        $this->_document->require_do('midgard:update');

        $this->_load_edit_controller();

        if (   $data['enable_versioning']
            && !empty($_POST))
        {
            $this->_backup_attachment();
        }

        switch ($this->_controller->process_form())
        {
            case 'save':
                // TODO: Update the URL name?

                // Update the Index
                $indexer = new org_openpsa_documents_midcom_indexer($this->_topic);
                $indexer->index($this->_controller->datamanager);

                $prefix = '';
                if ($this->_document->topic != $this->_topic->id)
                {
                    $nap = new midcom_helper_nav();
                    $node = $nap->get_node($this->_document->topic);
                    $prefix = $node[MIDCOM_NAV_ABSOLUTEURL];
                }

                return new midcom_response_relocate($prefix  . "document/" . $this->_document->guid . "/");

            case 'cancel':
                return new midcom_response_relocate("document/" . $this->_document->guid . "/");
        }

        $this->_request_data['controller'] = $this->_controller;

        midcom::get()->head->set_pagetitle(sprintf($this->_l10n_midcom->get('edit %s'), $this->_document->title));

        // Add toolbar items
        org_openpsa_helpers::dm2_savecancel($this);
        $this->bind_view_to_object($this->_document, $this->_controller->datamanager->schema->name);

        $this->add_breadcrumb("document/{$this->_document->guid}/", $this->_document->title);
        $this->add_breadcrumb("", sprintf($this->_l10n_midcom->get('edit %s'), $this->_l10n->get('document')));
    }

    /**
     * Handle versioning of the attachment
     *
     * @todo Move this to the DBA wrapper class when DM datatype_blob behaves better
     */
    private function _backup_attachment()
    {
        // First, look at post data (from in-form replace/delete buttons)
        if (!empty($_POST['document']))
        {
            foreach (array_keys($_POST['document']) as $key)
            {
                if (    strpos($key, '_delete')
                    || (    strpos($key, '_upload')
                        && !strpos($key, 'new_upload')))
                {
                    $this->_document->backup_version();
                    return;
                }
            }
        }

        // If nothing is found, try looking in quickform (regular form submission)
        $group = $this->_controller->formmanager->form->getElement('document');
        foreach ($group->getElements() as $element)
        {
            if (   preg_match('/e_exist_.+?_file$/', $element->getName())
                && $element->isUploadedFile())
            {
                $this->_document->backup_version();
                return;
            }
        }
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_edit($handler_id, array &$data)
    {
        midcom_show_style('show-document-edit');
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_delete($handler_id, array $args, array &$data)
    {
        $this->_document = $this->_load_document($args[0]);
        $workflow = new midcom\workflow\delete($this->_document);

        if ($workflow->run())
        {
            $indexer = midcom::get()->indexer;
            $indexer->delete($this->_document->guid);
            return new midcom_response_relocate('');
        }
        return new midcom_response_relocate("document/" . $this->_document->guid . "/");
    }
}
