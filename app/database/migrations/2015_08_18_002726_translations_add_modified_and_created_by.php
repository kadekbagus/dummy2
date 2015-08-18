<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TranslationsAddModifiedAndCreatedBy extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (['merchant_translations', 'event_translations', 'category_translations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->bigInteger('created_by')->unsigned()->nullable();
                $table->bigInteger('modified_by')->unsigned()->nullable();
                $table->index(['created_by'], 'created_by_idx');
                $table->index(['modified_by'], 'modified_by_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach (['merchant_translations', 'event_translations', 'category_translations'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['created_by', 'modified_by']);
            });
        }
    }

}
