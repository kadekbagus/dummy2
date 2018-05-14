<?php
use Orbit\Helper\Sepulsa\API\CampaignList;
use Orbit\Helper\Sepulsa\API\CampaignDetail;
use Orbit\Helper\Sepulsa\API\Login;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

// get campaign list
Route::get('/app/v1/sepulsa/campaign/list', function() {
    $searchQuery = OrbitInput::get('search');
    $recordPerPage = OrbitInput::get('take', 10);
    $filters = OrbitInput::get('filters', []);
    $page = OrbitInput::get('page', 1);
    $response = CampaignList::create()->getList($searchQuery, $recordPerPage, $filters, $page);

    return \Response::json($response);
});

// get campaign detail
Route::get('/app/v1/sepulsa/campaign/detail', function() {
    $campaignId = OrbitInput::get('campaign_id');
    $response = CampaignDetail::create()->getDetail($campaignId);

    return \Response::json($response);
});