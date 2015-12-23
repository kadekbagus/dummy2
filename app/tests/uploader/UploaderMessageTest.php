<?php
/**
 * Unit test for DominoPOS\OrbitUploader\UploaderMessage
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitUploader\UploaderMessage;

class UploaderMessageTest extends OrbitTestCase
{
    public function testInstance()
    {
        $message = new UploaderMessage(array());
        $this->assertInstanceOf('DominoPOS\OrbitUploader\UploaderMessage', $message);
    }

    public function testStaticInstance()
    {
        $message = UploaderMessage::create(array());
        $this->assertInstanceOf('DominoPOS\OrbitUploader\UploaderMessage', $message);
    }

    public function testDefaultMessageKeyShouldExists()
    {
        $messagesKeys = array(
            'success',
            'errors'
        );

        $message = new UploaderMessage(array());
        foreach ($messagesKeys as $key) {
            $this->assertTrue( array_key_exists($key, $message->getMessage(NULL)) );
        }

        $messagesKeysErrors = array(
            'unknown_error',
            'no_file_uploaded',
            'path_not_found',
            'no_write_access',
            'file_too_big',
            'file_type_not_allowed',
            'mime_type_not_allowed',
            'dimension_not_allowed',
            'unable_to_upload'
        );
        foreach ($messagesKeysErrors as $key) {
            $this->assertTrue( array_key_exists($key, $message->getMessage(NULL)['errors']) );
        }

        $messagesKeysSuccess = array(
            'upload_ok',
        );
        foreach ($messagesKeysSuccess as $key) {
            $this->assertTrue( array_key_exists($key, $message->getMessage(NULL)['success']) );
        }
    }

    public function testOverrideDefaultConfig()
    {
        $message = new UploaderMessage(array(
                'errors' => array(
                    'whoa' => 'whooaa there is some error bro!.'
                )
        ));
        $this->assertSame('whooaa there is some error bro!.', $message->getMessage('errors')['whoa']);
    }

    public function testGetDottedConfig()
    {
        $message = new UploaderMessage(array(
                'errors' => array(
                    'whoa' => 'whooaa there is some error bro!.',
                    'bad'  => 'Something bad happens bro!.',
                )
        ));
        $this->assertSame('Something bad happens bro!.', $message->getMessage('errors.bad'));
    }

    public function testMessageSubtituted()
    {
        $message = new UploaderMessage(array(
                'success' => array(
                    'cool' => 'Your cool factor number is :number.',
                )
        ));

        $coolFactor = $message->getMessage('success.cool', array('number' => '99%'));
        $expect = 'Your cool factor number is 99%.';

        $this->assertSame($expect, $coolFactor);
    }
}
