<?php
/**
 * @copyright CONTENT CONTROL GmbH, http://www.contentcontrol-berlin.de
*/

namespace midcom\datamanager\storage;

use midcom_db_attachment;
use midcom_helper_imagefilter;
use midcom_error;
use midcom;
use midgard_blob;

/**
 * Experimental storage class
 */
class photo extends images
{
    /**
     * {@inheritdoc}
     */
    public function save()
    {
        if ($this->value === null)
        {
            //delete?
        }

        $existing = $this->load();
        if (!empty($this->value['file']))
        {
            $this->convert_to_web_type($this->value['file']);
            $attachment = $this->get_attachment($this->value['file'], $existing, 'archival');
            if (!$attachment->copy_from_file($this->value['file']['tmp_name']))
            {
                throw new midcom_error('Failed to copy attachment');
            }

            if (!empty($this->config['type_config']['filter_chain']))
            {
                $this->apply_filter($attachment, $this->config['type_config']['filter_chain']);
            }

            $this->set_imagedata($attachment);
            $this->map = array('archival' => $attachment);
            if (!empty($this->config['type_config']['derived_images']))
            {
                foreach ($this->config['type_config']['derived_images'] as $identifier => $filter_chain)
                {
                    $derived = $this->get_attachment($this->value['file'], $existing, $identifier);
                    $this->apply_filter($attachment, $filter_chain, $derived);
                    $this->set_imagedata($derived);
                    $this->map[$identifier] = $derived;
                }
            }

            return $this->save_attachment_list();
        }
        return true;
    }

    /**
     *
     * @param array $data
     * @param array $existing
     * @param string $identifier
     * @param \midcom_core_dbaobject
     */
    protected function get_attachment(array $data, $existing, $identifier)
    {
        $filename = midcom_db_attachment::safe_filename($identifier . '_' . $data['name'], true);
        if (!empty($existing[$identifier]))
        {
            $attachment = $existing[$identifier];
            if ($attachment->name != $filename)
            {
                $attachment->name = $this->generate_unique_name($filename);
            }
            $attachment->title = $data['name'];
            $attachment->mimetype = $data['type'];
            return $attachment;
        }
        return $this->create_attachment($filename, $data['name'], $data['type']);
    }

    /**
     * Applies a filter chain
     *
     * @param midcom_db_attachment $source The image to apply to
     * @param string $filterchain The midcom_helper_imagefilter filter chain to apply
     * @param midcom_db_attachment $target The attachment where the changes should be saved
     */
    protected function apply_filter(midcom_db_attachment $source, $filterchain, $target = null)
    {
        if ($target === null)
        {
            $target = $source;
        }
        $filter = new midcom_helper_imagefilter($source);
        $filter->process_chain($filterchain);

        if (!$filter->write($target))
        {
            throw new midcom_error("Failed to update image '{$target->guid}'");
        }
    }

    /**
     * Automatically convert the uploaded file to a web-compatible type. Uses
     * only the first image of multi-page uploads (like PDFs). The original_tmpname
     * file is manipulated directly.
     *
     * Uploaded GIF, PNG and JPEG files are left untouched.
     *
     * In case of any conversions being done, the new extension will be appended
     * to the uploaded file.
     */
    protected function convert_to_web_type(array &$data)
    {
        $original_mimetype = $data['type'];
        switch (preg_replace('/;.+$/', '', $original_mimetype))
        {
            case 'image/png':
            case 'image/gif':
            case 'image/jpeg':
                debug_add('No conversion necessary, we already have a web mime type');
                return;

            case 'application/postscript':
            case 'application/pdf':
                $data['type'] = 'image/png';
                $conversion = 'png';
                break;

            default:
                $data['type'] = 'image/jpeg';
                $conversion = 'jpg';
                break;
        }
        debug_add('convert ' . $original_mimetype . ' to ' . $conversion);

        $filter = new midcom_helper_imagefilter;
        $filter->set_file($data['tmp_name']);
        $filter->convert($conversion);

        // Prevent double .jpg.jpg
        if (!preg_match("/\.{$conversion}$/", $data['tmp_name']))
        {
            // Make sure there is only one extension on the file ??
            $data['name'] = midcom_db_attachment::safe_filename($data['tmp_name'] . ".{$conversion}", true);
        }
    }
}