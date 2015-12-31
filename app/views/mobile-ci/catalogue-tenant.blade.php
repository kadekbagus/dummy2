@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
@stop

@section('content')
    @if($data->status === 1)
        @if(sizeof($data->records) > 0)
            <div id="search-tool">
                <div class="row">
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <label class="select-label">
                                <select class="select" id="category">
                                    @if(empty(Input::get('cid')))
                                        <option>{{ Lang::get('mobileci.tenant.category') }}</option>
                                    @else
                                        <option>{{ Lang::get('mobileci.tenant.all') }}</option>
                                    @endif
                                    @foreach($categories as $category)
                                    @if($category->category_id == Input::get('cid'))
                                    <option value="{{ $category->category_id }}" selected="selected">{{ $category->category_name }}</option>
                                    @else
                                    <option value="{{ $category->category_id }}">{{ $category->category_name }}</option>
                                    @endif
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <label class="select-label">
                                <select class="select" id="floor">
                                    @if(empty(Input::get('fid')))
                                        <option>{{ Lang::get('mobileci.tenant.floor') }}</option>
                                    @else
                                        <option>{{ Lang::get('mobileci.tenant.all') }}</option>
                                    @endif
                                    @foreach($floorList as $floor)
                                    @if($floor->object_name == Input::get('fid'))
                                    <option value="{{ $floor->object_name }}" selected="selected">{{ $floor->object_name }}</option>
                                    @else
                                    <option value="{{ $floor->object_name }}">{{ $floor->object_name }}</option>
                                    @endif
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                    <div class="col-xs-2 search-tool-col text-right">
                        <a href="{{ url('/customer/tenants') }}" class="btn btn-info btn-block reset-btn">
                            <span class="fa-stack fa-lg">
                                <i class="fa fa-filter fa-stack-2x"></i>
                                <i class="fa fa-times fa-stack-1x"></i>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="mobile-ci list-item-container">
                    <div class="row">
                    @foreach($data->records as $product)
                        <div class="col-xs-12 col-sm-12" id="item-{{$product->merchant_id}}">
                            <section class="list-item-single">
                                <a class="list-item-link" href="{{ url('customer/tenant?id='.$product->merchant_id) }}">
                                    <div class="list-item-info">
                                        <header class="list-item-title">
                                            <div><strong>{{ $product->name }}</strong></div>
                                        </header>
                                        <header class="list-item-subtitle">
                                            <div>
                                                <i class="fa fa-map-marker" style="padding-left: 5px;padding-right: 8px;"></i> 
                                                {{{ !empty($product->floor) ? ' ' . $product->floor : '' }}}{{{ !empty($product->unit) ? ' - ' . $product->unit : '' }}}
                                            </div>
                                            <div>
                                                <div class="col-xs-6">
                                                    <i class="fa fa-list" style="padding-left: 2px;padding-right: 4px;"></i>
                                                    @if(empty($product->category_string))
                                                        <span>-</span>
                                                    @else
                                                        <span>{{ mb_strlen($product->category_string) > 30 ? mb_substr($product->category_string, 0, 30, 'UTF-8') . '...' : $product->category_string }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </header>
                                        <header class="list-item-badges">
                                            <div class="col-xs-12 badges-wrapper text-right">
                                                @if($product->promotion_flag)
                                                <span class="badges promo-badges text-center"><i class="fa fa-gift"></i></span>
                                                @endif
                                                @if($product->news_flag)
                                                <span class="badges news-badges text-center"><i class="fa fa-newspaper-o"></i></span>
                                                @endif
                                                @if($product->coupon_flag)
                                                <span class="badges coupon-badges text-center"><i class="fa fa-ticket"></i></span>
                                                @endif
                                            </div>
                                        </header>
                                    </div>
                                    <div class="list-vignette-non-tenant"></div>
                                    @if(!count($product->mediaLogo) > 0)
                                    <img class="img-responsive img-fit-not-tenant" src="{{ asset('mobile-ci/images/default_product.png') }}"/>
                                    @endif
                                    @foreach($product->mediaLogo as $media)
                                    @if($media->media_name_long == 'retailer_logo_orig')
                                    <img class="img-responsive img-fit-not-tenant" alt="" src="{{ asset($media->path) }}"/>
                                    @endif
                                    @endforeach
                                </a>
                            </section>
                        </div>
                    @endforeach
                    </div>
                </div>
            </div>
        @else
            <div id="search-tool">
                <div class="row">
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <label class="select-label">
                                <select class="select" id="category">
                                    @if(empty(Input::get('cid')))
                                        <option>{{ Lang::get('mobileci.tenant.category') }}</option>
                                    @else
                                        <option>{{ Lang::get('mobileci.tenant.all') }}</option>
                                    @endif
                                    @foreach($categories as $category)
                                    @if($category->category_id == Input::get('cid'))
                                    <option value="{{ $category->category_id }}" selected="selected">{{ $category->category_name }}</option>
                                    @else
                                    <option value="{{ $category->category_id }}">{{ $category->category_name }}</option>
                                    @endif
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <label class="select-label">
                                <select class="select" id="floor">
                                    @if(empty(Input::get('fid')))
                                        <option>{{ Lang::get('mobileci.tenant.floor') }}</option>
                                    @else
                                        <option>{{ Lang::get('mobileci.tenant.all') }}</option>
                                    @endif
                                    @foreach($floorList as $floor)
                                    @if($floor->object_name == Input::get('fid'))
                                    <option value="{{ $floor->object_name }}" selected="selected">{{ $floor->object_name }}</option>
                                    @else
                                    <option value="{{ $floor->object_name }}">{{ $floor->object_name }}</option>
                                    @endif
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                    <div class="col-xs-2 search-tool-col text-right">
                        <a href="{{ url('/customer/tenants') }}" class="btn btn-info btn-block reset-btn">
                            <span class="fa-stack fa-lg">
                                <i class="fa fa-filter fa-stack-2x"></i>
                                <i class="fa fa-times fa-stack-1x"></i>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="row padded">
                <div class="col-xs-12">
                    <h4>{{ Lang::get('mobileci.search.no_item') }}</h4>
                </div>
            </div>
        @endif
    @else
        <div class="row padded">
            <div class="col-xs-12">
                <h4>{{ Lang::get('mobileci.search.too_much_items') }}</h4>
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
                <h4 class="modal-title" id="verifyModalLabel"><i class="fa fa-envelope-o"></i> {{ Lang::get('mobileci.promotion.info') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <p style="font-size:15px;">
                            <b>{{ Lang::get('mobileci.modals.enjoy_free') }}</b>
                            <br>
                            @if ($active_user)
                                <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">{{ Lang::get('mobileci.modals.unlimited') }}</span>
                            @else
                                <span style="color:#0aa5d5; font-size:22px; font-weight: bold;">30 {{ Lang::get('mobileci.modals.minutes') }}</span>
                            @endif
                            <br>
                            <b>{{ Lang::get('mobileci.modals.internet') }}</b>
                            <br><br>
                            <b>{{ Lang::get('mobileci.modals.check_out_our') }}</b>
                            <br>
                            <b><span style="color:#0aa5d5;">{{ Lang::get('mobileci.page_title.promotion') }}</span> {{ Lang::get('mobileci.page_title.and') }} <span style="color:#0aa5d5;">{{ Lang::get('mobileci.page_title.coupon_single') }}</span></b>
                        </p>
                    </div>
                </div>
                <div class="row" style="margin-left: -30px; margin-right: -30px; margin-bottom: -15px;">
                    <div class="col-xs-12">
                        <img class="img-responsive" src="{{ asset('mobile-ci/images/pop-up-banner.png') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.okay') }}</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-12 text-left">
                            <input type="checkbox" name="verifyModalCheck" id="verifyModalCheck" style="top:2px;position:relative;">
                            <label for="verifyModalCheck">{{ Lang::get('mobileci.modals.do_not_display') }}</label>
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
    var cookie_dismiss_name = 'dismiss_verification_popup';
    var cookie_dismiss_name_2 = 'dismiss_activation_popup';

    @if ($active_user)
    cookie_dismiss_name = 'dismiss_verification_popup_unlimited';
    @endif

    /**
     * Get Query String from the URL
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string n - Name of the parameter
     */
    function get(n)
    {
        var half = location.search.split(n + '=')[1];
        return half !== undefined ? decodeURIComponent(half.split('&')[0]) : null;
    }

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
        $('#verifyModal').on('hidden.bs.modal', function () {
            if ($('#verifyModalCheck')[0].checked) {
                $.cookie(cookie_dismiss_name, 't', {expires: 3650});
            }
        });

        // $('#userActivationModal').on('hidden.bs.modal', function () {
        //     $.cookie(cookie_dismiss_name_2, 't', {path: '/', domain: window.location.hostname, expires: 3650});
        // });

        {{-- a sequence of modals... --}}
        var modals = [
            {
                selector: '#verifyModal',
                display: get('internet_info') == 'yes' && !$.cookie(cookie_dismiss_name)
            }
            // ,
            // {
            //     selector: '#userActivationModal',
            //     @if ($active_user)
            //         display: false
            //     @else
            //         display: get('from_login') === 'yes' && !$.cookie(cookie_dismiss_name_2)
            //     @endif
            // }
        ];
        var modalIndex;

        for (modalIndex = 0; modalIndex < modals.length; modalIndex++) {
            {{-- for each displayable modal, after it is hidden try and display the next displayable modal --}}
            if (modals[modalIndex].display) {
                $(modals[modalIndex].selector).on('hidden.bs.modal', (function(myIndex) {
                    return function() {
                        for (var i = myIndex + 1; i < modals.length; i++) {
                            if (modals[i].display) {
                                $(modals[i].selector).modal();
                                return;
                            }
                        }
                    }
                })(modalIndex));
            }
        }

        {{-- display the first displayable modal --}}
        for (modalIndex = 0; modalIndex < modals.length; modalIndex++) {
            if (modals[modalIndex].display) {
                $(modals[modalIndex].selector).modal();
                break;
            }
        }

        $(document).on('show.bs.modal', '.modal', function (event) {
            var zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(function() {
                $('.modal-backdrop').not('.modal-stack').css('z-index', 0).addClass('modal-stack');
            }, 0);
        });

        var promo = '';
        @if(!empty(Input::get('promotion_id')))
            promo = '&promotion_id='+'{{Input::get('promotion_id')}}';
        @endif
        var path = '{{ url('/customer/tenants?keyword='.Input::get('keyword').'&sort_by=name&sort_mode=asc&cid='.Input::get('cid').'&fid='.Input::get('fid')) }}'+promo;
        $('#dLabel').dropdown();
        $('#dLabel2').dropdown();

        $('#category').change(function(){
            var val = '';
            if($('#category > option:selected').attr('value')) {
                val = $('#category > option:selected').attr('value');
            }
            path = updateQueryStringParameter(path, 'cid', val);
            console.log(path);
            window.location.replace(path);
        });
        $('#floor').change(function(){
            var val = '';
            if($('#floor > option:selected').attr('value')) {
                val = $('#floor > option:selected').attr('value');
            }
            path = updateQueryStringParameter(path, 'fid', val);
            console.log(path);
            window.location.replace(path);
        });

        $('.catalogue-img img').each(function(){
            var h = $(this).height();
            var ph = $('.catalogue').height();
            $(this).css('margin-top', ((ph-h)/2) + 'px');
        });
    }); 
    
    $(window).resize(function(){
        $('.catalogue-img img').each(function(){
            var h = $(this).height();
            var ph = $('.catalogue').height();
            $(this).css('margin-top', ((ph-h)/2) + 'px');
        });
    });
</script>
@stop
