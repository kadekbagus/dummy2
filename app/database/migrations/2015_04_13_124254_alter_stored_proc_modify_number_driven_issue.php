<?php
/**
 * @author Rio Astamal <me@rioastamal.net>
 * @desc Fix `arg_number` on 'number_driven'
 */
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterStoredProcModifyNumberDrivenIssue extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create stored procedure to issue lucky draw number
        $procIssueNumber = <<<ISSUE_NUMBER
CREATE PROCEDURE `issue_lucky_draw_number` (
    -- Number of lucky draw  to be issued
    IN arg_number INT,

    -- Type of the lucky draw 'sequence', 'number_driven', or 'random' (default)
    IN arg_lucky_type VARCHAR(15),

    -- Lucky number driven
    IN arg_lucky_number TINYINT,

    -- User ID which has the lucky draw
    IN arg_user_id BIGINT,

    -- Cashier ID (user_id) which issue the lucky draw number
    IN arg_cashier_id BIGINT,

    -- Issued data for lucky draw
    IN arg_issued_date DATETIME,

    -- Status of the lucky draw
    IN arg_status VARCHAR(15)
)
BEGIN
    -- Identifier for issued lucky draw, this one is used
    -- to re-select the records after update
    SET @arg_uuid := cast(uuid() as char character set latin1);

    -- Sequencial lucky number
    if arg_lucky_type = 'sequence' then
        update {{PREFIX}}lucky_draw_numbers ldn join (
            select *
            from {{PREFIX}}lucky_draw_numbers where (user_id is NULL or user_id = 0)
            order by cast(lucky_draw_number_code AS unsigned) asc limit arg_number
        ) subtable on subtable.lucky_draw_number_id = ldn.lucky_draw_number_id
        set `ldn`.`user_id` = arg_user_id,
            `ldn`.`modified_by` = arg_cashier_id,
            `ldn`.`issued_date` = arg_issued_date,
            `ldn`.`updated_at` = arg_issued_date,
            `ldn`.`hash` = @arg_uuid;

    -- Lucky number driven
    elseif arg_lucky_type = 'number_driven' then
        update {{PREFIX}}lucky_draw_numbers ldn join (
            select * from {{PREFIX}}lucky_draw_numbers, (
                select l1.lucky_draw_number_id as ldn_id from {{PREFIX}}lucky_draw_numbers l1
                where (user_id is NULL or user_id = 0) and
                substr(l1.lucky_draw_number_code, -1) = arg_lucky_number
                order by rand() limit 5000
            ) tmp where tmp.ldn_id = {{PREFIX}}lucky_draw_numbers.lucky_draw_number_id limit arg_number
        ) subtable on subtable.lucky_draw_number_id = ldn.lucky_draw_number_id
        set `ldn`.`user_id` = arg_user_id,
            `ldn`.`modified_by` = arg_cashier_id,
            `ldn`.`issued_date` = arg_issued_date,
            `ldn`.`updated_at` = arg_issued_date,
            `ldn`.`hash` = @arg_uuid;

    -- Random lucky number generator
    else
        update {{PREFIX}}lucky_draw_numbers ldn join (
            select * from {{PREFIX}}lucky_draw_numbers, (
                -- Prevent complaints 'duplicate column is by aliasing
                select l1.lucky_draw_number_id as ldn_id from {{PREFIX}}lucky_draw_numbers l1
                where (user_id is NULL or user_id = 0)
                order by rand() limit 5000
            ) tmp where tmp.ldn_id = {{PREFIX}}lucky_draw_numbers.lucky_draw_number_id limit arg_number
        ) subtable on subtable.lucky_draw_number_id = ldn.lucky_draw_number_id
        set `ldn`.`user_id` = arg_user_id,
            `ldn`.`modified_by` = arg_cashier_id,
            `ldn`.`issued_date` = arg_issued_date,
            `ldn`.`updated_at` = arg_issued_date,
            `ldn`.`hash` = @arg_uuid;
    end if;

    -- Return the result to the caller
    -- Cast to latin1 ci to make sure comparing the same charset
    select * from {{PREFIX}}lucky_draw_numbers where
    CAST(`hash` AS CHAR CHARACTER SET latin1) = @arg_uuid;
END;

ISSUE_NUMBER;

        $prefix = DB::getTablePrefix();
        $procIssueNumber = str_replace('{{PREFIX}}', $prefix, $procIssueNumber);
        DB::unprepared('DROP PROCEDURE IF EXISTS issue_lucky_draw_number;');
        DB::unprepared($procIssueNumber);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS issue_lucky_draw_number;');
    }

}
