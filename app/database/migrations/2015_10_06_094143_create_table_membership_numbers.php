<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableMembershipNumbers extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $conn = DB::connection();
        OrbitMySqlSchemaGrammar::useFor($conn);
        $builder = $conn->getSchemaBuilder();
        $builder->blueprintResolver(function ($table, $callback) {
            return new OrbitBlueprint($table, $callback);
        });
        $builder->create('membership_numbers', function (OrbitBlueprint $table) {
            $table->encodedId('membership_number_id');
            $table->encodedId('membership_id');
            $table->encodedId('user_id')->nullable();
            $table->string('membership_number', 50)->nullable();
            $table->datetime('expired_date')->nullable();
            $table->datetime('join_date')->nullable();
            $table->encodedId('issuer_merchant_id')->nullable();
            $table->string('status', 15);
            $table->encodedId('created_by')->nullable();
            $table->encodedId('modified_by')->nullable();
            $table->primary('membership_number_id');
            $table->timestamps();

            $table->index(array('membership_id'), 'membership_id_idx');
            $table->index(array('user_id'), 'user_id_idx');
            $table->index(array('membership_number'), 'membership_number_idx');
            $table->index(array('issuer_merchant_id'), 'issuer_merchant_id_idx');
            $table->index(array('status'), 'status_idx');
            $table->index(array('created_by'), 'created_by_idx');
            $table->index(array('modified_by'), 'modified_by_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('membership_numbers');
    }

}
