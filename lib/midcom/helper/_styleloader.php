<?php
/**
 * @package midcom.helper
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This class is responsible for all style management and replaces
 * the old <[...]> syntax. It is instantiated by the MidCOM framework
 * and accessible through the midcom::get('style') object.
 *
 * The method <code>show($style)</code> returns the style element $style for the current
 * component:
 *
 * It checks whether a style path is defined for the current component.
 *
 * - If there is a user defined style path, the element named $style in
 *   this path is returned,
 * - otherwise the element "$style" is taken from the default style of the
 *   current component (/path/to/component/_style/$path).
 *
 * (The default fallback is always the default style, e.g. if $style
 * is not in the user defined style path)
 *
 * To enable cross-style referencing and provide the opportunity to access
 * any style element (not only the style that is set
 * in the current page), "show" can be called with a full qualified style
 * path (like "/mystyle/element1", while the current page's style may be set
 * to "/yourstyle").
 *
 * Note: To make sure sub-styles and elements included in styles are handled
 * correctly, the old style tag <[...]> should not be used anymore,
 * but should be replaced by something like this:
 *
 * <code>
 * <?php midcom_show_style ("elementname"); ?>
 * </code>
 *
 * Style Inheritance
 *
 * The basic path the styleloader follows to find a style element is:
 * 1. Topic style -> if the current topic has a style set
 * 2. Inherited topic style -> if the topic inherits a style from another topic.
 * 3. Site-wide per-component default style -> if defined in MidCOM configuration key styleengine_default_styles
 * 4. Midgard style -> the style of the MidCOM component.
 * 5. The file style. This is usually the elements found in the components style directory.
 *
 * Regarding nr. 4:
 * It is possible to add extra file styles if so is needed for example by a portal component.
 * This is done either using the append/prepend component_style functions or by setting it
 * to another directory by calling (append|prepend)_styledir directly.
 *
 * NB: This cannot happen after the midcom::get()->content() stage in midcom is called,
 * i.e. you cannot change this in another style element or in a _show() function in a component.
 *
 * @todo Document Style Inheritance
 *
 * @package midcom.helper
 */
class midcom_helper__styleloader
{
    /**
     * Current style scope
     *
     * @var array
     */
    private $_scope = array();

    /**
     * Current topic
     *
     * @var midcom_db_topic
     */
    private $_topic;

    /**
     * Default style path
     *
     * @var string
     */
    private $_snippetdir;

    /**
     * Path to file styles.
     * @var array
     */
    var $_filedirs = array();

    /**
     * Context stack
     *
     * @var array
     */
    private $_context = array();

    /**
     * Style element cache
     *
     * @var array
     */
    private $_styles = array();

    /**
     * Default style element cache
     *
     * @var array
     */
    private $_snippets = array();

    /**
     * List of styledirs to handle after componentstyle
     *
     * @var array
     */
    private $_styledirs_append = array();

    /**
     * List of styledirs to handle before componentstyle
     *
     * @var array
     */
    private $_styledirs_prepend = array();

    /**
     * The stack of directories to check for styles.
     */
    var $_styledirs = array();

    /**
     * The actual Midgard style object
     */
    var $object = null;

    /**
     * Data to pass to the style
     *
     * @var array
     */
    public $data;

    /**
     * Returns the path of the style described by $id.
     *
     * @param int $id    Style id to look up path for
     * @return    string Style path
     */
    public function get_style_path_from_id($id)
    {
        static $path_cache = array();
        if (!isset($path_cache[$id]))
        {
            // Construct the path
            $path_parts = array();
            $original_id = $id;

            try
            {
                while (($style = new midcom_db_style($id)))
                {
                    $path_parts[] = $style->name;
                    $id = $style->up;

                    if ($style->up == 0)
                    {
                        // Toplevel style
                        break;
                    }

                    if (   midcom::get('config')->get('styleengine_relative_paths')
                        && $style->up == midcom_connection::get('style'))
                    {
                        // Relative path, stop before going to main Midgard style
                        break;
                    }
                }
            }
            catch (midcom_error $e){}

            $path_parts = array_reverse($path_parts);

            $path_cache[$original_id] = '/' . implode('/', $path_parts);
        }

        return $path_cache[$original_id];
    }

