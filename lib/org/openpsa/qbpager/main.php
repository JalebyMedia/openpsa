<?php
/**
 * @package org.openpsa.qbpager
 */

/**
 * Pages QB resultsets
 *
 * @package org.openpsa.qbpager
 */
class org_openpsa_qbpager extends midcom_baseclasses_components_purecode
{
    protected $_midcom_qb = false;
    protected $_midcom_qb_count = false;
    protected $_pager_id = false;
    private $_offset = 0;
    protected $_prefix = '';
    private $_request_data = array();
    private $_current_page = 1;
    public $results_per_page = 25;
    var $count = false;
    private $_count_mode = false;
    var $display_pages = 10;
    public $string_next = 'next';
    public $string_previous = 'previous';

    public function __construct($classname, $pager_id)
    {
        parent::__construct();

        $this->_pager_id = $pager_id;
        $this->_prefix = 'org_openpsa_qbpager_' . $this->_pager_id . '_';
        $this->_prepare_qbs($classname);
    }

    protected function _prepare_qbs($classname)
    {
        $this->_midcom_qb = midcom::get()->dbfactory->new_query_builder($classname);
        // Make another QB for counting, we need to do this to avoid trouble with core internal references system
        $this->_midcom_qb_count = midcom::get()->dbfactory->new_query_builder($classname);
    }

    /**
     * Makes sure we have some absolutely required things properly set
     */
    protected function _sanity_check()
    {
        if (empty($this->_pager_id))
        {
            debug_add('this->_pager_id is not set (needed for distinguishing different instances on same request)', MIDCOM_LOG_WARN);
            return false;
        }
        if ($this->results_per_page < 1)
        {
            debug_add('this->results_per_page is set to ' . $this->results_per_page . ', aborting', MIDCOM_LOG_WARN);
            return false;
        }
        return true;
    }

    /**
     * Check $_REQUEST for variables and sets internal status accordingly
     */
    private function _check_page_vars()
    {
        $page_var = $this->_prefix . 'page';
        $results_var =  $this->_prefix . 'results';
        if (!empty($_REQUEST[$page_var]))
        {
            debug_add("{$page_var} has value: {$_REQUEST[$page_var]}");
            $this->_current_page = (int)$_REQUEST[$page_var];
        }
        if (!empty($_REQUEST[$results_var]))
        {
            debug_add("{$results_var} has value: {$_REQUEST[$results_var]}");
            $this->results_per_page = (int)$_REQUEST[$results_var];
        }
        $this->_offset = ($this->_current_page-1)*$this->results_per_page;
        if ($this->_offset<0)
        {
            $this->_offset = 0;
        }
    }

    /**
     * Get the current page number
     */
    function get_current_page()
    {
        return $this->_current_page;
    }

    /**
     * Fetch all $_GET variables
     */
    private function _get_query_string($page_var, $page_number)
    {
        $query = array($page_var => (int) $page_number);

        foreach ($_GET as $key => $value)
        {
            if ( $key != $page_var && $key != '' )
            {
                $query[$key] = $value;
            }
        }

        return '?' . http_build_query($query);
    }

    /**
     * Displays previous/next selector
     */
    function show_previousnext($acl_checks = false)
    {
        $this->_request_data['prefix'] = $this->_prefix;
        $this->_request_data['current_page'] = $this->_current_page;
        $this->_request_data['page_count'] = $this->count_pages($acl_checks);
        $this->_request_data['results_per_page'] = $this->results_per_page;
        $this->_request_data['offset'] = $this->_offset;
        $this->_request_data['display_pages'] = $this->display_pages;
        //Won't work (wrong scope), so the code is copied below.
        //midcom_show_style('show-pages');
        $data = $this->_request_data;

        //Skip the header in case we only have one page
        if ($data['page_count'] <= 1)
        {
            return;
        }

        //TODO: "showing results (offset)-(offset+limit)
        //TODO: Localizations

        $page_var = $data['prefix'] . 'page';
        echo '<div class="org_openpsa_qbpager_previousnext">';

        if ($data['current_page'] > 1)
        {
            $previous = $data['current_page'] - 1;
            echo "\n<a class=\"previous_page\" href=\"" . $this->_get_query_string($page_var, $previous) . "\" rel=\"prev\">" . $this->_l10n->get($this->string_previous) . "</a>";
        }

        if ($data['current_page'] < $data['page_count'])
        {
            $next = $data['current_page'] + 1;
            echo "\n<a class=\"next_page\" href=\"" . $this->_get_query_string($page_var, $next) . "\" rel=\"next\">" . $this->_l10n->get($this->string_next) . "</a>";
        }

        echo "\n</div>\n";
    }

