<?php

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class MacAddressTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Factory::times(10)->create('MacAddress');
    }

    public function testOK_query_mac_address()
    {
        $this->assertSame(10, MacAddress::all()->count());
    }

    public function testOK_insert_valid_mac_address()
    {
        Factory::create('MacAddress', ['mac_address' => 'ff:ff:ff:ff:ff:ff']);

        $this->assertSame(1, MacAddress::where('mac_address', 'ff:ff:ff:ff:ff:ff')->count());
    }
}
