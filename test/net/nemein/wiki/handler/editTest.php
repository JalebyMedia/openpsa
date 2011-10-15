<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

if (!defined('OPENPSA_TEST_ROOT'))
{
    define('OPENPSA_TEST_ROOT', dirname(dirname(dirname(dirname(dirname(__FILE__))))) . DIRECTORY_SEPARATOR);
    require_once OPENPSA_TEST_ROOT . 'rootfile.php';
}

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class net_nemein_wiki_handler_editTest extends openpsa_testcase
{
    protected static $_topic;
    protected static $_page;

    public static function setUpBeforeClass()
    {
        $topic_attributes = array
        (
            'component' => 'net.nemein.wiki',
            'name' => __CLASS__ . time()
        );
        self::$_topic = self::create_class_object('midcom_db_topic', $topic_attributes);
        $article_properties = array
        (
            'topic' => self::$_topic->id,
            'title' => __CLASS__ . ' ' . time()
        );
        self::$_page = self::create_class_object('net_nemein_wiki_wikipage', $article_properties);
    }

    public function testHandler_edit()
    {
        midcom::get('auth')->request_sudo('net.nemein.wiki');

        $data = $this->run_handler(self::$_topic, array('edit', self::$_page->name));
        $this->assertEquals('edit', $data['handler_id']);

        midcom::get('auth')->drop_sudo();
    }

    public function testHandler_change()
    {
        midcom::get('auth')->request_sudo('net.nemein.wiki');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['change_to'] = 'default';
        $url = $this->run_relocate_handler(self::$_topic, array('change', self::$_page->name));
        $this->assertEquals('edit/' . self::$_page->name . '/', $url);

        midcom::get('auth')->drop_sudo();
    }
}
?>