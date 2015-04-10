<?php
/**
 * Unit test for OrbitShop API Controller version 1.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ControllerAPI;

/**
 * Concrete implementation of class OrbitShop\API\v1\ControllerAPI. I think it
 * is best to create a subclass instead of just a Mockup.
 */
class DummyCtlApi extends OrbitShop\API\v1\ControllerAPI
{
    public function hello()
    {
        $this->response->data = array(
            0 => 'hello',
            1 => 'world'
        );

        return $this->render();
    }
}

class ControllerAPITest extends OrbitTestCase
{
    public function testObjectInstance()
    {
        $ctl = new DummyCtlApi();
        $this->assertInstanceOf('OrbitShop\API\v1\ControllerAPI', $ctl);
    }

    public function testReturnJSON()
    {
        $data = new stdClass();
        $data->code = 0;
        $data->status = 'success';
        $data->message = 'Request OK';
        $data->data = array('hello', 'world');
        $expect = json_encode($data);

        $ctl = new DummyCtlApi();
        $return = $ctl->hello();

        // http body
        $this->assertSame($expect, $return->getContent());

        // http status code
        $this->assertSame(200, $return->getStatusCode());
    }

    public function testReturnRaw_ResponseProvider()
    {
        $ctl = new DummyCtlApi('raw');
        $expect = 'OrbitShop\API\v1\ResponseProvider';
        $return = $ctl->hello();

        $this->assertInstanceOf($expect, $return);
        $this->assertSame(0, $return->code);
        $this->assertSame('success', $return->status);
        $this->assertSame('Request OK', $return->message);

        $data = $return->data;
        $this->assertSame('hello', $data[0]);
        $this->assertSame('world', $data[1]);
    }

    public function testMethodNotFoundRaw()
    {
        $ctl = new DummyCtlApi('raw');
        $return = $ctl->nonExists();
        $expect = 'OrbitShop\API\v1\ResponseProvider';

        $this->assertInstanceOf($expect, $return);
        $this->assertSame(404, $return->code);
        $this->assertSame('error', $return->status);
        $this->assertSame('Request URL not found', $return->message);
        $this->assertTrue(is_null($return->data));
    }

    public function testMethodNotFoundJson()
    {
        $ctl = new DummyCtlApi();
        $return = $ctl->nonExists();

        $data = new stdClass();
        $data->code = 404;
        $data->status = 'error';
        $data->message = 'Request URL not found';
        $data->data = NULL;
        $expect = json_encode($data);

        $this->assertInstanceOf('Illuminate\Http\Response', $return);
        $this->assertSame(404, $return->getStatusCode());
        $this->assertSame($expect, $return->getContent());
    }
}
