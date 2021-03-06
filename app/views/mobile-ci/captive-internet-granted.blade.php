@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    <style>
        .header-buttons-container > .col-xs-2 {
            display: none;
        }
    </style>
@stop

@section('content')
    <div class="row padded">
        <div class="col-xs-12">
            <h3>{{ Lang::get('mobileci.captive.granted.heading') }}</h3>
            <img style="width:100px;float:left;margin: 0 1em 1em 0; position:relative; top:-1em;" src="{{ asset('mobile-ci/images/free-internet-connected.png') }}">
            <p>{{ Lang::get('mobileci.captive.granted.message') }}</p>
            <form method="get" action="{{ $continue_url }}">
                <input type="submit" class="btn btn-block btn-primary" value="{{ Lang::get('mobileci.captive.granted.button') }}">
                @foreach ($params as $name=>$value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            </form>
        </div>
    </div>
@stop