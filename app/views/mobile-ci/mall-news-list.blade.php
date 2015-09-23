@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    @if($data->status === 1)
        @if(sizeof($data->records) > 0)
            @foreach($data->records as $product)
                <div class="main-theme-mall catalogue" id="product-{{$product->promotion_id}}">
                    <div class="row catalogue-top">
                        <div class="col-xs-3 catalogue-img">
                            @if(!empty($product->image))
                            <a href="{{ asset($product->image) }}" data-featherlight="image" class="text-left"><img class="img-responsive" alt="" src="{{ asset($product->image) }}"></a>
                            @else
                            <a class="img-responsive" src="{{ asset('mobile-ci/images/default_product.png') }}"/>
                            @endif
                        </div>
                        <div class="col-xs-6">
                            <h4>{{ $product->news_name }}</h4>
                            @if (strlen($product->description) > 120)
                            <p>{{{ substr($product->description, 0, 120) }}} [<a href="{{ url('customer/mallnewsdetail?id='.$product->news_id) }}">...</a>] </p>
                            @else
                            <p>{{{ $product->description }}}</p>
                            @endif
                        </div>
                        <div class="col-xs-3" style="margin-top:20px">
                            <div class="circlet btn-blue detail-btn pull-right">
                                <a href="{{ url('customer/mallnewsdetail?id='.$product->news_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="row padded">
                <div class="col-xs-12">
                    <h4>Check for our news coming soon.</h4>
                </div>
            </div>
        @endif
    @else
        <div class="row padded">
            <div class="col-xs-12">
                <h4>Check for our news coming soon.</h4>
            </div>
        </div>
    @endif
@stop

@section('modals')
<!-- Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1" role="dialog" aria-labelledby="verifyModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="verifyModalLabel"><i class="fa fa-envelope-o"></i> Info</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <p>
                            <b>ENJOY FREE</b>
                            <br>
                            <span style="color:#0aa5d5; font-size:20px; font-weight: bold;">UNLIMITED</span>
                            <br>
                            <b>INTERNET</b>
                            <br><br>
                            <b>CHECK OUT OUR</b>
                            <br>
                            <b><span style="color:#0aa5d5;">PROMOTIONS</span> AND <span style="color:#0aa5d5;">COUPONS</span></b>
                        </p>
                    </div>
                </div>
                <div class="row" style="margin-left: -30px; margin-right: -30px; margin-bottom: -15px;">
                    <div class="col-xs-12">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/pop-up-banner.png') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('ext_script_bot')
{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
{{ HTML::script('mobile-ci/scripts/featherlight.min.js') }}
{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
<script type="text/javascript">
    function updateQueryStringParameter(uri, key, value) {
        var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
        var separator = uri.indexOf('?') !== -1 ? "&" : "?";
        if (uri.match(re)) {
            return uri.replace(re, '$1' + key + "=" + value + '$2');
        } else {
            return uri + separator + key + "=" + value;
        }
    }
    $(document).ready(function(){
        $(document).on('show.bs.modal', '.modal', function (event) {
            var zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(function() {
                $('.modal-backdrop').not('.modal-stack').css('z-index', 0).addClass('modal-stack');
            }, 0);
        });
        if(!$.cookie('dismiss_verification_popup')) {
            $.cookie('dismiss_verification_popup', 't', { expires: 1 });
            $('#verifyModal').modal();
        }
        
        var path = '{{ url('/customer/tenants?keyword='.Input::get('keyword').'&sort_by=name&sort_mode=asc&cid='.Input::get('cid').'&fid='.Input::get('fid')) }}';
        $('#dLabel').dropdown();
        $('#dLabel2').dropdown();
        $('#category>li').click(function(){
            if(!$(this).data('category')) {
                $(this).data('category', '');
            }
            path = updateQueryStringParameter(path, 'cid', $(this).data('category'));
            console.log(path);
            window.location.replace(path);
        });
        $('#floor>li').click(function(){
            if(!$(this).data('floor')) {
                $(this).data('floor', '');
            }
            path = updateQueryStringParameter(path, 'fid', $(this).data('floor'));
            console.log(path);
            window.location.replace(path);
        });
    });
</script>
@stop
