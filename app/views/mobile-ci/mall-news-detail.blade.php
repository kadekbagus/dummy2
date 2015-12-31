@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    {{ HTML::style('mobile-ci/stylesheet/lightslider.min.css') }}
    <style type="text/css">
        .product-detail{

        }
        .product-detail img{
            position: absolute;
            top:0em;
            width: 100%;
            height: 270px;
            width:100%;
            object-fit:cover;
            z-index: -1;
        }
        .product-info{
            position: static;
            margin-top: 269px;
            background: #0AA5D5;
            color:#fff;
            font-size: 1.3em;
            z-index: 2;
            -webkit-box-shadow: 0px -25px 91px -6px rgba(0,0,0,0.4);
            -moz-box-shadow: 0px -25px 91px -6px rgba(0,0,0,0.4);
            box-shadow: 0px -25px 91px -6px rgba(0,0,0,0.4);
        }
        .product-info {

        }
    </style>
@stop

@section('content')
<!-- product -->
<div class="row">
    <div class="col-xs-12 main-theme product-detail">
        <img src="{{ asset($product->image) }}">
    </div>
</div>
<div class="row product-info padded">
    <div class="col-xs-12">
        <p>{{{ $product->description }}}</p>
    </div>
    <div class="col-xs-12">
        <h4><strong>{{{ Lang::get('mobileci.promotion.validity') }}}</strong></h4>
        <p>{{{ date('d M Y', strtotime($product->begin_date)) }}} - {{{ date('d M Y', strtotime($product->end_date)) }}}</p>
    </div>
</div>
<div class="row">
    <div class="col-xs-12 main-theme-mall product-detail where">
        <div class="row">
            @if(!$all_tenant_inactive)
                @if(count($product->tenants) > 1 )
                <div class="col-xs-12 text-center">
                    <a href="{{{ url('customer/tenants?news_id='.$product->news_id) }}}" class="btn btn-info btn-block">{{{ Lang::get('mobileci.tenant.see_tenants') }}}</a>
                </div>
                @elseif(count($product->tenants) == 1 )
                <div class="col-xs-12 text-center">
                    <a href="{{{ url('customer/tenant?id='.$product->tenants[0]->merchant_id.'&nid='.$product->news_id) }}}" class="btn btn-info btn-block">{{{ Lang::get('mobileci.tenant.see_tenants') }}}</a>
                </div>
                @endif
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
            $(window).scroll(function(){
                s = $(window).scrollTop();
                $('.product-detail img').css('-webkit-transform', 'translateY('+(s/3)+'px)');
            });
        });
    </script>
@stop