    /**
     * Returns the id of the style described by $path.
     *
     * Note: $path already includes the element name, so $path looks like
     * "/rootstyle/style/style/element".
     *
     * @todo complete documentation
     * @param string $path      The path to retrieve
     * @param int $rootstyle    ???
     * @return    int ID of the matching style or false
     */
    public function get_style_id_from_path($path, $rootstyle = 0)
    {
        static $cached = array();

        if (   midcom::get('config')->get('styleengine_relative_paths')
            && $rootstyle == 0)
        {
            // Relative paths in use, start seeking from under the style used for the Midgard host
            $rootstyle = midcom_connection::get('style');
        }

        if (!isset($cached[$rootstyle]))
        {
            $cached[$rootstyle] = array();
        }
        if (array_key_exists($path, $cached[$rootstyle]))
        {
            return $cached[$rootstyle][$path];
        }

        $path = preg_replace("/^\/(.*)/", "$1", $path); // leading "/"
        $path_array = array_filter(explode('/', $path));

        $current_style = $rootstyle;

        if (count($path_array) == 0)
        {
            $cached[$rootstyle][$path] = false;
            return false;
        }

        foreach ($path_array as $path_item)
        {
            $mc = midgard_style::new_collector('up', $current_style);
            $mc->set_key_property('guid');
            $mc->add_value_property('id');
            $mc->add_constraint('name', '=', $path_item);
            $mc->execute();
            $styles = $mc->list_keys();

            foreach (array_keys($styles) as $style_guid )
            {
                $current_style = $mc->get_subkey($style_guid, 'id');
                midcom::get('cache')->content->register($style_guid);
            }
        }

        if ($current_style != 0)
        {
            $cached[$rootstyle][$path] = $current_style;
            return $current_style;
        }

        $cached[$rootstyle][$path] = false;
        return false;
    }

    /**
     * Returns a style element that matches $name and is in style $id.
     * It also returns an element if it is not in the given style,
     * but in one of its parent styles.
     *
     * @param int $id        The style id to search in.
     * @param string $name    The element to locate.
     * @return string    Value of the found element, or false on failure.
     */
    private function _get_element_in_styletree($id, $name)
    {
        static $cached = array();
        if (!isset($cached[$id]))
        {
            $cached[$id] = array();
        }
        if (array_key_exists($name, $cached[$id]))
        {
            return $cached[$id][$name];
        }

        $element_mc = midgard_element::new_collector('style', $id);
        $element_mc->set_key_property('guid');
        $element_mc->add_value_property('value');
        $element_mc->add_constraint('name', '=', $name);
        $element_mc->execute();
        $elements = $element_mc->list_keys();

        foreach ($elements as $element_guid => $value)
        {
            $value = $element_mc->get_subkey($element_guid, 'value');
            midcom::get('cache')->content->register($element_guid);
            $cached[$id][$name] = $value;
            return $value;
        }


        // No such element on this level, check parents
        $style_mc = midgard_style::new_collector('id', $id);
        $style_mc->set_key_property('guid');
        $style_mc->add_value_property('up');
        $style_mc->execute();
        $styles = $style_mc->list_keys();

        foreach ($styles as $style_guid => $value)
        {
            // FIXME: Should we register this also in the other case
            midcom::get('cache')->content->register($style_guid);

            $up = $style_mc->get_subkey($style_guid, 'up');
            if ($up)
            {
                $value = $this->_get_element_in_styletree($up, $name);
                $cached[$id][$name] = $value;
                return $value;
            }
        }

        $cached[$id][$name] = false;
        return $cached[$id][$name];
    }

    /**
     * Looks for a style element matching $path (either in a user defined style
     * or the default style snippetdir) and displays/evaluates it.
     *
     * @param string $path    The style element to show.
     * @return boolean            True on success, false otherwise.
     */
    function show($path)
    {
        if ($this->_context === array())
        {
            debug_add("Trying to show '{$path}' but there is no context set", MIDCOM_LOG_INFO);
            return false;
        }

        $_element = $path;

        // we have full qualified path to element
        if (preg_match("|(.*)/(.*)|", $path, $matches))
        {
            $_stylepath = $matches[1];
            $_element = $matches[2];
        }

        if (   isset ($_stylepath)
            && $_styleid = $this->get_style_id_from_path($_stylepath))
        {
            array_unshift($this->_scope, $_styleid);
        }

        $_style = $this->_find_element_in_scope($_element);

        if (!$_style)
        {
            $_style = $this->_get_element_from_snippet($_element);
        }

        if ($_style !== false)
        {
            $this->_parse_element($_style, $path);
        }
        else
        {
            debug_add("The element '{$path}' could not be found.", MIDCOM_LOG_INFO);
            return false;
        }

        if (isset($_stylepath))
        {
            array_shift($this->_scope);
        }

        return true;
    }

