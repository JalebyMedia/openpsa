<?php
/**
 * @package midcom.helper.datamanager2
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use Michelf\MarkdownExtra;

/**
 * Datamanager 2 simple markdown widget
 *
 * As with all subclasses, the actual initialization is done in the initialize() function,
 * not in the constructor, to allow for error handling.
 *
 * This widget supports all types which have a simple, plain text representation accessible
 * though their <i>value</i> member.
 *
 * <b>Available configuration options:</b>
 *
 * - <i>int width:</i> The number of columns of the textarea, this defaults to 50.
 *   Note that this value might be overridden by CSS.
 * - <i>int height:</i> The number of rows of the textarea, this defaults to 6.
 *   Note that this value might be overridden by CSS.
 * - <i>string wrap:</i> Controls the textbox wrapping, defaults to 'virtual' text is
 *   wrapped by the browser, but the automatic wraps are not sent to the server. You
 *   can set this to 'off' or 'physical'. If you set this to an empty string, the
 *   attribute is omitted.
 *
 * @package midcom.helper.datamanager2
 */
class midcom_helper_datamanager2_widget_markdown extends midcom_helper_datamanager2_widget
{
    /**
     * Width of the textbox.
     *
     * @var int
     */
    public $width = 50;

    /**
     * Height of the textbox.
     *
     * @var int
     */
    public $height = 6;

    /**
     * Wrapping mode of the textbox.
     *
     * @var string
     */
    public $wrap = 'virtual';

    /**
     * The initialization event handler post-processes the maxlength setting.
     */
    public function _on_initialize()
    {
        $this->_require_type_value();

        midcom::get()->head->enable_jquery();
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/midcom.helper.datamanager2/markitup/jquery.markitup.pack.js');
        midcom::get()->head->add_jsfile(MIDCOM_STATIC_URL . '/midcom.helper.datamanager2/markitup/sets/markdown/set.js');
        $this->add_stylesheet(MIDCOM_STATIC_URL . '/midcom.helper.datamanager2/markitup/skins/markitup/style.css');
        $this->add_stylesheet(MIDCOM_STATIC_URL . '/midcom.helper.datamanager2/markitup/sets/markdown/style.css');
    }

    /**
     * Adds a simple single-line text form element at this time.
     */
    function add_elements_to_form($attributes)
    {
        $attributes = array_merge($attributes, array
        (
            'rows' => $this->height,
            'cols' => $this->width,
            'class' => 'longtext',
            'id'    => "{$this->_namespace}{$this->name}",
        ));
        if ($this->wrap != '')
        {
            $attributes['wrap'] = $this->wrap;
        }

        $elements = Array();
        $elements[] = $this->_form->createElement('textarea', $this->name, $this->_translate($this->_field['title']), $attributes);
        $this->_form->applyFilter($this->name, 'trim');

        $elements[] = $this->_form->createElement('static', "{$this->name}_toolbar", '', "<script> jQuery('#{$this->_namespace}{$this->name}').markItUp(mySettings);\n</script>");

        // Load help text
        // TODO: l10n
        $file = MIDCOM_ROOT . "/midcom/helper/datamanager2/documentation/markdown.en.txt";
        if (file_exists($file))
        {
            $elements[] = $this->_form->createElement('static', "{$this->name}_help", '', "<div class=\"markdown_cheatsheet\" style=\"display: none;\">" . MarkdownExtra::defaultTransform(file_get_contents($file)) . "</div>");
        }

        $this->_form->addGroup($elements, $this->name, $this->_translate($this->_field['title']), ' ', false);
        $this->_form->updateElementAttr($this->name, array('class' => 'midcom_helper_datamanager2_widget_markdown'));
    }

    function get_default()
    {
        return $this->_type->value;
    }

    function sync_type_with_widget($results)
    {
        $this->_type->value = $results[$this->name];
    }
}
