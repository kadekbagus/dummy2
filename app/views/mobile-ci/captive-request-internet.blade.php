@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    <script>
    var OrbitInternetChecker = {};

    /**
     * Callback to handle if internet is up
     */
    OrbitInternetChecker.up = function()
    {
        $('#captive-check-internet').addClass('hide');

        // The user is already connected to the internet, no need to grant.
        // Redirect to our granted page.
        window.location.href = '{{ $granted_url }}';
    };

    /**
     * Callback to handle if internet is down (image not found)
     */
    OrbitInternetChecker.down = function()
    {
        setTimeout(function() {
            $('#captive-check-internet').addClass('hide');
            $('#captive-no-internet').removeClass('hide');
        }, {{ $timeout }} * 1000);
    };

    /**
     * Callback to handle form submission when Free internet button
     * get clicked.
     */
    OrbitInternetChecker.submit = function(el) {
        el.value = 'Please wait...';
        el.disabled = 'disabled';

        setTimeout(function() {
            document.getElementById('frm-grant-internet').submit();
        }, 1000);

        return false;
    }
    </script>

    <div class="row padded">
        <div class="col-xs-12 hide" id="captive-no-internet">
            <h3>{{ Lang::get('mobileci.captive.request_internet.heading') }}</h3>
            <img style="width:100px;float:left;margin: 0 1em 1em 0; position:relative; top:-1em;" src="{{ asset('mobile-ci/images/signal-wifi-128x128.png') }}">
            <p>{{ Lang::get('mobileci.captive.request_internet.message') }}
            <form id="frm-grant-internet" method="get" action="{{ $base_grant_url }}">
                <input onclick="return OrbitInternetChecker.submit(this)" id="btn-grant-internet" type="submit" class="btn btn-block btn-primary" value="{{ Lang::get('mobileci.captive.request_internet.button') }}">
                @foreach ($params as $name=>$value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            </form>
        </div>

        <div class="col-xs-12" id="captive-check-internet">
            <h4>{{ Lang::get('mobileci.captive.request_internet.check_connection') }}</h4>
            <p>{{ Lang::get('mobileci.captive.request_internet.too_long') }}</p>
        </div>

        <img id="pingdom-icon" class="hide" src="{{ $ping_url }}" onerror="OrbitInternetChecker.down()" onload="OrbitInternetChecker.up()">
    </div>
@stop