    /**
     * Looks for a midcom core style element matching $path and displays/evaluates it.
     * This offers a bit reduced functionality and will only look in the DB root style,
     * the theme directory and midcom's style directory, because it has to work even when
     * midcom is not yet fully initialized
     *
     * @param string $path    The style element to show.
     * @return boolean            True on success, false otherwise.
     */
    function show_midcom($path)
    {
        $_element = $path;
        $_style = false;

        $this->_snippetdir = MIDCOM_ROOT . '/midcom/style';
        $context = midcom_core_context::get();
        if (isset($this->_styledirs[$context->id]))
        {
            $styledirs_backup = $this->_styledirs;
        }

        $this->_styledirs[$context->id][0] = $this->_snippetdir;

        try
        {
            $root_topic = $context->get_key(MIDCOM_CONTEXT_ROOTTOPIC);
            if ($root_topic->style)
            {
                $db_style = $this->get_style_id_from_path($root_topic->style);
                if ($db_style)
                {
                    $_style = $this->_get_element_in_styletree($db_style, $_element);
                }
            }
        }
        catch (midcom_error_forbidden $e)
        {
            $e->log();
        }

        if ($_style === false)
        {
            $_style = $this->_get_element_from_snippet($_element);
        }

        if (isset($styledirs_backup))
        {
            $this->_styledirs = $styledirs_backup;
        }

        if ($_style !== false)
        {
            $this->_parse_element($_style, $path);
            return true;
        }
        debug_add("The element '{$path}' could not be found.", MIDCOM_LOG_INFO);
        return false;
    }

    /**
     * Try to find element in current / given scope
     */
    private function _find_element_in_scope($_element)
    {
        if (count($this->_scope) > 0)
        {
            $src = "{$this->_scope[0]}/{$_element}";

            if (array_key_exists($src, $this->_styles))
            {
                return $this->_styles[$src];
            }
            else if ($this->_scope[0] != '')
            {
                if ($_result = $this->_get_element_in_styletree($this->_scope[0], $_element))
                {
                    $this->_styles[$src] = $_result;
                    return $this->_styles[$src];
                }
            }
        }
        return false;
    }

    /**
     * Try to get element from default style snippet
     */
    private function _get_element_from_snippet($_element)
    {
        $src = "{$this->_snippetdir}/{$_element}";
        if (array_key_exists($src, $this->_snippets))
        {
            return $this->_snippets[$src];
        }
        else
        {
            if (midcom::get('config')->get('theme'))
            {
                $content = midcom_helper_misc::get_element_content($_element);
                if ($content)
                {
                    $this->_snippets[$src] = $content;
                    return $content;
                }
            }

            $current_context = midcom_core_context::get()->id;
            foreach ($this->_styledirs[$current_context] as $path)
            {
                $filename = $path .  "/{$_element}.php";
                if (file_exists($filename))
                {
                    $this->_snippets[$filename] = file_get_contents($filename);
                    return $this->_snippets[$filename];
                }
            }
        }
        return false;
    }

    /**
     * This is a bit of a hack to allow &(); tags
     */
    private function _parse_element($_style, $path)
    {
        if (midcom_core_context::get()->has_custom_key('request_data'))
        {
            $data =& midcom_core_context::get()->get_custom_key('request_data');
        }
        else
        {
            $data = array();
        }

        if (midcom::get('config')->get('wrap_style_show_with_name'))
        {
            $_style = "\n<!-- Start of style '{$path}' -->\n" . $_style;
            $_style .= "\n<!-- End of style '{$path}' -->\n";
        }

        $preparsed = midcom_helper_misc::preparse($_style);
        $result = eval('?>' . $preparsed);

        if ($result === false)
        {
            // Note that src detection will be semi-reliable, as it depends on all errors being
            // found before caching kicks in.
            throw new midcom_error("Failed to parse style element '{$path}', content was loaded from '{$_style}', see above for PHP errors.");
        }
    }

