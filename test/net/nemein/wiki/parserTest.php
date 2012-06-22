<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR);
    require_once OPENPSA_TEST_ROOT . 'rootfile.php';
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class net_nemein_wiki_parserTest extends openpsa_testcase
{
    protected static $_page;

    public static function setUpBeforeClass()
    {
        $topic = self::get_component_node('net.nemein.wiki');
        $attributes = array
        (
            'topic' => $topic->id,
            'title' => __CLASS__ . microtime()
        );
        self::$_page = self::create_class_object('net_nemein_wiki_wikipage', $attributes);
    }

    /**
     * @dataProvider provider_find_links_in_content
     */
    public function test_find_links_in_content($text, $result)
    {
        self::$_page->content = $text;
        midcom::get('auth')->request_sudo('net.nemein.wiki');
        self::$_page->update();
        midcom::get('auth')->drop_sudo();

        $parser = new net_nemein_wiki_parser(self::$_page);
        $links = $parser->find_links_in_content();
        $this->assertEquals($result, $links);
    }

    public function provider_find_links_in_content()
    {
        return array
        (
            '1' => array
            (
                'filler [link|Link Title] filler',
                array('link' => 'Link Title')
            ),
            '2' => array
            (
                'filler [link] filler',
                array('link' => 'link')
            ),
            '3' => array
            (
                'filler filler',
                array()
            ),
        );
    }
}
?>