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
                $shareData = StoreLinkPreview::create()->setInput($this->input)->getPreviewData();
                break;

            case 'promotion':
                $shareData = PromotionLinkPreview::create()->setInput($this->input)->getPreviewData();
                break;

            case 'coupon':
                $shareData = CouponLinkPreview::create()->setInput($this->input)->getPreviewData();
                break;

            case 'event':
                $shareData = NewsLinkPreview::create()->setInput($this->input)->getPreviewData();
                break;

            case 'mall':
                $shareData = MallLinkPreview::create()->setInput($this->input)->getPreviewData();
                break;

            case 'partner':
                $shareData = PartnerLinkPreview::create()->setInput($this->input)->getPreviewData();
                break;

            default:
            case 'home':
                $shareData = HomeLinkPreview::create()->setInput($this->input)->getPreviewData();
                break;
        }

        return $shareData;
    }
}
