<?php

class SponsorProvider extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'sponsor_provider_id';

    protected $table = 'sponsor_providers';
}