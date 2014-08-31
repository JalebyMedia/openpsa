<?php
/**
 * @package net.nehmer.static
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * n.n.static link handler
 *
 * @package net.nehmer.static
 */
class net_nehmer_static_handler_link extends midcom_baseclasses_components_handler
implements midcom_helper_datamanager2_interfaces_create
{
    /**
     * The content topic to use
     *
     * @var midcom_db_topic
     */
    private $_content_topic;

    /**
     * The article which has been created
     *
     * @var midcom_db_article
     */
    private $_article;

    /**
     * The article link which has been created
     *
     * @var net_nehmer_static_link_dba
     */
    private $_link;

    /**
     * Maps the content topic from the request data to local member variables.
     */
    public function _on_initialize()
    {
        $this->_content_topic = $this->_request_data['content_topic'];
    }

    public function load_schemadb()
    {
        return midcom_helper_datamanager2_schema::load_database($this->_config->get('schemadb_link'));
    }

    public function get_schema_defaults()
    {
        $defaults = array();
        if (isset($_GET['article']))
        {
            $defaults['article'] = $_GET['article'];
        }
        else
        {
            $defaults['topic'] = $this->_topic->id;
        }
        return $defaults;
    }

    /**
     * DM2 creation callback, binds to the current content topic.
     */
    public function &dm2_create_callback (&$controller)
    {
        $this->_link = new net_nehmer_static_link_dba();
        $this->_link->topic = $this->_topic->id;

        if (!$this->_link->create())
        {
            debug_print_r('We operated on this object:', $this->_link);
            throw new midcom_error('Failed to create a new article. Last Midgard error was: '. midcom_connection::get_error_string());
        }

        return $this->_link;
    }

    /**
     * Displays an article edit view.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_create($handler_id, array $args, array &$data)
    {
        $this->_content_topic->require_do('midgard:create');

        if (!$this->_config->get('enable_article_links'))
        {
            throw new midcom_error_notfound('Article linking disabled');
        }

        $data['controller'] = $this->get_controller('create');

        switch ($data['controller']->process_form())
        {
            case 'save':
                $this->_article = new midcom_db_article($this->_link->article);
                return new midcom_response_relocate("{$this->_article->name}/");

            case 'cancel':
                return new midcom_response_relocate('');
        }

        $title = sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get('article link'));
        midcom::get()->head->set_pagetitle("{$this->_topic->extra}: {$title}");
        $this->add_breadcrumb("create/link/", sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get('article link')));
    }

    /**
     * Shows the loaded article.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_create ($handler_id, array &$data)
    {
        midcom_show_style('admin-create-link');
    }
}
