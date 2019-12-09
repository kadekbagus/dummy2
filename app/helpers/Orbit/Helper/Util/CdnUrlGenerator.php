<?php namespace Orbit\Helper\Util;

/**
 * Helpert to generate image url from cdn
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
class CdnUrlGenerator
{
    protected $config = [
    	// cdn path
	    'cdn_default' => array(
	        // Whether to use CDN or default URL for the uploaded files
	        'enable_cdn' => FALSE,

	        // Do we need to upload to CDN?
	        'upload_to_cdn' => FALSE,

	        // provider used
	        'provider' => 'S3',

	        // CDN Provider
	        'providers' => [
	            // gotomalls as default when not using cdn
	            'default' => [
	                // Just dummy provider
	                'name' => 'Gotomalls',

	                // Name of the bucket or directory
	                'bucket_name' => NULL,

	                // 'url_prefix' => 'https://mall-api.gotomalls.cool'
                    'url_prefix' => 'https://mall-api-v44.gotomalls.cool'
	            ],

	            // AWS S3
	            'S3' => [
	                // Name of the provider
	                'name' => 'Amazon S3',

	                // Name of the S3 bucket
	                'bucket_name' => 'dev-dotcool',

	                // Prefix of the CDN URL, without the bucket name (without trailing slash)
	                // When saving to database this prefix should be concat with bucket name
	                // and the path of the file. So the cdn_url field in table media has aboslute
	                // url like: https://s3-ap-southeast-1.amazonaws.com/dev-dotcool/uploads/retailers/2016/01/some-file.jpg
	                'url_prefix' => 'https://s3-ap-southeast-1.amazonaws.com'
	            ],

                // Cloudfront CDN config.
                'cloudfront' => [
                    'name' => 'Cloudfront CDN',
                    'bucket_name' => null,
                    'url_prefix' => 'https://cdn.cloudfront.net',
                ],
	        ]
	    )
    ];

    /**
     * List of cdn url prefixes. We store them on a variable
     * so we don't build it each time we call getImageUrl()
     * from the same instance.
     * @var array
     */
    protected $cdnUrlPrefixes = [];

    /**
     * @param array $config
     * @return void
     */
    public function __construct(array $config=[], $configKey = 'cdn_default')
    {
        $this->config = array_merge($this->config, $config);
        $this->configKey = $configKey;
        $this->buildCdnUrlPrefixes();
    }

    /**
     * @param array $config
     * @return imageUrl
     */
    public static function create(array $config=[], $configKey = 'cdn_default')
    {
        return new Static($config, $configKey);
    }

    /**
     * Get the image url.
     *
     * @return array
     */
    public function getImageUrl($localPath = "", $cdnFullPath = "")
    {
        $usingCdn = $this->getConfigValue('enable_cdn');
        $defaultUrlPrefix = $this->getConfigValue('providers.default.url_prefix');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = '';
        if (! empty($localPath)) {
        	$image = $urlPrefix . $localPath;
        }

        if ($usingCdn && ! empty($cdnFullPath)) {
            $image = $cdnFullPath;
        }

        return $image;
    }

    /**
     * Get list of url prefixes for each providers.
     *
     * @return array
     */
    public function getCdnUrlPrefixes()
    {
        return $this->cdnUrlPrefixes;
    }

    /**
     * Build cdn url prefixes for each provider.
     *
     * @return void
     */
    private function buildCdnUrlPrefixes()
    {
        foreach($this->getConfigValue('providers') as $providerId => $providerConfig) {
            $this->cdnUrlPrefixes[$providerId] = array_get($providerConfig, 'url_prefix');

            $bucketName = array_get($providerConfig, 'bucket_name');
            if (! empty($bucketName)) {
                $this->cdnUrlPrefixes[$providerId] .= "/{$bucketName}";
            }
        }
    }

    /**
     * Get config key and fallback to default if it is not exists
     *
     * @param string $keys
     * @return boolean
     */
    protected function getConfigValue($keys)
    {
  		if (! empty(array_get($this->config, $this->configKey . '.' . $keys))) {
  			return array_get($this->config, $this->configKey . '.' . $keys);
  		}

  		return array_get($this->config, 'cdn_default' . '.' . $keys);
	}
}
