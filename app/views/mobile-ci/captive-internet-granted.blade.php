@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    <div class="row padded">
        <div class="col-xs-12">
            <h3>{{ Lang::get('mobileci.captive.granted.heading') }}</h3>
            <img style="width:100px;float:left;margin: 0 1em 1em 0; position:relative; top:-1em;" src="{{ asset('mobile-ci/images/signal-wifi-128x128.png') }}">
            <p>{{ Lang::get('mobileci.captive.granted.message') }}
            <form method="get" action="{{ $continue_url }}">
                <input type="submit" class="btn btn-block btn-primary" value="{{ Lang::get('mobileci.captive.granted.button') }}">
                @foreach ($params as $name=>$value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            </form>
        </div>
    </div>
@stop