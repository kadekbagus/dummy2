<?php namespace Orbit\Helper\Net\LinkPreview;
/**
 * Factory class to get LinkPreviewData based on link object type
 * @author Ahmad <ahmad@dominopos.com>
 */
class ObjectLinkPreviewFactory
{
    protected $input = [];

    protected $linkType = '';

    public function __construct(array $input, $linkType)
    {
        $this->input = $input;
        $this->linkType = $linkType;
    }

    public static function create(array $input, $linkType)
    {
        return new static($input, $linkType);
    }

    /**
     * Set the input
     *
     * @param String
     * @return ObjectLinkPreviewFactory
     */
    public function setInput(array $input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Get the input
     *
     * @return array
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Set the linkType
     *
     * @param String
     * @return ObjectLinkPreviewFactory
     */
    public function setLinkType($linkType)
    {
        $this->linkType = $linkType;

        return $this;
    }

    /**
     * @return string
     */
    public function getLinkType()
    {
        return $this->linkType;
    }

    /**
     * Get LinkPreviewData based on link type
     *
     * @return ObjectLinkPreviewInterface
     */
    public function getData()
    {
        $shareData = NULL;

        switch ($this->input['objectType']) {
            case 'store':
                switch ($this->linkType) {
                    case 'detail':
                        $shareData = StoreLinkPreview::create()->setInput($this->input)->getPreviewData();
                        break;

                    case 'list':
                        $shareData = StoreLinkPreview::create()->setInput($this->input)->getPreviewData();
                        break;

                    default:
                        # code...
                        break;
                }

            case 'promotion':
                # code...
                break;

            case 'home':
                $shareData = HomeLinkPreview::create()->setInput($this->input)->getPreviewData();
                break;

            default:
                break;
        }

        return $shareData;
    }
}
