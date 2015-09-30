<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterMultiLanguageSchemaUseUuid extends Migration {
 /**
     * Run the migrations.
     *
     * @throws
     */
 public function up()
 {
    $builder = DB::connection()->getSchemaBuilder();
    $builder->blueprintResolver(function ($table, $callback) {
        return new OrbitBlueprint($table, $callback);
    });

    foreach($this->getColumnNames() as $args)
    {
        call_user_func_array([$this, 'alterTable'], $args);
    }
}

public function down()
{
        # DO NOTHING
}

private function alterTable($tableName, $columnName, $isNullable)
{
    $specialLength = ['session_id' => 40];
    $tableName     = DB::getTablePrefix() . $tableName;

    if (array_key_exists($columnName, $specialLength)) {
        $stmt = ("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` CHAR({$specialLength[$columnName]}) CHARACTER SET ASCII COLLATE ASCII_BIN;");
    } elseif ($isNullable == 'YES') {
        $stmt = ("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` CHAR(16) CHARACTER SET ASCII COLLATE ASCII_BIN;");
    } else {
        $stmt = ("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` CHAR(16) CHARACTER SET ASCII COLLATE ASCII_BIN NOT NULL;");
    }

    $ok = DB::statement($stmt);
    if (!$ok)
    {
        throw \Exception("FAIL: " . $stmt);
    }
}
private function getColumnNames()
{
    return [
    # table_name, column_name, IS_NULLABLE
    ['category_translations', 'category_translation_id', 'NO'],
    ['category_translations', 'category_id', 'NO'],
    ['category_translations', 'merchant_language_id', 'NO'],
    ['coupon_translations', 'coupon_translation_id', 'NO'],
    ['coupon_translations', 'promotion_id', 'NO'],
    ['coupon_translations', 'merchant_language_id', 'NO'],
    ['event_translations', 'event_translation_id', 'NO'],
    ['event_translations', 'event_id', 'NO'],
    ['event_translations', 'merchant_language_id', 'NO'],
    ['languages', 'language_id', 'NO'],
    ['merchant_languages', 'merchant_language_id', 'NO'],
    ['merchant_languages', 'language_id', 'NO'],
    ['merchant_languages', 'merchant_id', 'NO'],
    ['merchant_translations', 'merchant_translation_id', 'NO'],
    ['merchant_translations', 'merchant_id', 'NO'],
    ['merchant_translations', 'merchant_language_id', 'NO'],
    ['merchants', 'location_id', 'NO'],
    ['news_translations', 'news_translation_id', 'NO'],
    ['news_translations', 'news_id', 'NO'],
    ['news_translations', 'merchant_id', 'NO'],
    ['news_translations', 'merchant_language_id', 'NO'],
    ['promotion_translations', 'promotion_translation_id', 'NO'],
    ['promotion_translations', 'promotion_id', 'NO'],
    ['promotion_translations', 'merchant_language_id', 'NO'],
    ['promotions', 'location_id', 'YES'],
    ];
}

}
