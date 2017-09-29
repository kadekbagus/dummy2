<?php

class Bank extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'bank_id';

    protected $table = 'banks';
}