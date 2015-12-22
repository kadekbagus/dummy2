<?php
/**
 * Unit test for DominoPOS\OrbitUploader\UploaderConfig
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitUploader\UploaderConfig;

class UploaderConfigTest extends OrbitTestCase
{
    public function testInstance()
    {
        $config = new UploaderConfig(array());
        $this->assertInstanceOf('DominoPOS\OrbitUploader\UploaderConfig', $config);
    }

    public function testStaticInstance()
    {
        $config = UploaderConfig::create([]);
        $this->assertInstanceOf('DominoPOS\OrbitUploader\UploaderConfig', $config);
    }

    public function testDefaultConfigKeyShouldExists()
    {
        $configKeys = array(
            'file_type',
            'mime_type',
            'file_size',
            'path',
            'name',
            'create_directory',
            'append_year_month',
            'keep_aspect_ratio',
            'resize_image',
            'crop_image',
            'scale_image',
            'suffix',
            'resize',
            'crop',
            'scale',
            'before_saving',
            'after_saving'
        );

        $config = new UploaderConfig(array());
        foreach ($configKeys as $key) {
            $this->assertTrue( array_key_exists($key, $config->getConfig(NULL)) );
        }
    }

    public function testDefaultConfigValueDataTypes()
    {
        $configKeys = array(
            'file_type' => 'array',
            'mime_type' => 'array',
            'file_size' => 'integer',
            'path' => 'string',
            'name' => 'string',
            'create_directory' => 'boolean',
            'append_year_month' => 'boolean',
            'keep_aspect_ratio' => 'boolean',
            'resize_image' => 'boolean',
            'crop_image' => 'boolean',
            'scale_image' => 'boolean',
            'suffix' => 'string',
            'resize' => 'array',
            'crop' => 'array',
            'scale' => 'array'
        );

        $config = new UploaderConfig(array());
        foreach ($configKeys as $key=>$type) {
            if ($type === 'array') {
                $this->assertTrue( is_array($config->getConfig($key)) );
                continue;
            }

            if ($type === 'integer') {
                $this->assertTrue( is_numeric($config->getConfig($key)) );
                continue;
            }

            if ($type === 'boolean') {
                $this->assertTrue( is_bool($config->getConfig($key)) );
                continue;
            }

            if ($type === 'string') {
                $this->assertTrue( is_string($config->getConfig($key)) );
                continue;
            }
        }
    }

    public function testOverrideDefaultConfig()
    {
        $config = new UploaderConfig(array(
                'file_type' => array('.orbit', '.test'),
                'suffix'    => '-orbit'
        ));
        $this->assertSame('-orbit', $config->getConfig('suffix'));
        $this->assertSame('.orbit', $config->getConfig('file_type')[0]);
        $this->assertSame('.test', $config->getConfig('file_type')[1]);
    }

    public function testGetDottedConfig()
    {
        $config = new UploaderConfig(array(
                'resize'    => array(
                    'width'     => 555,
                    'height'    => 777
                )
        ));

        $this->assertSame(555, $config->getConfig('resize.width'));
        $this->assertSame(777, $config->getConfig('resize.height'));
    }

    public function testSetDottedConfig()
    {
        $config = new UploaderConfig(array(
                'resize'    => array(
                    'width'     => 555,
                    'height'    => 777
                )
        ));
        $config->setConfig('resize.width', 999);
        $config->setConfig('resize.height', 888);

        // Clear the cache so the getconfig read from the actual value
        $config->clearConfigCache();

        $configArr = $config->getConfig(NULL);
        $this->assertSame(999, $configArr['resize']['width']);
        $this->assertSame(888, $configArr['resize']['height']);
    }

    public function testConfigResizedImageSuffix_aspectRatioNo()
    {
        $config = new UploaderConfig(array(
                'resize'    => array(
                    'default'   => array(
                        'width'     => 600,
                        'height'    => 450
                    )
                ),
                'keep_aspect_ratio' => FALSE
        ));

        $expect = 'resized-default-600x450';
        $return = $config->getResizedImageSuffix();
        $this->assertSame($expect, $return);
    }

    public function testConfigResizedImageSuffix_aspectRatioYes()
    {
        $config = new UploaderConfig(array(
                'resize'    => array(
                    'default' => array(
                        'width'     => 600,
                        'height'    => 450
                    )
                ),
                'keep_aspect_ratio' => TRUE
        ));

        $expect = 'resized-default-auto';
        $return = $config->getResizedImageSuffix();
        $this->assertSame($expect, $return);
    }

    public function testConfigCroppedImageSuffix()
    {
        $config = new UploaderConfig(array(
                'crop'    => array(
                    'default'   => array(
                        'width'     => 320,
                        'height'    => 300
                    ),
                    'thumbnail' => array(
                        'width'     => 64,
                        'height'    => 64
                    )
                ),
        ));

        // 'default' profile
        $expect = 'cropped-default-320x300';
        $return = $config->getCroppedImageSuffix();
        $this->assertSame($expect, $return);

        // 'thumbnail' profile
        $expect = 'cropped-thumbnail-64x64';
        $return = $config->getCroppedImageSuffix('thumbnail');
        $this->assertSame($expect, $return);
    }

    public function testConfigScaledImageSuffix()
    {
        $config = new UploaderConfig(array(
                'scale' => array(
                    'default' => 75,
                    'small'   => 10
                )
        ));

        $expect = 'scaled-default-75';
        $return = $config->getScaledImageSuffix();
        $this->assertSame($expect, $return);

        $expect = 'scaled-small-10';
        $return = $config->getScaledImageSuffix('small');
        $this->assertSame($expect, $return);
    }
}
