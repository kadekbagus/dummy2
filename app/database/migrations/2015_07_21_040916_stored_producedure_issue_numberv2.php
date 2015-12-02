<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class StoredProducedureIssueNumberv2 extends Migration
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
CREATE PROCEDURE `issue_lucky_draw_numberv2`(
    -- (1) Number of lucky draw  to be issued
    IN arg_number INT,

    -- (2) Type of the lucky draw 'sequence', 'number_driven', or 'random' (default)
    IN arg_lucky_type VARCHAR(15),

    -- (3) Lucky number driven
    IN arg_lucky_number TINYINT,

    -- (4) User ID which has the lucky draw
    IN arg_user_id BIGINT,

    -- (5) Cashier ID (user_id) which issue the lucky draw number
    IN arg_cashier_id BIGINT,

    -- (6) Issued date for lucky draw
    IN arg_issued_date DATETIME,

    -- (7) Status of the lucky draw
    IN arg_status VARCHAR(15),

    -- (8) Number of records returned
    IN arg_max_returned INT,

    -- (9) UUID (identifier) for each generated lucky draw number
    IN arg_uuid CHAR(40),

    -- (10) Lucky draw ID
    IN arg_lucky_draw_id BIGINT
)
BEGIN
    -- Sequencial lucky number
    if arg_lucky_type = 'sequence' then
        update {{PREFIX}}lucky_draw_numbers ldn join (
            select *
            from {{PREFIX}}lucky_draw_numbers where (user_id is NULL or user_id = 0)
            and lucky_draw_id=arg_lucky_draw_id
            order by cast(lucky_draw_number_code AS unsigned) asc limit arg_number
        ) subtable on subtable.lucky_draw_number_id = ldn.lucky_draw_number_id
        set `ldn`.`user_id` = arg_user_id,
            `ldn`.`modified_by` = arg_cashier_id,
            `ldn`.`issued_date` = arg_issued_date,
            `ldn`.`updated_at` = arg_issued_date,
            `ldn`.`hash` = arg_uuid;

    -- Lucky number driven
    elseif arg_lucky_type = 'number_driven' then
        update {{PREFIX}}lucky_draw_numbers ldn join (
            select * from {{PREFIX}}lucky_draw_numbers, (
                select l1.lucky_draw_number_id as ldn_id from {{PREFIX}}lucky_draw_numbers l1
                where (user_id is NULL or user_id = 0) and
                substr(l1.lucky_draw_number_code, -1) = arg_lucky_number and
                lucky_draw_id=@arg_lucky_draw_id
                order by rand() limit 5000
            ) tmp where tmp.ldn_id = {{PREFIX}}lucky_draw_numbers.lucky_draw_number_id limit arg_number
        ) subtable on subtable.lucky_draw_number_id = ldn.lucky_draw_number_id
        set `ldn`.`user_id` = arg_user_id,
            `ldn`.`modified_by` = arg_cashier_id,
            `ldn`.`issued_date` = arg_issued_date,
            `ldn`.`updated_at` = arg_issued_date,
            `ldn`.`hash` = arg_uuid;

    -- Random lucky number generator
    else
        update {{PREFIX}}lucky_draw_numbers ldn join (
            select * from {{PREFIX}}lucky_draw_numbers, (
                -- Prevent complaints 'duplicate column is by aliasing
                select l1.lucky_draw_number_id as ldn_id from {{PREFIX}}lucky_draw_numbers l1
                where (user_id is NULL or user_id = 0) and
                lucky_draw_id=arg_lucky_draw_id
                order by rand() limit 5000
            ) tmp where tmp.ldn_id = {{PREFIX}}lucky_draw_numbers.lucky_draw_number_id limit arg_number
        ) subtable on subtable.lucky_draw_number_id = ldn.lucky_draw_number_id
        set `ldn`.`user_id` = arg_user_id,
            `ldn`.`modified_by` = arg_cashier_id,
            `ldn`.`issued_date` = arg_issued_date,
            `ldn`.`updated_at` = arg_issued_date,
            `ldn`.`hash` = arg_uuid;
    end if;

    -- Return the result to the caller without limit
    -- Cast to latin1 ci to make sure comparing the same charset
    select count(*) into @total_issued_number from {{PREFIX}}lucky_draw_numbers
    where cast(`hash` as char character set latin1) = arg_uuid;

    -- Return the result to the caller with limit
    select *, @total_issued_number as total_issued_number
    from {{PREFIX}}lucky_draw_numbers where
    cast(`hash` as char character set latin1) = arg_uuid
    limit arg_max_returned;
END;

ISSUE_NUMBER;

        $prefix = DB::getTablePrefix();
        $procIssueNumber = str_replace('{{PREFIX}}', $prefix, $procIssueNumber);
        DB::unprepared('DROP PROCEDURE IF EXISTS issue_lucky_draw_numberv2;');
        DB::unprepared($procIssueNumber);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS issue_lucky_draw_numberv2;');
    }
}
