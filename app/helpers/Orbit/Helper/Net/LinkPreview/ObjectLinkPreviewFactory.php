<?php namespace Orbit\Helper\Net\LinkPreview;

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
     * Get instance of the ObjectLinkPreview based on link type
     *
     * @return ObjectLinkPreviewInterface
     */
    public function getInstance()
    {
        $shareData = NULL;

        switch ($this->input['objectType']) {
            case 'store':
                switch ($this->linkType) {
                    case 'detail':
                        $shareData = StoreLinkPreview::create()->setInput($this->input)->getShareData();
                        break;

                    case 'list':
                        # code...
                        break;

                    default:
                        # code...
                        break;
                }

            case 'promotion':
                # code...
                break;

            default:
                break;
        }

        return $shareData;
    }
}
