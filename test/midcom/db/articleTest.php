<?php
/**
 * @package openpsa.test
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */

/**
 * OpenPSA testcase
 *
 * @package openpsa.test
 */
class midcom_db_articleTest extends openpsa_testcase
{
    protected static $_topic;

    public static function setUpBeforeClass()
    {
        self::$_topic = self::create_class_object('midcom_db_topic');
    }

    public function testCRUD()
    {
        midcom::get('auth')->request_sudo('midcom.core');

        $article = new midcom_db_article();
        $stat = $article->create();
        $this->assertFalse($stat, midcom_connection::get_error_string());

        $article = new midcom_db_article();
        $article->topic = self::$_topic->id;
        $stat = $article->create();
        $this->assertTrue($stat, midcom_connection::get_error_string());

        $this->register_object($article);

        $article->title = 'test';
        $stat = $article->update();
        $this->assertTrue($stat);
        $this->assertEquals('test', $article->title);

        $stat = $article->delete();
        $this->assertTrue($stat);

        midcom::get('auth')->drop_sudo();
    }

    public function test_get_parent()
    {
        $attributes = array('topic' => self::$_topic->id);
        $article1 = $this->create_object('midcom_db_article', $attributes);
        $attributes['up'] = $article1->id;
        $attributes['name'] = 'test2';
        $article2 = $this->create_object('midcom_db_article', $attributes);
        $parent2 = $article2->get_parent();
        $this->assertEquals($parent2->guid, $article1->guid);
        $parent1 = $article1->get_parent();
        $this->assertEquals($parent1->guid, self::$_topic->guid);
    }
}
?>