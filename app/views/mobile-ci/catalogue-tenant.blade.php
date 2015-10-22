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
                            <button id="dLabel" type="button" class="btn btn-info btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="buttonLabel">
                                    @if(!empty(Input::get('cid')))
                                        <?php
                                            $namex = $categories->filter(function ($item) {
                                                return $item->category_id == Input::get('cid');
                                            })->first()->category_name;
                                            echo $namex;
                                        ?>
                                    @else
                                        {{ Lang::get('mobileci.tenant.category') }}
                                    @endif
                                </span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel" id="category">
                                <li data-category=""><span>{{ Lang::get('mobileci.tenant.all') }}</span></li>
                                @foreach($categories as $category)
                                <li data-category="{{ $category->category_id }}"><span>{{ $category->category_name }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <button id="dLabel2" type="button" class="btn btn-info btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="buttonLabel">
                                    @if(!empty(Input::get('fid')))
                                        {{ Input::get('fid') }}
                                    @else
                                        {{ Lang::get('mobileci.tenant.floor') }}
                                    @endif
                                </span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel2" id="floor">
                                @foreach($floorList as $floor)
                                <li data-floor="{{ $floor }}"><span>{{ $floor }}</span></li>
                                @endforeach
                            </ul>
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
            @foreach($data->records as $product)
                <div class="main-theme-mall catalogue" id="product-{{$product->product_id}}">
                    <div class="row catalogue-top">
                        <div class="col-xs-6 catalogue-img">
                            @foreach($product->mediaLogo as $media)
                            @if($media->media_name_long == 'retailer_logo_orig')
                            <a href="{{ asset($media->path) }}" data-featherlight="image" class="text-left"><img class="img-responsive" alt="" src="{{ asset($media->path) }}"></a>
                            @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-9">
                            <h4>{{ $product->name }} at</h4>
                            <h3>{{ $retailer->name }}{{{ !empty($product->floor) ? ' - ' . $product->floor : '' }}}{{{ !empty($product->unit) ? ' - ' . $product->unit : '' }}}</h3>
                            <h5 class="tenant-category">
                            @foreach($product->categories as $cat)
                                <span>{{$cat->category_name}}</span>
                            @endforeach
                            </h5>
                        </div>
                        <div class="col-xs-3">
                            <div class="circlet btn-blue detail-btn pull-right">
                                <a href="{{ url('customer/tenant?id='.$product->merchant_id) }}"><span class="link-spanner"></span><i class="fa fa-ellipsis-h"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div id="search-tool">
                <div class="row">
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <button id="dLabel" type="button" class="btn btn-info btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="buttonLabel">
                                    @if(!empty(Input::get('cid')))
                                        <?php
                                            $namex = $categories->filter(function ($item) {
                                                return $item->category_id == Input::get('cid');
                                            })->first()->category_name;
                                            echo $namex;
                                        ?>
                                    @else
                                        Category
                                    @endif
                                </span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel" id="category">
                                <li data-category=""><span>{{ Lang::get('mobileci.catalogue.all') }}</span></li>
                                @foreach($categories as $category)
                                <li data-category="{{ $category->category_id }}"><span>{{ $category->category_name }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="col-xs-5 search-tool-col">
                        <div class="dropdown">
                            <button id="dLabel2" type="button" class="btn btn-info btn-block" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="buttonLabel">
                                    @if(!empty(Input::get('fid')))
                                        {{ Input::get('fid') }}
                                    @else
                                        Floor
                                    @endif
                                </span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel2" id="floor">
                                <li data-category=""><span>{{ Lang::get('mobileci.catalogue.all') }}</span></li>
                                <li data-floor="LG"><span>LG</span></li>
                                <li data-floor="G"><span>G</span></li>
                                <li data-floor="UG"><span>UG</span></li>
                                <li data-floor="L1"><span>L1</span></li>
                                <li data-floor="L2"><span>L2</span></li>
                                <!-- Lippo Mall Puri only has floor up to L2
                                <li data-floor="L3"><span>L3</span></li>
                                <li data-floor="L4"><span>L4</span></li>
                                <li data-floor="L5"><span>L5</span></li>
                                <li data-floor="L6"><span>L6</span></li>
                                -->
                            </ul>
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
<div class="modal fade" id="userActivationModal" tabindex="-1" role="dialog" aria-labelledby="userActivationModalLabel" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="userActivationModalLabel"><i class="fa fa-envelope-o"></i> {{ Lang::get('mobileci.promotion.info') }}</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <p style="font-size:15px;">
                            {{ Lang::get('mobileci.modals.message_user_activation') }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="row">
                    <div class="col-xs-12 text-center">
                        <button type="button" class="btn btn-info btn-block" data-dismiss="modal">{{ Lang::get('mobileci.modals.okay') }}</button>
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

        {{-- a sequence of modals... --}}
        var modals = [
            {
                selector: '#verifyModal',
                display: get('internet_info') == 'yes' && !$.cookie(cookie_dismiss_name)
            },
            {
                selector: '#userActivationModal',
                @if ($active_user)
                    display: false
                @else
                    display: get('from_login') === 'yes'
                @endif
            }
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