    public function get_pages($acl_checks = false)
    {
        $pages = array();
        $this->_request_data['prefix'] = $this->_prefix;
        $this->_request_data['current_page'] = $this->_current_page;
        $this->_request_data['page_count'] = $this->count_pages($acl_checks);
        $this->_request_data['results_per_page'] = $this->results_per_page;
        $this->_request_data['offset'] = $this->_offset;
        $this->_request_data['display_pages'] = $this->display_pages;

        if ($this->_request_data['page_count'] < 1)
        {
            return $pages;
        }

        $data = $this->_request_data;
        $page_var = $data['prefix'] . 'page';

        $display_start = max(($data['current_page'] - ceil($data['display_pages'] / 2)), 1);
        $display_end = min(($data['current_page'] + ceil($data['display_pages'] / 2)), $data['page_count']);

        if ($data['current_page'] > 1)
        {
            $previous = $data['current_page'] - 1;
            if ($previous > 1)
            {
                $pages[] = array
                (
                    'class' => 'first',
                    'href' => $this->_get_query_string($page_var, 1),
                    'rel' => 'prev',
                    'label' => $this->_l10n->get('first'),
                    'number' => 1
                );
            }
            $pages[] = array
            (
                'class' => 'previous',
                'href' => $this->_get_query_string($page_var, $previous),
                'rel' => 'prev',
                'label' => $this->_l10n->get($this->string_previous),
                'number' => $previous
            );
        }
        $page = $display_start - 1;
        while ($page++ < $display_end)
        {
            $href = false;
            if ($page != $data['current_page'])
            {
                $href = $this->_get_query_string($page_var, $page);
            }
            $pages[] = array
            (
                'class' => 'current',
                'href' => $href,
                'rel' => false,
                'label' => $page,
                'number' => $page
            );
        }

        if ($data['current_page'] < $data['page_count'])
        {
            $next = $data['current_page'] + 1;
            $pages[] = array
            (
                'class' => 'next',
                'href' => $this->_get_query_string($page_var, $next),
                'rel' => 'next',
                'label' => $this->_l10n->get($this->string_next),
                'number' => $next
            );

            if ($next < $data['page_count'])
            {
                $pages[] = array
                (
                    'class' => 'last',
                    'href' => $this->_get_query_string($page_var, $data['page_count']),
                    'rel' => 'next',
                    'label' => $this->_l10n->get('last'),
                    'number' => $data['page_count']
                );
            }
        }

        return $pages;
    }

    /**
     * Displays page selector
     */
    function show_pages($acl_checks = false)
    {
        //Won't work (wrong scope), so the code is copied below.
        //midcom_show_style('show-pages');

        $pages = $this->get_pages($acl_checks);
        //Skip the header in case we only have one page
        if (count($pages) <= 1)
        {
            return;
        }

        //TODO: "showing results (offset)-(offset+limit)

        echo '<div class="org_openpsa_qbpager_pages">';

        foreach ($pages as $page)
        {
            if ($page['href'] === false)
            {
                echo "\n<span class=\"" . $page['class'] . "_page\">{$page['label']}</span>";
            }
            else
            {
                $rel = '';
                if ($page['rel'] !== false)
                {
                    $rel = ' rel="' . $page['rel'] . '"';
                }
                echo "\n<a class=\"{$page['class']}_page\" href=\"" . $page['href'] . "\"{$rel}>" . $page['label'] . "</a>";
            }
        }

        echo "\n</div>\n";
    }

    /**
     * Displays page selector as XML
     */
    function show_pages_as_xml($acl_checks = false, $echo = true)
    {
        $pages = $this->get_pages($acl_checks);
        $pages_xml_str = "<pages total=\"" . count($pages) . "\">\n";

        //Skip the header in case we only have one page
        if (count($pages) <= 1)
        {
            $pages_xml_str .= "</pages>\n";
            if ($echo)
            {
                echo $pages_xml_str;
            }
            return $pages_xml_str;
        }

        //TODO: "showing results (offset)-(offset+limit)
        foreach ($pages as $page)
        {
            if ($page['href'] === false)
            {
                echo "\n<page class=\"page_" . $page['class'] . "_page\" number=\"{$page['number']}\" url=\"\"><![CDATA[{$page['label']}]]></page>";
            }
            else
            {
                echo "\n<page class=\"page_{$page['class']}_page\" number=\"{$page['number']}\" url=\"" . $page['href'] . "\"><![CDATA[" . $page['label'] . "]]></page>";
            }
        }

        $pages_xml_str .= "</pages>\n";

        if ($echo)
        {
            echo $pages_xml_str;
        }
        return $pages_xml_str;
    }

