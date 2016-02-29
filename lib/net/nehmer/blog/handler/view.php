<?php
/**
 * @package net.nehmer.blog
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Blog index page handler
 *
 * @package net.nehmer.blog
 */
class net_nehmer_blog_handler_view extends midcom_baseclasses_components_handler
{
    /**
     * The content topic to use
     *
     * @var midcom_db_topic
     */
    private $_content_topic = null;

    /**
     * The article to display
     *
     * @var midcom_db_article
     */
    private $_article = null;

    /**
     * The Datamanager of the article to display.
     *
     * @var midcom_helper_datamanager2_datamanager
     */
    private $_datamanager = null;

    /**
     * Simple helper which references all important members to the request data listing
     * for usage within the style listing.
     */
    private function _prepare_request_data()
    {
        $this->_request_data['article'] = $this->_article;
        $this->_request_data['datamanager'] = $this->_datamanager;

        // Populate the toolbar
        if ($this->_article->can_do('midgard:update'))
        {
            $this->_view_toolbar->add_item
            (
                array
                (
                    MIDCOM_TOOLBAR_URL => "edit/{$this->_article->guid}/",
                    MIDCOM_TOOLBAR_LABEL => $this->_l10n_midcom->get('edit'),
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/edit.png',
                    MIDCOM_TOOLBAR_ACCESSKEY => 'e',
                )
            );
        }

        $article = $this->_article;
        if ($this->_article->topic !== $this->_content_topic->id)
        {
            $qb = net_nehmer_blog_link_dba::new_query_builder();
            $qb->add_constraint('topic', '=', $this->_content_topic->id);
            $qb->add_constraint('article', '=', $this->_article->id);
            if ($qb->count() === 1)
            {
                // Get the link
                $results = $qb->execute_unchecked();
                $article = $results[0];
            }
        }
        if ($article->can_do('midgard:delete'))
        {
            $workflow = new midcom\workflow\delete($this->_article);
            $this->_view_toolbar->add_item($workflow->get_button("delete/{$this->_article->guid}/"));
        }
    }

    /**
     * Maps the content topic from the request data to local member variables.
     */
    public function _on_initialize()
    {
        $this->_content_topic = $this->_request_data['content_topic'];
    }

    /**
     * Can-Handle check against the article name. We have to do this explicitly
     * in can_handle already, otherwise we would hide all subtopics as the request switch
     * accepts all argument count matches unconditionally.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     * @return boolean True if the request can be handled, false otherwise.
     */
    public function _can_handle_view ($handler_id, array $args, array &$data)
    {
        $qb = midcom_db_article::new_query_builder();
        net_nehmer_blog_viewer::article_qb_constraints($qb, $data, $handler_id);

        $qb->begin_group('OR');
            $qb->add_constraint('name', '=', $args[0]);
            $qb->add_constraint('guid', '=', $args[0]);
        $qb->end_group();
        $articles = $qb->execute();
        if (count($articles) > 0)
        {
            $this->_article = $articles[0];
            return true;
        }

        return false;
    }

    /**
     * Handle actual article display
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array $args The argument list.
     * @param array &$data The local request data.
     */
    public function _handler_view ($handler_id, array $args, array &$data)
    {
        if ($handler_id == 'view-raw')
        {
            midcom::get()->skip_page_style = true;
        }

        $this->_load_datamanager();

        if ($this->_config->get('enable_ajax_editing'))
        {
            $this->_request_data['controller'] = midcom_helper_datamanager2_controller::create('ajax');
            $this->_request_data['controller']->schemadb =& $this->_request_data['schemadb'];
            $this->_request_data['controller']->set_storage($this->_article);
            $this->_request_data['controller']->process_ajax();
        }

        if ($this->_config->get('comments_enable'))
        {
            $comments_node = $this->_seek_comments();
            if ($comments_node)
            {
                $this->_request_data['comments_url'] = $comments_node[MIDCOM_NAV_RELATIVEURL] . "comment/{$this->_article->guid}";
                if (   $this->_topic->can_do('midgard:update')
                    && $this->_topic->can_do('net.nehmer.comments:moderation'))
                {
                    net_nehmer_comments_viewer::add_head_elements();
                }
            }
            // TODO: Should we tell admin to create a net.nehmer.comments folder?
        }

        $this->add_breadcrumb($this->_master->get_url($this->_article), $this->_article->title);

        $this->_prepare_request_data();

        if (   $this->_config->get('enable_article_links')
            && $this->_content_topic->can_do('midgard:create'))
        {
            $this->_view_toolbar->add_item(
                array
                (
                    MIDCOM_TOOLBAR_LABEL => sprintf($this->_l10n_midcom->get('create %s'), $this->_l10n->get('article link')),
                    MIDCOM_TOOLBAR_URL => "create/link/?article={$this->_article->id}",
                    MIDCOM_TOOLBAR_ICON => 'stock-icons/16x16/attach.png',
                )
            );
        }

        $this->bind_view_to_object($this->_article, $this->_datamanager->schema->name);
        midcom::get()->metadata->set_request_metadata($this->_article->metadata->revised, $this->_article->guid);
        midcom::get()->head->set_pagetitle("{$this->_topic->extra}: {$this->_article->title}");
    }

    /**
     * Internal helper, loads the datamanager for the current article. Any error triggers a 500.
     */
    private function _load_datamanager()
    {
        $this->_datamanager = new midcom_helper_datamanager2_datamanager($this->_request_data['schemadb']);

        if (! $this->_datamanager->autoset_storage($this->_article))
        {
            throw new midcom_error("Failed to create a DM2 instance for article {$this->_article->id}.");
        }
    }

    /**
     * Try to find a comments node (cache results)
     */
    private function _seek_comments()
    {
        if ($this->_config->get('comments_topic'))
        {
            try
            {
                $comments_topic = new midcom_db_topic($this->_config->get('comments_topic'));
            }
            catch (midcom_error $e)
            {
                return false;
            }

            // We got a topic. Make it a NAP node
            $nap = new midcom_helper_nav();
            return $nap->get_node($comments_topic->id);
        }

        // No comments topic specified, autoprobe
        $comments_node = midcom_helper_misc::find_node_by_component('net.nehmer.comments');

        // Cache the data
        if (midcom::get()->auth->request_sudo('net.nehmer.blog'))
        {
            $this->_topic->set_parameter('net.nehmer.blog', 'comments_topic', $comments_node[MIDCOM_NAV_GUID]);
            midcom::get()->auth->drop_sudo();
        }

        return $comments_node;
    }

    /**
     * Shows the loaded article.
     *
     * @param mixed $handler_id The ID of the handler.
     * @param array &$data The local request data.
     */
    public function _show_view ($handler_id, array &$data)
    {
        if ($this->_config->get('enable_ajax_editing'))
        {
            // For AJAX handling it is the controller that renders everything
            $this->_request_data['view_article'] = $this->_request_data['controller']->get_content_html();
        }
        else
        {
            $this->_request_data['view_article'] = $this->_datamanager->get_content_html();
        }

        midcom_show_style('view');
    }
}
