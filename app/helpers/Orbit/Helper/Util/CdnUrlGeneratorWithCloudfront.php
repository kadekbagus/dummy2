<?php namespace Orbit\Helper\Util;

use Orbit\Helper\Util\CdnUrlGenerator;

/**
 * Helper to generate image url from Cloudfront cdn.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CdnUrlGeneratorWithCloudfront extends CdnUrlGenerator
{
    /**
     * Get the image url with Cloudfront cdn prefix.
     *
     * @return string $image Cloudfront image url (if enabled).
     */
    public function getImageUrl($localPath = "", $cdnFullPath = "")
    {
        $image = parent::getImageUrl($localPath, $cdnFullPath);

        $currentProvider = $this->getConfigValue('provider');
        $usingCloudfront = $this->getConfigValue('enable_cloudfront');

        if ($usingCloudfront) {
            // If enabled, then replace current cdn url (likely S3) with the Cloudfront version.
            $image = str_replace($this->cdnUrlPrefixes[$currentProvider], $this->cdnUrlPrefixes['cloudfront'], $image);
        }

        return $image;
    }
}
