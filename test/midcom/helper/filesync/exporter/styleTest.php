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
require_once OPENPSA_TEST_ROOT . 'midcom/helper/filesync/__files/fs_setup.php';

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class midcom_helper_filesync_exporter_styleTest extends openpsa_testcase
{
    protected static $_rootdir;

    public static function setUpBeforeClass()
    {
        self::$_rootdir = openpsa_test_fs_setup::get_exportdir('style');
    }

    public function test_read_root()
    {
        $style_name = 'style_' . __CLASS__ . __FUNCTION__ . microtime(true);

        $element_name = 'element_' . __CLASS__ . __FUNCTION__ . microtime(true);
        $style = $this->create_object('midcom_db_style', array('name' => $style_name));
        $sub_style = $this->create_object('midcom_db_style', array('name' => $style_name, 'up' => $style->id));
        $element = $this->create_object('midcom_db_element', array('name' => $element_name, 'style' => $sub_style->id));

        $exporter = new midcom_helper_filesync_exporter_style(self::$_rootdir);
        midcom::get('auth')->request_sudo('midcom.helper.filesync');
        $stat = $exporter->read_root($style->id);
        midcom::get('auth')->drop_sudo();

        $this->assertTrue($stat);
        $this->assertFileExists(self::$_rootdir . $style_name);
        $this->assertFileExists(self::$_rootdir . $style_name . '/' . $style_name);
        $this->assertFileExists(self::$_rootdir . $style_name . '/' . $style_name . '/' . $element_name . '.php');
    }
}
?>