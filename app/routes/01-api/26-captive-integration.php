<?php
/**
 * Routes for integration with Orbit Captive Portal
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
Route::group(['before' => 'orbit-settings'], function() {
    /**
     * Route called when user are leave the network
     */
    Route::get('/api/v1/captive-portal/network/leave', [
        'as' => 'captive-portal-network-leave',
        'uses' => 'CaptiveIntegrationAPIController@getUserOutOfNetwork'
    ]);

    /**
     * Route called when user are enter the network
     */
    Route::get('/api/v1/captive-portal/network/enter', [
        'as' => 'captive-portal-network-enter',
        'uses' => 'CaptiveIntegrationAPIController@getUserSignInNetwork'
    ]);
});