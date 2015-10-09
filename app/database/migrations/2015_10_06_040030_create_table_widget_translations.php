<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableWidgetTranslations extends Migration {

  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    $builder = DB::connection()->getSchemaBuilder();
    $builder->blueprintResolver(function ($table, $callback) {
      return new OrbitBlueprint($table, $callback);
    });
    $builder->create('widget_translations', function(OrbitBlueprint $table)
    {
      $table->encodedId('widget_translation_id');
      $table->encodedId('widget_id');
      $table->encodedId('merchant_language_id');
      $table->string('widget_slogan', 255)->nullable();
      $table->string('status', 15)->default('active');
      $table->timestamps();
      $table->index(['widget_id', 'merchant_language_id'], 'widget_language_idx');
      $table->index(['status'], 'status_idx');
      $table->encodedId('created_by')->nullable();
      $table->encodedId('modified_by')->nullable();
      $table->index(['created_by'], 'created_by_idx');
      $table->index(['modified_by'], 'modified_by_idx');
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    Schema::drop('widget_translations');
  }

}
