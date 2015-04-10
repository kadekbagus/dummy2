@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    <div class="row padded text-center">
        <div class="col-xs-12">
            <h4>{{ Lang::get('mobileci.404.not_found') }}</h4>
            <a class="btn btn-info" href="{{ url('/customer/home') }}">{{ Lang::get('mobileci.promotion_detail.back_label') }}</a>
        </div>
    </div>
@stop

@section('ext_script_bot')
    {{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
@stop
