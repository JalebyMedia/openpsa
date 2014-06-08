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
class net_nehmer_comments_handler_moderateTest extends openpsa_testcase
{
    public function testHandler_report()
    {
        $this->create_user(true);
        $topic = $this->create_object('midcom_db_topic');
        $attributes = array('objectguid' => $topic->guid);
        $comment = $this->create_object('net_nehmer_comments_comment', $attributes);

        midcom::get()->auth->request_sudo('net.nehmer.comments');

        $_POST = array
        (
            'mark' => 'abuse'
        );

        $url = $this->run_relocate_handler('net.nehmer.comments', array('report', $comment->guid));
        $this->assertEquals('', $url);

        $comment->refresh();
        $this->assertEquals(net_nehmer_comments_comment::ABUSE, $comment->status);
        midcom::get()->auth->drop_sudo();
    }
}
?>