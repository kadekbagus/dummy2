<?php namespace Orbit\Helper\Net\LinkPreview;
/**
 * Class to get Home type LinkPreviewData
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use Tenant;
use DB;
use Config;

class HomeLinkPreview implements ObjectLinkPreviewInterface
{
    protected $input;

    public static function create()
    {
        return new static();
    }

    public function setInput(array $input)
    {
        $this->input = $input;
        return $this;
    }

    public function getInput()
    {
        return $this->input;
    }

    public function getPreviewData()
    {
        $lang = $this->input['lang'];
        $previewData = new LinkPreviewData('', '', '', '', [], $lang->name);

        return $previewData;
    }
}
