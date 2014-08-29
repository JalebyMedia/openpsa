<?php
/**
 * @package midgard.admin.asgard
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * Style helper methods
 *
 * @package midgard.admin.asgard
 */
class midgard_admin_asgard_stylehelper
{
    /**
     * The current request data
     *
     * @var array
     */
    private $_data;

    public function __construct(array &$data)
    {
        $this->_data =& $data;

        midcom::get()->head->add_jquery_ui_theme(array('accordion'));
        midcom::get()->head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/core.min.js');
        midcom::get()->head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/widget.min.js');
        midcom::get()->head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/accordion.min.js');
    }

    public function render_help()
    {
        $help_element = null;
        if (empty($this->_data['object']->id))
        {
            return;
        }

        if (   midcom::get()->dbfactory->is_a($this->_data['object'], 'midgard_style')
            && (   $this->_data['handler_id'] !== '____mfa-asgard-object_create'
                || $this->_data['current_type'] == 'midgard_element'))
        {
            $help_element = $this->_get_help_style_elementnames($this->_data['object']);
        }
        else if (   midcom::get()->dbfactory->is_a($this->_data['object'], 'midgard_element')
                 && $this->_data['handler_id'] !== '____mfa-asgard-object_create')
        {
            $help_element = $this->_get_help_element();
        }

        if ($help_element)
        {
            midcom_show_style('midgard_admin_asgard_stylehelper_' . $help_element);
        }
    }

    private function _get_help_element()
    {
        if (   empty($this->_data['object']->name)
            || empty($this->_data['object']->style))
        {
            // We cannot help with empty elements
            return;
        }

        if ($this->_data['object']->name == 'ROOT')
        {
            $element_path = midcom::get()->componentloader->path_to_snippetpath('midgard.admin.asgard') . '/documentation/ROOT.php';
            $this->_data['help_style_element'] = array
            (
                'component' => 'midcom',
                'default'   => file_get_contents($element_path),
            );
            return 'element';
        }

        // Find the element we're looking for
        $style_elements = $this->_get_style_elements_and_nodes($this->_data['object']->style);
        foreach ($style_elements['elements'] as $component => $elements)
        {
            if (!empty($elements[$this->_data['object']->name]))
            {
                $element_path = $elements[$this->_data['object']->name];
                $this->_data['help_style_element'] = array
                (
                    'component' => $component,
                    'default'   => file_get_contents($element_path),
                );
                return 'element';
            }
        }
    }

    /**
     * Helper for suggesting element names to create under a style
     */
    private function _get_help_style_elementnames(midcom_db_style $style)
    {
        $this->_data['help_style_elementnames'] = $this->_get_style_elements_and_nodes($style->id);
        return 'elementnames';
    }

    private function _get_style_elements_and_nodes($style_id)
    {
        $results = array
        (
            'elements' => array
            (
                'midcom' => array
                (
                    'style-init' => '',
                    'style-finish' => '',
                 )
            ),
            'nodes' => array(),
        );

        if (!$style_id)
        {
            return $results;
        }
        $style_path = midcom::get()->style->get_style_path_from_id($style_id);
        $style_nodes = $this->_get_nodes_using_style($style_path);

        foreach ($style_nodes as $node)
        {
            if (!isset($results['nodes'][$node->component]))
            {
                $results['nodes'][$node->component] = array();
                // Get the list of style elements for the component
                $results['elements'][$node->component] = $this->_get_component_default_elements($node->component);
            }

            $results['nodes'][$node->component][] = $node;
        }

        if ($style_id == midcom_connection::get('style'))
        {
            // We're in site main style, append elements from there to the list of "common elements"
            $mc = midcom_db_element::new_collector('style', $style_id);
            $elements = $mc->get_values('name');
            foreach ($elements as $name)
            {
                $results['elements']['midcom'][$name] = '';
            }

            if (!isset($results['elements']['midcom']['ROOT']))
            {
                // There should always be the ROOT element available
                $results['elements']['midcom']['ROOT'] = '';
            }
        }

        return $results;
    }

    /**
     * Get list of topics using a particular style
     *
     * @param string $style Style path
     * @return array List of folders
     */
    private function _get_nodes_using_style($style)
    {
        $style_nodes = array();
        // Get topics directly using the style
        $qb = midcom_db_topic::new_query_builder();
        $qb->add_constraint('style', '=', $style);
        $nodes = $qb->execute();

        foreach ($nodes as $node)
        {
            $style_nodes[] = $node;

            if ($node->styleInherit)
            {
                $child_nodes = $this->_get_nodes_inheriting_style($node);
                $style_nodes = array_merge($style_nodes, $child_nodes);
            }
        }

        return $style_nodes;
    }

    private function _get_nodes_inheriting_style($node)
    {
        $nodes = array();
        $child_qb = midcom_db_topic::new_query_builder();
        $child_qb->add_constraint('up', '=', $node->id);
        $child_qb->add_constraint('style', '=', '');
        $children = $child_qb->execute();

        foreach ($children as $child_node)
        {
            $nodes[] = $child_node;
            $subnodes = $this->_get_nodes_inheriting_style($child_node);
            $nodes = array_merge($nodes, $subnodes);
        }

        return $nodes;
    }

    /**
     * List the default template elements shipped with a component
     *
     * @param string $component Component to look elements for
     * @return array List of elements found indexed by the element name
     */
    private function _get_component_default_elements($component)
    {
        $elements = array();

        // Path to the file system
        $path = midcom::get()->componentloader->path_to_snippetpath($component) . '/style';

        if (!is_dir($path))
        {
            debug_add("Directory {$path} not found.");
            return $elements;
        }

        foreach (glob($path . '/*.php') as $filepath)
        {
            $file = basename($filepath);
            $elements[str_replace('.php', '', $file)] = $filepath;
        }

        return $elements;
    }
}
