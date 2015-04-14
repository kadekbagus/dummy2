<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTablePromotionsAddColumns extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promotions', function(Blueprint $table)
        {
            $table->text('long_description')->nullable()->after('description');
            $table->datetime('coupon_validity_in_date')->nullable()->after('coupon_validity_in_days');
            $table->string('maximum_issued_coupon_type', 15)->nullable()->after('is_coupon');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promotions', function(Blueprint $table)
        {
            $table->dropColumn('maximum_issued_coupon_type');
            $table->dropColumn('coupon_validity_in_date');
            $table->dropColumn('long_description');
        });
    }

}
