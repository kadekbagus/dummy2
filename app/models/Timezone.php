<?php
/**
 * Model for representing the timezones table.
 *
 * @author Tian <tian@dominopos.com>
 */
class Timezone extends Eloquent
{
    use GeneratedUuidTrait;

    protected $primaryKey = 'timezone_id';
    protected $table = 'timezones';
}
