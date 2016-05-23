<?php
/**
 * Unit test for MallGeolocAPIController::getSearchMallNearby(). Call to this
 * API does not need authentication.
 *
 * @author Rio Astamal <rio@dominopos.com>
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;

class getMallFenceTest extends TestCase
{
    private $baseUrl = '/api/v1/pub/mall-fence';

    public function setUp()
    {
        parent::setUp();
        Config::set('orbit.is_demo', FALSE);
        $_GET = [];
    }

    public function testOK_No_Mall_In_Fence()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');

        $_GET['latitude'] = 0;
        $_GET['longitude'] = 0;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(0, (int)$response->data->total_records);
    }

    public function testOK_Found_One_Mall_In_Fence()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');

        $_GET['latitude'] = -76.099223;
        $_GET['longitude'] = 28.738754;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);
    }

    public function testOK_Found_Two_Mall_In_Fence()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Beruang Kutub';
        $antartica1->save();

        $antartica2 = $geofence2->mall;
        $antartica2->name = 'Beruang Hutan';
        $antartica2->save();

        $_GET['latitude'] = -76.099223;
        $_GET['longitude'] = 28.738754;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }

    public function testOK_Found_One_Mall_In_Fence_With_Production_Host_Name()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Beruang Kutub';
        $antartica1->status = 'inactive';
        $antartica1->save();

        $antartica2 = $geofence2->mall;
        $antartica2->name = 'Beruang Hutan';
        $antartica2->status = 'active';
        $antartica2->save();

        $_GET['latitude'] = -76.099223;
        $_GET['longitude'] = 28.738754;
        $_GET['hostname'] = 'example3.com';

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);
    }

    public function testOK_Found_Two_Mall_In_Fence_With_Demo_Host_Name()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Beruang Kutub';
        $antartica1->status = 'inactive';
        $antartica1->save();

        $antartica2 = $geofence2->mall;
        $antartica2->name = 'Beruang Hutan';
        $antartica2->status = 'active';
        $antartica2->save();

        $_GET['latitude'] = -76.099223;
        $_GET['longitude'] = 28.738754;

        Config::set('orbit.is_demo', TRUE);

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }
}