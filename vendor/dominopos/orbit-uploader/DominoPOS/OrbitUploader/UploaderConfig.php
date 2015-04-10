<?php namespace DominoPOS\OrbitUploader;
/**
 * Library for storing or getting the configuration of the Uploader library.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class UploaderConfig
{
    /**
     * List of configuration passed by the user.
     *
     * @var array
     */
    protected $config = array();

    /**
     * List of default configuration.
     *
     * @var array
     */
    protected $default = array();

    /**
     * Store the cached config
     *
     * @var array
     */
    protected $cachedConfig = array();

    /**
     * Class constructor for setting the default configuration
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->default = array(
                // Allowed Extension
                'file_type'     => array('jpg', 'png'),

                // Allowed Mimetype
                'mime_type'     => array('image/jpeg', 'image/png'),

                // Maximum file size allowed in bytes
                'file_size'     => 50000,

                // The target path which file to be stored
                'path'          => 'uploads',

                // Default HTML element name
                'name'          => 'images',

                // Force create directory when not exists
                'create_directory'  => TRUE,

                // Append year and month to the path
                'append_year_month' => TRUE,

                // Keeping the aspect ratio
                'keep_aspect_ratio' => TRUE,

                // Resize the image
                'resize_image'      => TRUE,

                // Crop the image
                'crop_image'        => TRUE,

                // Scale the image
                'scale_image'       => FALSE,

                // Name suffix of the generated image
                'suffix'            => '',

                // Resize the image
                'resize'        => array(
                    // Profile name
                    'default'   => array(
                        'width'     => 200,
                        'height'    => 200
                    )
                ),

                // Crop the image
                'crop'          => array(
                    // Profile name
                    'default' => array(
                        'width'     => 100,
                        'height'    => 100,
                    )
                ),

                // Scale the image in percent
                'scale'         => array(
                    // Profile name
                    'default'   => 50
                ),

                // Callback before saving the file
                'before_saving' => NULL,

                // Callback after saving the file
                'after_saving'  => NULL,
            );

        $this->config = $config + $this->default;
    }

    /**
     * Static method to create the object
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $config
     * @return UploaderConfig
     */
    public static function create(array $config)
    {
        return new static($config);
    }

    /**
     * Method to get suffix for cropped image. If the current size of crop
     * config are 'width' => 64, 'height' => 64 and the key was 'default'
     * then the output would like this:
     *
     *   'cropped-default-64x64'
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getCroppedImageSuffix($profile='default')
    {
        if (! isset($this->config['crop'][$profile])) {
            $profile = 'default';
        }
        $cropConfig = $this->config['crop'][$profile];

        return sprintf('cropped-%s-%sx%s', $profile, $cropConfig['width'], $cropConfig['height']);
    }

    /**
     * Method to get suffix for resized image. If the current size of resize
     * config are 'width' => 200, 'height' => 200 and the key was 'default'
     * then the output would like
     * this:
     *
     *   'resized-default-200x200'
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getResizedImageSuffix($profile='default')
    {
        if (! isset($this->config['resize'][$profile])) {
            $profile = 'default';
        }
        $resizeConfig = $this->config['resize'][$profile];

        if ($this->config['keep_aspect_ratio'] !== TRUE)
        {
            return sprintf('resized-%s-%sx%s',
                            $profile,
                            $resizeConfig['width'],
                            $resizeConfig['height']
            );
        }

        return sprintf('resized-%s-auto', $profile);
    }

    /**
     * Method to get suffix for scaled image. If the current size of scaled
     * config are 'scaled' => 50 then the output would like this:
     *
     *   'scaled-default-50'
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getScaledImageSuffix($profile='default')
    {
        if (! isset($this->config['scale'][$profile])) {
            $profile = 'default';
        }
        $scaledConfig = $this->config['scale'][$profile];

        return sprintf('scaled-%s-%s', $profile, $scaledConfig);
    }

    /**
     * Get uploader config using dotted configuration.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key Config name
     * @param mixed $defualt Default value
     * @return mixed
     */
    public function getConfig($key, $default=NULL)
    {
        // Check inside cache first
        if (array_key_exists($key, $this->cachedConfig)) {
            return $this->cachedConfig[$key];
        }

        $config = static::getConfigVal($key, $this->config, $default);
        $this->cachedConfig[$key] = $config;

        return $config;
    }

    /**
     * Set uploader config using dotted configuration
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key Config name
     * @param mixed $value Value of the config
     * @return UploaderConfig
     */
    public function setConfig($key, $value)
    {
        static::setConfigVal($key, $value, $this->config);

        // Update the cache
        $this->cachedConfig[$key] = $value;

        return $this;
    }

    /**
     * Clear the config cache.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return UploaderConfig
     */
    public function clearConfigCache()
    {
        $this->cachedConfig = array();

        return $this;
    }

    /**
     * Static method for getting configuration from an array using the "." dot
     * notation.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit Laravel Illuminate\Support\Arr
     *
     * @param string $key The dotted configuration name
     * @param array $key Source of the config
     * @param mixed $default Default value given when no matching config found
     * @return mixed
     */
    public static function getConfigVal($key, $array, $default=NULL)
    {
        // Return all config
        if (is_null($key)) {
            return $array;
        }

        // No need further check if we found the element at first
        if (isset($array[$key])) {
            return $array[$key];
        }

        // Split the key by dot notation and loop through the array
        foreach (explode('.', $key) as $keyname) {
            //     Does this $key exists?
            if (! array_key_exists($keyname, $array)) {
                return $default;
            }

            // Set the value of $copy to the next array value
            // Left to Right till the last key
            $array = $array[$keyname];
        }

        return $array;
    }

    /**
     * Static method for setting configuration using "." dot notation.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit Laravel Illuminate\Support\Arr
     *
     * @param string $key The dotted name of the configuration
     * @param mixed $value Value of the new config
     * @param array &$array Array target of the new configuration
     * @return array
     */
    public static function setConfigVal($key, $value, &$array)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        // Explode the key by dot notation
        $keys = explode('.', $key);

        // Loop through the key except for the last one
        while (count($keys) > 1) {
            // shift the first element of the keys
            $key = array_shift($keys);

            // If one of the key does not exists on the original array
            // then we need create it so we can reach the last array
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = array();
            }

            // Rebuild the array till before the last one
            $array =& $array[$key];
        }

        // Get the last key and assign it to the array
        $array[array_shift($keys)] = $value;

        return $array;
    }
}
