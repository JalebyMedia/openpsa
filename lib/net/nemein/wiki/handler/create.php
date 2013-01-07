<?php
/**
 * @package net.nemein.wiki
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Wikipage creation handler
 *
 * @package net.nemein.wiki
 */
class net_nemein_wiki_handler_create extends midcom_baseclasses_components_handler
implements midcom_helper_datamanager2_interfaces_create
{
    /**
     * Wiki word we're creating page for
     *
     * @var string
     */
    private $_wikiword = '';

    /**
     * The wikipage we're creating
     *
     * @var net_nemein_wiki_wikipage
     */
    private $_page = null;

    /**
     * The schema to use for the new page.
     *
     * @var string
     */
    private $_schema = 'default';

    public function load_schemadb()
    {
        return $this->_request_data['schemadb'];
    }

    public function get_schema_name()
    {
        return $this->_schema;
    }

    public function get_schema_defaults()
    {
        $defaults = array();
        $defaults['title'] = $this->_wikiword;
        return $defaults;
    }

    /**
     * DM2 creation callback, binds to the current content topic.
     */
    public function & dm2_create_callback (&$controller)
    {
        $this->_page = new net_nemein_wiki_wikipage();
        $this->_page->topic = $this->_topic->id;
        $this->_page->title = $this->_wikiword;
        $this->_page->author = midcom_connection::get_user();

        // We can clear the session now
        $this->_request_data['session']->remove('wikiword');

        if (! $this->_page->create())
        {
            debug_print_r('We operated on this object:', $this->_page);
            throw new midcom_error('Failed to create a new page. Last Midgard error was: '. midcom_connection::get_error_string());
        }

        $this->_page = new net_nemein_wiki_wikipage($this->_page->id);

        return $this->_page;
    }

    private function _check_unique_wikiword($wikiword)
    {
        $resolver = new net_nemein_wiki_resolver($this->_topic->id);
        $resolved = $resolver->path_to_wikipage($wikiword, true, true);

        if (!empty($resolved['latest_parent']))
        {
            $to_node =& $resolved['latest_parent'];
        }
        else
        {
            $to_node =& $resolved['folder'];
        }
        $created_page = false;
        switch (true)
        {
            case (strstr($resolved['remaining_path'], '/')):
                // One or more namespaces left, find first, create it and recurse
                $paths = explode('/', $resolved['remaining_path']);
                $folder_title = array_shift($paths);
                $topic = new midcom_db_topic();
                $topic->up = $to_node[MIDCOM_NAV_ID];
                $topic->extra = $folder_title;
                $topic->title = $folder_title;
                $generator = midcom::get('serviceloader')->load('midcom_core_service_urlgenerator');
                $topic->name = $generator->from_string($folder_title);
                $topic->component = 'net.nemein.wiki';
                if (!$topic->create())
                {
                    throw new midcom_error("Could not create wiki namespace '{$folder_title}', last Midgard error was: " . midcom_connection::get_error_string());
                }
                // refresh
                $topic = new midcom_db_topic($topic->id);

                // See if we have article with same title in immediate parent
                $qb = net_nemein_wiki_wikipage::new_query_builder();
                $qb->add_constraint('title', '=', $folder_title);
                $qb->add_constraint('topic', '=', $topic->up);
                $results = $qb->execute();

                if (   is_array($results)
                    && count($results) == 1)
                {
                    $article =& $results[0];
                    $article->name = 'index';
                    $article->topic = $topic->id;
                    if (!$article->update())
                    {
                        // Could not move article, do something ?
                    }
                }
                else
                {
                    $created_page = net_nemein_wiki_viewer::initialize_index_article($topic);
                    if (!$created_page)
                    {
                        // Could not create index
                        $topic->delete();
                        throw new midcom_error("Could not create index for new topic, errstr: " . midcom_connection::get_error_string());
                    }
                }
                // We have created a new topic, now recurse to create the rest of the path.
                return $this->_check_unique_wikiword($wikiword);
                break;
            case (is_object($resolved['wikipage'])):
                // Page exists
                throw new midcom_error('Wiki page with that name already exists.');
                break;
            default:
                // No more namespaces left, create the page to latest parent
                if ($to_node[MIDCOM_NAV_ID] != $this->_topic->id)
                {
                    // Last parent is not this topic, redirect there
                    $wikiword_url = rawurlencode($resolved['remaining_path']);
                    midcom::get()->relocate($to_node[MIDCOM_NAV_FULLURL] . "create/{$this->_schema}?wikiword={$wikiword_url}");
                    // This will exit()
                }
                break;
        }
        return true;
    }

    /**
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_create($handler_id, array $args, array &$data)
    {
        // Initialize sessioning first
        $data['session'] = new midcom_services_session();

        if (!array_key_exists('wikiword', $_GET))
        {
            if (!$data['session']->exists('wikiword'))
            {
                throw new midcom_error_notfound('No wiki word given');
            }
            else
            {
                $this->_wikiword = $data['session']->get('wikiword');
            }
        }
        else
        {
            $this->_wikiword = $_GET['wikiword'];
            $data['session']->set('wikiword', $this->_wikiword);
        }

        $this->_topic->require_do('midgard:create');

        if ($handler_id == 'create_by_word_schema')
        {
            $this->_schema = $args[0];
        }
        else
        {
            $this->_schema = $this->_config->get('default_schema');
        }

        if (!array_key_exists($this->_schema, $data['schemadb']))
        {
            throw new midcom_error_notfound('Schema ' . $this->_schema . ' not found in schemadb');
        }

        $this->_check_unique_wikiword($this->_wikiword);

        $data['controller'] = $this->get_controller('create');

        switch ($data['controller']->process_form())
        {
            case 'save':
                // Reindex the article
                $indexer = midcom::get('indexer');
                net_nemein_wiki_viewer::index($data['controller']->datamanager, $indexer, $this->_topic);

                midcom::get('uimessages')->add($this->_l10n->get('net.nemein.wiki'), sprintf($this->_l10n->get('page %s added'), $this->_wikiword), 'ok');

                return new midcom_response_relocate("{$this->_page->name}/");

            case 'cancel':
                return new midcom_response_relocate('');
        }

        $data['view_title'] = sprintf($this->_request_data['l10n']->get('create wikipage %s'), $this->_wikiword);
        midcom::get('head')->set_pagetitle($data['view_title']);
        $data['preview_mode'] = false;

        $this->add_breadcrumb
        (
            "create/?wikiword=" . rawurlencode($this->_wikiword),
            sprintf($this->_l10n->get('create wikipage %s'), $this->_wikiword)
        );

        // Set the help object in the toolbar
        $this->_view_toolbar->add_help_item('markdown', 'net.nemein.wiki');
    }

    /**
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_create($handler_id, array &$data)
    {
        midcom_show_style('view-wikipage-edit');
    }
}
?>