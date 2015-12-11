<?php
/**
 * @author Rio Astamal <me@rioastamal.net>
 * @desc The main difference with v2 is on v3 the number is inserted to the table lucky_draw_numbers. On v2 we update the number which being generated before.
 */
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStoredProcedureIssueLuckyDrawNumberv3 extends Migration
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
CREATE PROCEDURE `issue_lucky_draw_numberv3`(
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
    IN arg_lucky_draw_id BIGINT,

    -- (11) Minimum number
    IN arg_minimum_number BIGINT
)
BEGIN
    -- counter
    declare i int default 1;

    -- Get last issued number
    set @last_issued_number := (select ifnull(max(lucky_draw_number_code), arg_minimum_number)
                                from {{PREFIX}}lucky_draw_numbers where lucky_draw_id=arg_lucky_draw_id);

    if arg_lucky_type = 'sequence' then

        -- insert each number
        while i <= arg_number do
            -- increment the lucky draw number sequencially
            set @last_issued_number := @last_issued_number + 1;

            insert into {{PREFIX}}lucky_draw_numbers
            (`lucky_draw_id`, `user_id`, `lucky_draw_number_code`, `issued_date`, `hash`, `status`, `created_by`, `created_at`)
            values
            (arg_lucky_draw_id, arg_user_id, @last_issued_number, arg_issued_date, arg_uuid, arg_status, arg_cashier_id, arg_issued_date);

            -- increment the counter
            SET i = i + 1;
        end while;
    end if;

    -- Return the result to the caller without limit
    -- Cast to latin1 ci to make sure comparing the same charset
    select count(lucky_draw_number_id) into @total_issued_number from {{PREFIX}}lucky_draw_numbers
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

        $this->down();
        DB::unprepared($procIssueNumber);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS issue_lucky_draw_numberv3;');
    }

}
