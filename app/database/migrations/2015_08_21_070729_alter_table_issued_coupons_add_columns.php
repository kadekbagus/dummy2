<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableIssuedCouponsAddColumns extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('issued_coupons', function(Blueprint $table)
        {
            $table->integer('redeem_retailer_id')->unsigned()->nullable()->after('issuer_retailer_id');
            $table->string('redeem_verification_code', 20)->nullable()->after('redeem_retailer_id');

            $table->index(array('redeem_retailer_id'), 'redeem_retailer_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('issued_coupons', function(Blueprint $table)
        {
            $table->dropIndex('redeem_retailer_id_idx');
            $table->dropColumn('redeem_verification_code');
            $table->dropColumn('redeem_retailer_id');
        });
    }

}
