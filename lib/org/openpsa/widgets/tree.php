<?php
/**
 * @package org.openpsa.widgets
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * dynatree-based tree widget
 *
 * @package org.openpsa.widgets
 */
class org_openpsa_widgets_tree extends midcom_baseclasses_components_purecode
{
    /**
     * The tree's root node, if any
     *
     * @var int
     */
    public $root_node = 0;

    /**
     * The object fields to use for link titles
     *
     * @var array
     */
    public $title_fields = array('title');

    /**
     * Callback for rendering object links. It receives the GUID as parameter
     *
     * @var callback
     */
    public $link_callback;

    /**
     * The constraints for the children MC
     *
     * @var array
     */
    public $constraints = array();

    /**
     * The object's class name
     *
     * @var string
     */
    private $_object_class;

    /**
     * The object's parent field
     *
     * @var string
     */
    private $_parent_field;

    /**
     * Flag that tracks if JS/CSS files have already been added
     *
     * @var boolean
     */
    private static $_head_elements_added = false;

    /**
     * Constructor, adds head elements and initializes some variables
     *
     * @param string $classname The object class we're using
     * @param string $parent_field Where to look for children
     */
    public function __construct($classname, $parent_field)
    {
        $this->_object_class = $classname;
        $this->_parent_field = $parent_field;

        self::add_head_elements();
    }

    public function render(array $items = array())
    {
        if (empty($items))
        {
            $items = $this->_list_items($this->root_node);
        }
        if (empty($items))
        {
            return;
        }

        $identifier = 't_' . md5('org_openpsa_widgets_treemenu_' . $this->_object_class);

        echo '<div class="openpsa-dynatree-container" id="' . $identifier . "\">\n";
        $this->_render_items($items);
        echo "\n</div>\n";

        $prefix = midcom_core_context::get()->get_key(MIDCOM_CONTEXT_ANCHORPREFIX);

        echo <<<JSINIT
<script type="text/javascript">
      $(document).ready(function ()
      {
          org_openpsa_tree.setup("{$identifier}", "{$prefix}");
      });
</script>
JSINIT;

    }

    /**
     * Internal helper for loading the items recursively
     *
     * @param int $id The parent object ID
     */
    private function _list_items($id)
    {
        $data = array();

        $mc = midcom::get('dbfactory')->new_collector($this->_object_class, $this->_parent_field, (int) $id);
        $mc->add_value_property('id');

        foreach ($this->title_fields as $field)
        {
            $mc->add_value_property($field);
            $mc->add_order($field);
        }
        foreach ($this->constraints as $constraint)
        {
            $mc->add_constraint($constraint[0], $constraint[1], $constraint[2]);
        }

        $mc->execute();
        $keys = $mc->list_keys();

        if (sizeof($keys) === 0)
        {
            return;
        }

        foreach ($keys as $guid => $array)
        {
            $entry = array('guid' => $guid);

            foreach ($this->title_fields as $field)
            {
                if (($title = $mc->get_subkey($guid, $field)))
                {
                    $entry['title'] = $title;
                    break;
                }
            }
            if (empty($entry['title']))
            {
                $entry['title'] = $this->_l10n->get('unknown');
            }

            $entry['children'] = $this->_list_items($mc->get_subkey($guid, 'id'));
            $data[] = $entry;
        }
        return $data;
    }

    private function _render_items(array $items)
    {
        if (sizeof($items) == 0)
        {
            return;
        }
        $prefix = midcom::get()->get_host_prefix();
        echo "<ul>\n";
        foreach ($items as $item)
        {
            if (is_callable($this->link_callback))
            {
                $url = call_user_func($this->link_callback, $item['guid']);
            }
            else
            {
                $url = $prefix . 'midcom-permalink-' . $item['guid'];
            }
            echo '<li id="g_' . $item['guid'] . '"><a href="' . $url . '">' . $item['title'] . "</a>\n";
            if (!empty($item['children']))
            {
                $this->_render_items($item['children']);
            }
            echo "</li>\n";
        }
        echo "</ul>\n";
    }

    /**
     * Add necessary head elements
     */
    public static function add_head_elements()
    {
        if (self::$_head_elements_added)
        {
            return;
        }

        $head = midcom::get('head');
        $head->enable_jquery();

        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.core.min.js');
        $head->add_jsfile(MIDCOM_JQUERY_UI_URL . '/ui/jquery.ui.widget.min.js');

        $head->add_jsfile(MIDCOM_STATIC_URL . '/jQuery/jquery.cookie.js');
        $head->add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.widgets/dynatree/jquery.dynatree.min.js');
        $head->add_stylesheet(MIDCOM_STATIC_URL . "/org.openpsa.widgets/dynatree/skin/ui.dynatree.css");
        $head->add_jsfile(MIDCOM_STATIC_URL . '/org.openpsa.widgets/dynatree.custom.js');
        $head->add_jquery_ui_theme();
        self::$_head_elements_added = true;
    }

}
?>