    /**
     * Gets the component style.
     *
     * @todo Document
     *
     * @param midcom_db_topic $topic    Current topic
     * @return int Database ID if the style to use in current view or false
     */
    private function _get_component_style(midcom_db_topic $topic)
    {
        $_st = false;
        // get user defined style for component
        // style inheritance
        // should this be cached somehow?
        if ($topic->style)
        {
            $_st = $this->get_style_id_from_path($topic->style);
        }
        else if (!empty($GLOBALS['midcom_style_inherited']))
        {
            // FIXME: This GLOBALS is set by urlparser. Should be removed
            // get user defined style inherited from topic tree
            $_st = $this->get_style_id_from_path($GLOBALS['midcom_style_inherited']);
        }
        else
        {
            // Get style from sitewide per-component defaults.
            $styleengine_default_styles = midcom::get('config')->get('styleengine_default_styles');
            if (isset($styleengine_default_styles[$topic->component]))
            {
                $_st = $this->get_style_id_from_path($styleengine_default_styles[$topic->component]);
            }
            else if (midcom::get('config')->get('styleengine_relative_paths'))
            {
                $_st = midcom_connection::get('style');
            }
        }

        if ($_st)
        {
            $substyle = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_SUBSTYLE);

            if (is_string($substyle))
            {
                $chain = explode('/', $substyle);
                foreach ($chain as $stylename)
                {
                    if ($_subst_id = $this->get_style_id_from_path($stylename, $_st))
                    {
                        $_st = $_subst_id;
                    }
                }
            }
        }

