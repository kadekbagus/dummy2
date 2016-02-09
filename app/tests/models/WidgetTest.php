<?php

/**
 * Unit test for Widget model.
 * @author kadek <kadek@dominopos.com>
 *
**/

use OrbitShop\API\v1\Helper\Generator;
use Laracasts\TestDummy\Factory;
use Faker\Factory as Faker;

class WidgetTest extends TestCase
{
    public function testAllowedForUser()
    {

        // user super admin
        $super_admin = (object) array(
            'user_id' => '1',
            'user_email' => 'super@admin.com',
            'role' => (object) array(
                'role_id' => '1',
                'role_name' => 'super admin'
            ),
         );

        // user customer
        $customer = (object) array(
            'user_id' => '2',
            'user_email' => 'customer@myorbit.com',
            'role' => (object) array(
                'role_id' => '2',
                'role_name' => 'customer'
            ),
         );

        // user mall admin A
        $mall_admin_a = (object) array(
            'user_id' => '3',
            'user_email' => 'mall_admin1@myorbit.com',
            'role' => (object) array(
                'role_id' => '3',
                'role_name' => 'mall admin'
            ),
         );

        // user mall admin B
        $mall_admin_b = (object) array(
            'user_id' => '4',
            'user_email' => 'mall_admin2@myorbit.com',
            'role' => (object) array(
                'role_id' => '3',
                'role_name' => 'mall admin'
            ),
         );

        // user mall group C
        $mall_admin_c = (object) array(
            'user_id' => '5',
            'user_email' => 'mall_group@myorbit.com',
            'role' => (object) array(
                'role_id' => '3',
                'role_name' => 'mall group'
            ),
         );

        // mall group c (parent of mall A)
        $mall_group_c = new MallGroup();
        $mall_group_c->user_id = $mall_admin_c->user_id;
        $mall_group_c->orid = '';
        $mall_group_c->email = $mall_admin_c->user_email;
        $mall_group_c->name = 'MALL GROUP C';
        $mall_group_c->save();

        // mall A
        $mall_a = new Mall();
        $mall_a->user_id = $mall_admin_a->user_id;
        $mall_a->omid = '';
        $mall_a->email = $mall_admin_a->user_email;
        $mall_a->name = 'MALL A';
        $mall_a->parent_id = $mall_group_c->merchant_id;
        $mall_a->save();

        // mall B
        $mall_b = new Mall();
        $mall_b->user_id = $mall_admin_b->user_id;
        $mall_b->omid = '';
        $mall_b->email = $mall_admin_b->user_email;
        $mall_b->name = 'MALL B';
        $mall_b->save();

        // create employee and employee retailer for mall A
        $employee_mall_a = new Employee();
        $employee_mall_a->user_id = $mall_admin_a->user_id;
        $employee_mall_a->employee_id_char = '';
        $employee_mall_a->position = '';
        $employee_mall_a->status = 'active';
        $employee_mall_a->save();

        $employee_retailer_mall_a = new EmployeeRetailer();
        $employee_retailer_mall_a->employee_id = $employee_mall_a->employee_id;
        $employee_retailer_mall_a->retailer_id = $mall_a->merchant_id;
        $employee_retailer_mall_a->save();

        // create employee and employee retailer for mall B
        $employee_mall_b = new Employee();
        $employee_mall_b->user_id = $mall_admin_b->user_id;
        $employee_mall_b->employee_id_char = '';
        $employee_mall_b->position = '';
        $employee_mall_b->status = 'active';
        $employee_mall_b->save();

        $employee_retailer_mall_b = new EmployeeRetailer();
        $employee_retailer_mall_b->employee_id = $employee_mall_b->employee_id;
        $employee_retailer_mall_b->retailer_id = $mall_b->merchant_id;
        $employee_retailer_mall_b->save();


        // widget mall A
        $widget_mall_a = new Widget();
        $widget_mall_a->merchant_id = $mall_a->merchant_id;
        $widget_mall_a->widget_slogan = 'Widget MALL A';
        $widget_mall_a->widget_type = 'promotion';
        $widget_mall_a->status = 'active';
        $widget_mall_a->save();

        // widget mall B
        $widget_mall_b = new Widget();
        $widget_mall_b->merchant_id = $mall_b->merchant_id;
        $widget_mall_b->widget_slogan = 'Widget MALL B';
        $widget_mall_b->widget_type = 'promotion';
        $widget_mall_b->status = 'active';
        $widget_mall_b->save();


        // testing user super admin
        $result = Widget::AllowedForUser($super_admin)->excludeDeleted()->get();

        $this->assertSame($widget_mall_a->merchant_id, $result[0]->merchant_id);
        $this->assertSame($widget_mall_a->event_name, $result[0]->event_name);
        $this->assertSame($widget_mall_a->event_type, $result[0]->event_type);

        $this->assertSame($widget_mall_b->merchant_id, $result[1]->merchant_id);
        $this->assertSame($widget_mall_b->event_name, $result[1]->event_name);
        $this->assertSame($widget_mall_b->event_type, $result[1]->event_type);


        // testing user customer
        $result = Widget::AllowedForUser($customer)->excludeDeleted()->get();

        $this->assertSame($widget_mall_a->merchant_id, $result[0]->merchant_id);
        $this->assertSame($widget_mall_a->event_name, $result[0]->event_name);
        $this->assertSame($widget_mall_a->event_type, $result[0]->event_type);

        $this->assertSame($widget_mall_b->merchant_id, $result[1]->merchant_id);
        $this->assertSame($widget_mall_b->event_name, $result[1]->event_name);
        $this->assertSame($widget_mall_b->event_type, $result[1]->event_type);


        // testing user mall admin A
        // should get only events from mall A
        $result = Widget::AllowedForUser($mall_admin_a)->excludeDeleted()->get();

        $this->assertSame($widget_mall_a->merchant_id, $result[0]->merchant_id);
        $this->assertSame($widget_mall_a->event_name, $result[0]->event_name);
        $this->assertSame($widget_mall_a->event_type, $result[0]->event_type);

        // testing user mall admin B
        // should get only events from mall B
        $result = Widget::AllowedForUser($mall_admin_b)->excludeDeleted()->get();

        $this->assertSame($widget_mall_b->merchant_id, $result[0]->merchant_id);
        $this->assertSame($widget_mall_b->event_name, $result[0]->event_name);
        $this->assertSame($widget_mall_b->event_type, $result[0]->event_type);

        // testing user mall admin C (mall group)
        // should get events from mall A, because mall group C is the parent of mall A
        $result = Widget::AllowedForUser($mall_admin_c)->excludeDeleted()->get();

        $this->assertSame($widget_mall_a->merchant_id, $result[0]->merchant_id);
        $this->assertSame($widget_mall_a->event_name, $result[0]->event_name);
        $this->assertSame($widget_mall_a->event_type, $result[0]->event_type);

    }
}