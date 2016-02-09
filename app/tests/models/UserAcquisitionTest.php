<?php

use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;

class UserAcquisitionTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function testSave()
    {
        $user = Factory::create('User');
        $acquirer = Factory::create('Mall');
        $acq = new UserAcquisition();
        $acq->user_id = $user->user_id;
        $acq->acquirer_id = $acquirer->merchant_id;
        $acq->save();

        $a = UserAcquisition::find($acq->user_acquisition_id);
        $this->assertSame($user->user_id, $a->user_id);
        $this->assertSame($acquirer->merchant_id, $a->acquirer_id);
    }

}
