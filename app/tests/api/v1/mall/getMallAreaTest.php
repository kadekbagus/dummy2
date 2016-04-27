<?php
/**
 * Unit test for MallGeolocAPIController::getSearchMallNearby(). Call to this
 * API does not need authentication.
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;

class getMallAreaTest extends TestCase
{
    private $baseUrl = '/api/v1/pub/mall-area';

    public function setUp()
    {
        parent::setUp();

        $_GET = [];
    }

    public function testOK_No_Mall_In_Area()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');

        //area in antartica sea
        $_GET['area'] = '-58.524626 -32.635680, -58.636791 -2.620075, -67.355143 -2.189744, -67.230556 -28.547533, -58.524626 -32.635680';

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Beruang Kutub';
        $antartica1->save();

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(0, (int)$response->data->total_records);
    }

    public function testOK_Found_One_Mall_In_Area()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');

        $_GET['area'] = '-75.325459 17.759327, -75.413558 31.432058, -77.117319 31.153023, -77.054959 20.200886, -75.325459 17.759327';

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Beruang Kutub';
        $antartica1->save();

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);
    }

    public function testOK_Found_Two_Mall_In_Area()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $_GET['area'] = '-71.193575 6.976403, -71.362837 33.695150, -77.527021 34.134603, -77.488999 8.558434, -71.193575 6.976403';

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Beruang Kutub';
        $antartica1->save();

        $antartica2 = $geofence2->mall;
        $antartica2->name = 'Beruang Hutan';
        $antartica2->save();

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }
}