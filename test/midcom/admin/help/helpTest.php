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
class midcom_admin_help_helpTest extends openpsa_testcase
{
    public function test_generate_file_path()
    {
        $path = midcom_admin_help_help::generate_file_path('handlers_view', 'net.nehmer.blog', 'en');
        $this->assertEquals(MIDCOM_ROOT . '/net/nehmer/blog/documentation/handlers_view.en.txt', $path);

        $path = midcom_admin_help_help::generate_file_path('handlers_view', 'net.nehmer.blog');
        $this->assertEquals(MIDCOM_ROOT . '/net/nehmer/blog/documentation/handlers_view.en.txt', $path);

        $path = midcom_admin_help_help::generate_file_path('handlers_view', 'net.nehmer.blog', 'xx');
        $this->assertEquals(MIDCOM_ROOT . '/net/nehmer/blog/documentation/handlers_view.en.txt', $path);

        $path = midcom_admin_help_help::generate_file_path('handlers_nonexistent', 'net.nehmer.blog', 'xx');
        $this->assertNull($path);
    }

    /**
     * @dataProvider provider_list_files
     */
    public function test_list_files($component, $index, $expected)
    {
        //@todo: To test properly, we should setup a context with a handler of the component
        $handler = new midcom_admin_help_help;
        $this->assertEquals($expected, $handler->list_files($component, $index));
    }

    public function provider_list_files()
    {
        return [
            [
                'midcom.helper.filesync',
                false,
                [
                    'urlmethods' => [
                        'path' => '/urlmethods',
                        'subject' => 'Additional URL methods',
                        'lang' => 'en'
                    ],
                ]
            ],
            [
                'net.nehmer.blog',
                false,
                [
                    '01_component_config' => [
                        'path' => MIDCOM_ROOT . '/net/nehmer/blog/documentation/01_component_config.en.txt',
                        'subject' => 'Component configuration',
                        'lang' => 'en'
                    ],
                    'style' => [
                        'path' => MIDCOM_ROOT . '/net/nehmer/blog/documentation/style.en.txt',
                        'subject' => 'net.nehmer.blog style elements',
                        'lang' => 'en'
                    ],
                ]
            ]
        ];
    }
}
