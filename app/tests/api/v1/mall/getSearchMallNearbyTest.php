<?php
/**
 * Unit test for MallGeolocAPIController::getSearchMallNearby(). Call to this
 * API does not need authentication.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Laracasts\TestDummy\Factory;

class getSearchMallNearBy extends TestCase
{
    private $baseUrl = '/api/v1/pub/mall-nearby';
    private $myAntarticaLocation = [-76.099223, 28.738754];

    public function setUp()
    {
        parent::setUp();

        $_GET = [];
    }

    public function testOK_No_Mall_Found_Nearby()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        $_GET['distance'] = 0.1;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(0, (int)$response->data->total_records);
    }

    public function testOK_Found_One_Mall_Nearby()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        // The distance is 99.xx
        $_GET['distance'] = 100;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);
    }

    public function testOK_Found_Two_Mall_Nearby()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence = Factory::create('MerchantGeofence_Antartica2');

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        // The distance to the antartica 2 is 610.xx
        $_GET['distance'] = 611;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }
}