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

class getSearchMallNearBy extends TestCase
{
    private $baseUrl = '/api/v1/pub/mall-nearby';
    private $myAntarticaLocation = [-76.099223, 28.738754];

    public function setUp()
    {
        parent::setUp();
        $_SERVER['HTTP_HOST'] = 'example2.com';
        
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

    public function testOK_Found_One_Mall_Nearby_Dummy_opening_hours_Attribute()
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

        $expect = 'Sun - Mon 10.00 - 22.00';
        $this->assertSame($expect, $response->data->records[0]->operating_hours);
    }

    public function testOK_Found_Two_Mall_Nearby()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        // The distance to the antartica 2 is 610.xx
        $_GET['distance'] = 611;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }

    public function testOK_Found_Two_Mall_Take_One_Only()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Antartica 1';
        $antartica1->save();

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        // The distance to the antartica 2 is 610.xx
        $_GET['distance'] = 611;
        $_GET['take'] = 1;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The first mall should be Antartica 1, because by default
        // sorted by the nearest
        $this->assertSame('Antartica 1', $response->data->records[0]->name);
    }

    public function testOK_Found_One_Mall_Search_By_Antartica_Keyword()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Antartica 1';
        $antartica1->save();

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        // The distance to the antartica 2 is 610.xx
        $_GET['distance'] = -1;
        $_GET['keyword_search'] = 'antartica';

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The first mall should be Antartica 1, because by default
        // sorted by the nearest
        $this->assertSame('Antartica 1', $response->data->records[0]->name);
    }


    public function testOK_Found_Two_Mall_Search_OrderBy_Name()
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

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        // The distance to the antartica 2 is 610.xx
        $_GET['distance'] = -1;
        $_GET['keyword_search'] = 'beruang';

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
        $this->assertSame(2, (int)$response->data->returned_records);

        // The first mall should be Beruang Hutan, because by searching
        // by keyword will trigger sorting by name
        $this->assertSame('Beruang Hutan', $response->data->records[0]->name);
    }

    public function testOK_Found_One_Mall_OrderBy_distance()
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

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        // The distance to the antartica 2 is 610.xx
        $_GET['distance'] = -1;
        $_GET['sortby'] = 'distance';
        $_GET['sortmode'] = 'asc';
        $_GET['take'] = 1;

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
        $this->assertSame(1, (int)$response->data->returned_records);

        // The first mall should be Beruang Hutan, because by searching
        // by keyword will trigger sorting by name
        $this->assertSame('Beruang Kutub', $response->data->records[0]->name);
    }

    public function testOK_Get_Mall_NearBy_With_Production_Host_Name()
    {
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

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        $_GET['hostname'] = 'example3.com';
        // The distance to the antartica 2 is 610.xx
        $_GET['distance'] = 611;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);
    }

    public function testOK_Get_Mall_NearBy_With_Demo_Host_Name()
    {
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

        $_GET['latitude'] = $this->myAntarticaLocation[0];
        $_GET['longitude'] = $this->myAntarticaLocation[1];
        // The distance to the antartica 2 is 610.xx
        $_GET['distance'] = 611;

        $url = $this->baseUrl . '?' . http_build_query($_GET);

        $response = $this->call('GET', $url)->getContent();
        $response = json_decode($response);
        
        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }
}