@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    {{ HTML::style('mobile-ci/stylesheet/lightslider.min.css') }}
    <style type="text/css">
        .modal-spinner{
            display: none;
            font-size: 2.5em;
            color: #fff;
            position: absolute;
            top: 50%;
            margin: 0 auto;
            width: 100%;
        }
        .where .row a{
            margin:20px auto;
        }
    </style>
@stop

@section('content')
<!-- product -->
<div class="row product">
    <div class="col-xs-12 product-img">
        <div class="zoom-wrapper">
            <div class="zoom"><a href="{{ asset($product->image) }}" data-featherlight="image"><img alt="" src="{{ asset('mobile-ci/images/product-zoom.png') }}" ></a></div>
        </div>
        <a href="{{ asset($product->image) }}" data-featherlight="image"><img class="img-responsive" alt="" src="{{ asset($product->image) }}" ></a>
    </div>
    <div class="col-xs-12 main-theme product-detail">
        <div class="row">
            <div class="col-xs-12">
                <h3>{{ $product->promotion_name }}</h3>
            </div>
            <div class="col-xs-12">
                <p>{{ $product->description }}</p>
            </div>
            <div class="col-xs-12">
                <h4>Validity</h4>
                <p>{{ date('d M Y', strtotime($product->begin_date)) }} - {{ date('d M Y', strtotime($product->end_date)) }}</p>
            </div>
        </div>
    </div>
    <div class="col-xs-12 main-theme-mall product-detail where">
        <div class="row">
            @if(count($product->tenants) > 1 )
            <div class="col-xs-12 text-center">
                <a href="{{ url('customer/tenants?promotion_id='.$product->news_id) }}" class="btn btn-info btn-block">See Tenants</a>
            </div>
            @elseif(count($product->tenants) == 1 )
            <div class="col-xs-12 text-center">
                <a href="{{ url('customer/tenant?id='.$product->tenants[0]->merchant_id.'&pid='.$product->news_id) }}" class="btn btn-info btn-block">See Tenant</a>
            </div>
            @endif
        </div>
    </div>
</div>
<!-- end of product -->
@stop

@section('ext_script_bot')
    {{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
    {{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
    {{ HTML::script('mobile-ci/scripts/lightslider.min.js') }}
    {{ HTML::script('mobile-ci/scripts/autoNumeric.js') }}
    <script type="text/javascript">
        $(document).ready(function(){
            
        });
    </script>
@stop
