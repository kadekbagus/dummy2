<?php
/**
 * Unit test for DominoPOS\OrbitUploader\Uploader
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitUploader\UploaderMessage;
use DominoPOS\OrbitUploader\UploaderConfig;
use DominoPOS\OrbitUploader\Uploader;

class UploaderTest extends OrbitTestCase
{
    public function testInstance()
    {
        $uploader = new Uploader(
            UploaderConfig::create([]),
            UploaderMessage::create([])
        );
        $this->assertInstanceOf('DominoPOS\OrbitUploader\Uploader', $uploader);
    }

    public function testStaticInstance()
    {
        $uploader = Uploader::create(
            UploaderConfig::create([]),
            UploaderMessage::create([])
        );
        $this->assertInstanceOf('DominoPOS\OrbitUploader\Uploader', $uploader);
    }

    public function testUploaderGetConfig()
    {
        $uploader = Uploader::create(
            UploaderConfig::create([]),
            UploaderMessage::create([])
        );
        $this->assertInstanceOf('DominoPOS\OrbitUploader\UploaderConfig', $uploader->getUploaderConfig());
    }

    public function testUploaderGetMessage()
    {
        $uploader = Uploader::create(
            UploaderConfig::create([]),
            UploaderMessage::create([])
        );
        $this->assertInstanceOf('DominoPOS\OrbitUploader\UploaderMessage', $uploader->getUploaderMessage());
    }

    public function testUploaderChangeConfigSettings()
    {
        $config = array(
            'suffix' => '-orbit'
        );
        $uploader = Uploader::create(
            UploaderConfig::create($config),
            UploaderMessage::create([])
        );
        $this->assertSame('-orbit', $uploader->getUploaderConfig()->getConfig('suffix'));

        // Change the settings
        $uploaderConfig = $uploader->getUploaderConfig()->setConfig('suffix', '-cool');
        $this->assertSame('-cool', $uploader->getUploaderConfig()->getConfig('suffix'));
    }
}