    /**
     * Displays page selector as list
     */
    function show_pages_as_list($acl_checks = false)
    {
        $pages = $this->get_pages($acl_checks);

        //Won't work (wrong scope), so the code is copied below.
        //midcom_show_style('show-pages');

        //Skip the header in case we only have one page
        $total_links = count($pages);
        if ($total_links <= 1)
        {
            return;
        }

        //TODO: "showing results (offset)-(offset+limit)
        echo '<div class="org_openpsa_qbpager_pages">';
        echo "\n    <ul>\n";
        foreach ($pages as $i => $page)
        {
            if ($page['class'] == 'next')
            {
                echo "\n<li class=\"separator\"></li>";
                echo "\n<li class=\"page splitter\">...</li>";
            }
            if (   $i > 0
                && $i < $total_links)
            {
                echo "\n<li class=\"separator\"></li>";
            }
            if ($page['href'] === false)
            {
                echo "\n<li class=\"page {$page['class']}\">{$page['label']}</li>";
            }
            else
            {
                echo "\n<li class=\"page {$page['class']}\" onclick=\"window.location='{$page['href']}';\">{$page['label']}</li>";
            }
            if ($page['class'] == 'previous')
            {
                echo "\n<li class=\"page splitter\">...</li>";
                echo "\n<li class=\"separator\"></li>";
            }
        }

        echo "\n    </ul>\n";
        echo "</div>\n";
    }

    /**
     * sets LIMIT and OFFSET for requested page
     */
    protected function _qb_limits($qb)
    {
        $this->_check_page_vars();

        if ($this->_current_page == 'all')
        {
            debug_add("displaying all results");
            return;
        }

        $qb->set_limit($this->results_per_page);
        $qb->set_offset($this->_offset);
        debug_add("set offset to {$this->_offset} and limit to {$this->results_per_page}");
    }

    function execute()
    {
        if (!$this->_sanity_check())
        {
            return false;
        }
        $this->_qb_limits($this->_midcom_qb);
        return $this->_midcom_qb->execute();
    }

    function execute_unchecked()
    {
        if (!$this->_sanity_check())
        {
            return false;
        }
        $this->_qb_limits($this->_midcom_qb);
        return $this->_midcom_qb->execute_unchecked();
    }

    /**
     * Returns number of total pages for query
     *
     * By default returns a number of pages without any ACL checks, checked
     * count is available but is much slower.
     */
    function count_pages($acl_checks = false)
    {
        if (!$this->_sanity_check())
        {
            return false;
        }
        if (!$acl_checks)
        {
            $this->count_unchecked();
        }
        else
        {
            $this->count();
        }
        return ceil($this->count / $this->results_per_page);
    }

    //These two wrapped to prevent their use since the pager needs them internally
    function set_limit($limit)
    {
        debug_add('operation not allowed', MIDCOM_LOG_WARN);
        return false;
    }

    function set_offset($offset)
    {
        debug_add('operation not allowed', MIDCOM_LOG_WARN);
        return false;
    }

    //Rest of supported methods wrapped with extra sanity check
    function add_constraint($param, $op, $val)
    {
        if (!$this->_sanity_check())
        {
            return false;
        }
        $this->_midcom_qb_count->add_constraint($param, $op, $val);
        return $this->_midcom_qb->add_constraint($param, $op, $val);
    }

    function add_order($param, $sort='ASC')
    {
        if (!$this->_sanity_check())
        {
            return false;
        }
        return $this->_midcom_qb->add_order($param, $sort);
    }

    function begin_group($type)
    {
        if (!$this->_sanity_check())
        {
            return false;
        }
        $this->_midcom_qb_count->begin_group($type);
        return $this->_midcom_qb->begin_group($type);
    }

    function end_group()
    {
        if (!$this->_sanity_check())
        {
            return false;
        }
        $this->_midcom_qb_count->end_group();
        return $this->_midcom_qb->end_group();
    }

    function include_deleted()
    {
        $this->_midcom_qb_count->include_deleted();
        return $this->_midcom_qb->include_deleted();
    }

    function count()
    {
        if (!$this->_sanity_check())
        {
            return false;
        }
        if (   !$this->count
            || $this->_count_mode != 'count')
        {
            $this->count = $this->_midcom_qb_count->count();
        }
        $this->_count_mode = 'count';
        return $this->count;
    }

    function count_unchecked()
    {
        if (!$this->_sanity_check())
        {
            return false;
        }
        if (   !$this->count
            || $this->_count_mode != 'count_unchecked')
        {
            $this->count = $this->_midcom_qb_count->count_unchecked();
        }
        $this->_count_mode = 'count_unchecked';
        return $this->count;
    }
}
?>
