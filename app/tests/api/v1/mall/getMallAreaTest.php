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
        $_SERVER['HTTP_HOST'] = 'example2.com';

        $_GET = [];
    }

    public function testOK_No_Mall_In_Area()
    {
        // Create mall in antartica
        $geofence = Factory::create('MerchantGeofence');

        //area in antartica sea
        $_GET['area'] = '-61.93644640661632 57.95057812426762, -39.97304109648114 57.95057812426762, -39.97304109648114 91.34901562426762, -61.93644640661632 91.34901562426762, -61.93644640661632 57.95057812426762';

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
        $geofence = Factory::create('MerchantGeofence_Antartica2');

        $_GET['area'] = '-75.18442825097793 8.698869139892622, -69.85292893887913 8.698869139892622, -69.85292893887913 25.398087889892622, -75.18442825097793 25.398087889892622, -75.18442825097793 8.698869139892622';

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Beruang Hutan';
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

        $_GET['area'] = '-81.85111601628252 -12.252058594482378, -62.111671481762606 -12.252058594482378, -62.111671481762606 54.54481640551762, -81.85111601628252 54.54481640551762, -81.85111601628252 -12.252058594482378';

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

    public function testOK_Found_Two_Mall_In_Area_Cross_International_Date_Line()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $_GET['area'] = '-84.78803812147538 -49.64951953198238, 73.06899542190443 -49.64951953198238, 73.06899542190443 -142.46201953198238, -84.78803812147538 -142.46201953198238, -84.78803812147538 -49.64951953198238';

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

    public function testOK_Found_One_Mall_In_Area_With_Production_Host_Name()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $_GET['area'] = '-81.85111601628252 -12.252058594482378, -62.111671481762606 -12.252058594482378, -62.111671481762606 54.54481640551762, -81.85111601628252 54.54481640551762, -81.85111601628252 -12.252058594482378';
        $_GET['hostname'] = 'example3.com';

        $antartica1 = $geofence->mall;
        $antartica1->name = 'Beruang Kutub';
        $antartica1->status = 'inactive';
        $antartica1->save();

        $antartica2 = $geofence2->mall;
        $antartica2->name = 'Beruang Hutan';
        $antartica2->status = 'active';
        $antartica2->save();

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(1, (int)$response->data->total_records);
    }

    public function testOK_Found_Two_Mall_In_Area_With_Demo_Host_Name()
    {
        // Create 2 malls in antartica
        $geofence = Factory::create('MerchantGeofence');
        $geofence2 = Factory::create('MerchantGeofence_Antartica2');

        $_GET['area'] = '-81.85111601628252 -12.252058594482378, -62.111671481762606 -12.252058594482378, -62.111671481762606 54.54481640551762, -81.85111601628252 54.54481640551762, -81.85111601628252 -12.252058594482378';
        
        $antartica1 = $geofence->mall;
        $antartica1->name = 'Beruang Kutub';
        $antartica1->status = 'inactive';
        $antartica1->save();

        $antartica2 = $geofence2->mall;
        $antartica2->name = 'Beruang Hutan';
        $antartica2->status = 'active';
        $antartica2->save();

        $response = $this->call('GET', $this->baseUrl)->getContent();
        $response = json_decode($response);

        $this->assertSame(Status::OK, (int)$response->code);
        $this->assertSame(2, (int)$response->data->total_records);
    }
}