        return $_st;
    }

    /**
     * Gets the component styledir associated with the topics
     * component.
     *
     * @param MidgardTopic $topic the current component topic.
     * @return mixed the path to the components style directory.
     */
    private function _get_component_snippetdir($topic)
    {
        // get component's snippetdir (for default styles)
        $loader = midcom::get('componentloader');
        if (   !$topic
            || !$topic->guid)
        {
            return null;
        }
        if (!empty($loader->manifests[$topic->component]->extends))
        {
            $this->append_component_styledir($loader->manifests[$topic->component]->extends);
        }

        return $loader->path_to_snippetpath($topic->component) . "/style";
    }

    /**
     * Function append styledir
     *
     * Adds an extra style directory to check for style elements at
     * the end of the styledir queue.
     *
     * @param dirname path of style directory within midcom.
     * @return boolean true if directory appended
     * @throws midcom exception if directory does not exist.
     */
    function append_styledir ($dirname)
    {
        if (!file_exists($dirname))
        {
            throw new midcom_error("Style directory $dirname does not exist!");
        }
        $this->_styledirs_append[midcom_core_context::get()->id][] = $dirname;
        return true;
    }

    /**
     * Function prepend styledir
     *
     * @param string $dirname path of styledirectory within midcom.
     * @return boolean true if directory appended
     * @throws midcom_error if directory does not exist.
     */
    function prepend_styledir ($dirname)
    {
        if (!file_exists($dirname))
        {
            throw new midcom_error("Style directory {$dirname} does not exist.");
        }
        $this->_styledirs_prepend[midcom_core_context::get()->id][] = $dirname;
        return true;
    }

    /**
     * append the styledir of a component to the queue of styledirs.
     *
     * @param string componentname
     * @throws midcom exception if directory does not exist.
     */
    function append_component_styledir ($component)
    {
        $loader = midcom::get('componentloader');
        $path = $loader->path_to_snippetpath($component) . "/style";
        $this->append_styledir($path);
    }

    /**
     * prepend the styledir of a component
     *
     * @param string $component component name
     */
    function prepend_component_styledir ($component)
    {
        $loader = midcom::get('componentloader');
        $path = $loader->path_to_snippetpath($component) . "/style";
        $this->prepend_styledir($path);
    }

    /**
     * Appends a substyle after the currently selected component style.
     *
     * Appends a substyle after the currently selected component style, effectively
     * enabling a depth of more than one style during substyle selection. This is only
     * effective if done during the handle phase of the component and allows the
     * component. The currently selected substyle therefore is now searched one level
     * deeper below "subStyle".
     *
     * The system must have completed the CAN_HANDLE Phase before this function will
     * be available.
     *
     * @param string $newsub The substyle to append.
     */
    function append_substyle ($newsub)
    {
        // Make sure try to use only the first argument if we get space separated list, fixes #1788
        if (strpos($newsub, ' ') !== false)
        {
            $newsub = preg_replace('/^(.+?) .+/', '$1', $newsub);
        }

        if (midcom::get()->get_status() < MIDCOM_STATUS_HANDLE)
        {
            throw new midcom_error("Cannot append a substyle before the HANDLE phase.");
        }

        $context = midcom_core_context::get();
        $current_style = $context->get_key(MIDCOM_CONTEXT_SUBSTYLE);

        if (strlen($current_style) > 0)
        {
            $newsub = $current_style . '/' . $newsub;
        }

        $context->set_key(MIDCOM_CONTEXT_SUBSTYLE, $newsub);
    }

    /**
     * Prepends a substyle before the currently selected component style.
     *
     * Prepends a substyle before the currently selected component style, effectively
     * enabling a depth of more than one style during substyle selection. This is only
     * effective if done during the handle phase of the component and allows the
     * component. The currently selected substyle therefore is now searched one level
     * deeper below "subStyle".
     *
     * The system must have completed the CAN_HANDLE Phase before this function will
     * be available.
     *
     * @param string $newsub The substyle to prepend.
     */
    function prepend_substyle($newsub)
    {
        if (midcom::get()->get_status() < MIDCOM_STATUS_HANDLE)
        {
            throw new midcom_error("Cannot prepend a substyle before the HANDLE phase.");
        }

        $context = midcom_core_context::get();
        $current_style = $context->get_key(MIDCOM_CONTEXT_SUBSTYLE);

        if (strlen($current_style) > 0)
        {
            $newsub .= "/" . $current_style;
        }
        debug_add("Updating Component Context Substyle from $current_style to $newsub");

        $context->set_key(MIDCOM_CONTEXT_SUBSTYLE, $newsub);
    }

    /**
     * This function merges the prepend and append styles with the
     * componentstyle. This happens when the enter_context function is called.
     * You cannot change the style call stack after that (unless you call enter_context again of course).
     *
     * @param string component style
     */
    private function _merge_styledirs ($component_style)
    {
        $current_context = midcom_core_context::get()->id;
        /* first the prepend styles */
        $this->_styledirs[$current_context] = $this->_styledirs_prepend[$current_context];
        /* then the contextstyle */
        $this->_styledirs[$current_context][count($this->_styledirs[$current_context])] = $component_style;

        $this->_styledirs[$current_context] =  array_merge($this->_styledirs[$current_context], $this->_styledirs_append[$current_context]);
    }

    /**
     * Switches the context (see dynamic load). Private variables $_context, $_topic
     * and $_snippetdir are adjusted.
     *
     * @todo check documentation
     * @param int $context    The context to enter
     * @return boolean            True on success, false on failure.
     */
    function enter_context($context)
    {
        // set new context and topic
        array_unshift($this->_context, $context); // push into context stack

        $this->_topic = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_CONTENTTOPIC);

        // Prepare styledir stacks
        if (!isset($this->_styledirs[$context]))
        {
            $this->_styledirs[$context] = array();
        }
        if (!isset($this->_styledirs_prepend[$context]))
        {
            $this->_styledirs_prepend[$context] = array();
        }
        if (!isset($this->_styledirs_append[$context]))
        {
            $this->_styledirs_append[$context] = array();
        }

        if (   $this->_topic
            && $_st = $this->_get_component_style($this->_topic))
        {
            array_unshift($this->_scope, $_st);
        }

        $this->_snippetdir = $this->_get_component_snippetdir($this->_topic);

        $this->_merge_styledirs($this->_snippetdir);
        return true;
    }

    /**
     * Switches the context (see dynamic load). Private variables $_context, $_topic
     * and $_snippetdir are adjusted.
     *
     * @todo check documentation
     * @return boolean            True on success, false on failure.
     */
    function leave_context()
    {
        if (   $this->_topic
            && $this->_get_component_style($this->_topic))
        {
            array_shift($this->_scope);
        }
        array_shift($this->_context);

        $this->_topic = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_CONTENTTOPIC);

        $this->_snippetdir = $this->_get_component_snippetdir($this->_topic);
        return true;
    }

    function get_style()
    {
        if (is_null($this->object))
        {
            $this->object = new midcom_db_style(midcom_connection::get('style'));
        }
        return $this->object;
    }

    /**
     * Include all text/css attachments of current style to MidCOM headers
     */
    function add_database_head_elements()
    {
        static $called = false;
        if ($called)
        {
            return;
        }
        $style = $this->get_style();
        $mc = midcom_db_attachment::new_collector('parentguid', $style->guid);
        $mc->add_constraint('mimetype', '=', 'text/css');
        $attachments = $mc->get_values('name');

        foreach ($attachments as $guid => $filename)
        {
            // TODO: Support media types
            midcom::get('head')->add_stylesheet(midcom_connection::get_url('self') . "midcom-serveattachmentguid-{$guid}/{$filename}");
        }

        $called = true;
    }
}

/**
 * Global shortcut.
 *
 * @see midcom_helper__styleloader::show()
 */
function midcom_show_style($param)
{
    return midcom::get('style')->show($param);
}
?>
