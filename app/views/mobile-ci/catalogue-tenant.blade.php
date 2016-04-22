@extends('mobile-ci.layout')

@section('fb_scripts')
@if(! empty($facebookInfo))
@if(! empty($facebookInfo['version']) && ! empty($facebookInfo['app_id']))
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version={{$facebookInfo['version']}}&appId={{$facebookInfo['app_id']}}";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
@endif
@endif
@stop

@section('content')
    @if($data->status === 1)
        @if(sizeof($data->records) > 0 || $link_to_coupon_data->linkedToCS)
            @if(Input::get('coupon_redeem_id') === null && Input::get('coupon_id') === null && Input::get('promotion_id') == null && Input::get('news_id') === null)
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
                                    <option value="{{ $category->category_id }}" selected="selected">{{{ $category->category_name }}}</option>
                                    @else
                                    <option value="{{ $category->category_id }}">{{{ $category->category_name }}}</option>
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
                                    <option value="{{{ $floor->object_name }}}" selected="selected">{{{ $floor->object_name }}}</option>
                                    @else
                                    <option value="{{{ $floor->object_name }}}">{{{ $floor->object_name }}}</option>
                                    @endif
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                    <div class="col-xs-2 search-tool-col text-right">
                        <a href="{{{ $urlblock->blockedRoute('ci-tenants', ['keyword' => Input::get('keyword')]) }}}" class="btn btn-info btn-block reset-btn">
                            <span class="fa-stack fa-lg">
                                <i class="fa fa-filter fa-stack-2x"></i>
                                <i class="fa fa-times fa-stack-1x"></i>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
            <div class="container">
                <div class="mobile-ci list-item-container">
                    <div class="row">
                        <div class="catalogue-wrapper">
                        @if($link_to_coupon_data->linkedToCS)
                            <div class="col-xs-12 col-sm-12" id="item-cs">
                                <section class="list-item-single-tenant">
                                    <div class="list-item-info">
                                        <header class="list-item-title">
                                            <div><strong>{{ Lang::get('mobileci.coupon.all_cs') }}</strong></div>
                                        </header>
                                    </div>
                                    <div class="list-vignette-non-tenant"></div>
                                    <img class="img-responsive img-fit-tenant" alt="" src="{{ asset('mobile-ci/images/default_cs.png') }}"/>
                                </section>
                            </div>
                        @endif

                        {{-- Showing info for there is no stores and search mode is false --}}
                        @if(count($data->records) == 0 && $data->search_mode)
                            <div class="row padded">
                                <div class="col-xs-12">
                                    <h4>{{ Lang::get('mobileci.greetings.no_stores_listing') }}</h4>
                                </div>
                            </div>
                        @endif

                        @foreach($data->records as $tenant)
                            <div class="col-xs-12 col-sm-12" id="item-{{$tenant->merchant_id}}">
                                <section class="list-item-single-tenant">
                                    <a class="list-item-link" href="{{ $urlblock->blockedRoute('ci-tenant', ['id' => $tenant->merchant_id]) }}">
                                        <div class="list-item-info">
                                            <header class="list-item-title">
                                                <div><strong>{{{ $tenant->name }}}</strong></div>
                                            </header>
                                            <header class="list-item-subtitle">
                                                <div>
                                                    <i class="fa fa-map-marker" style="padding-left: 5px;padding-right: 8px;"></i>
                                                    {{{ !empty($tenant->floor) ? ' ' . $tenant->floor : '' }}}{{{ !empty($tenant->unit) ? ' - ' . $tenant->unit : '' }}}
                                                </div>
                                                <div>
                                                    <div class="col-xs-6">
                                                        <i class="fa fa-list" style="padding-left: 2px;padding-right: 4px;"></i>
                                                        @if(empty($tenant->category_string))
                                                            <span>-</span>
                                                        @else
                                                            <span>{{{ mb_strlen($tenant->category_string) > 30 ? mb_substr($tenant->category_string, 0, 30, 'UTF-8') . '...' : $tenant->category_string }}}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if ($urlblock->isLoggedIn())
                                                    @if(! empty($tenant->facebook_like_url))
                                                    <div class="fb-like" data-href="{{{$tenant->facebook_like_url}}}" data-layout="button_count" data-action="like" data-show-faces="false" data-share="false"></div>
                                                    @endif
                                                @endif
                                            </header>
                                            <header class="list-item-badges">
                                                <div class="col-xs-12 badges-wrapper text-right">
                                                    @if($tenant->promotion_flag)
                                                    <span class="badges promo-badges text-center"><i class="fa fa-bullhorn"></i></span>
                                                    @endif
                                                    @if($tenant->news_flag)
                                                    <span class="badges news-badges text-center"><i class="fa fa-newspaper-o"></i></span>
                                                    @endif
                                                    @if($tenant->coupon_flag)
                                                    <span class="badges coupon-badges text-center"><i class="fa fa-ticket"></i></span>
                                                    @endif
                                                </div>
                                            </header>
                                        </div>
                                        <div class="list-vignette-non-tenant"></div>
                                        @if(!count($tenant->mediaLogo) > 0)
                                        <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_tenants_directory.png') }}"/>
                                        @endif
                                        @foreach($tenant->mediaLogo as $media)
                                        @if($media->media_name_long == 'retailer_logo_orig')
                                        <img class="img-responsive img-fit-tenant" alt="" src="{{ asset($media->path) }}"/>
                                        @endif
                                        @endforeach
                                    </a>
                                </section>
                            </div>
                        @endforeach
                        </div>
                    </div>
                    @if($data->returned_records < $data->total_records)
                        <div class="row">
                            <div class="col-xs-12 padded">
                                <button class="btn btn-info btn-block" id="load-more-tenants">{{Lang::get('mobileci.notification.view_more_btn')}}</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            @if(Input::get('coupon_redeem_id') === null && Input::get('coupon_id') === null && Input::get('promotion_id') == null && Input::get('news_id') === null)
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
                                    <option value="{{ $category->category_id }}" selected="selected">{{{ $category->category_name }}}</option>
                                    @else
                                    <option value="{{ $category->category_id }}">{{{ $category->category_name }}}</option>
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
                                    <option value="{{{ $floor->object_name }}}" selected="selected">{{{ $floor->object_name }}}</option>
                                    @else
                                    <option value="{{{ $floor->object_name }}}">{{{ $floor->object_name }}}</option>
                                    @endif
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                    <div class="col-xs-2 search-tool-col text-right">
                        <a href="{{{ $urlblock->blockedRoute('ci-tenants', ['keyword' => Input::get('keyword')]) }}}" class="btn btn-info btn-block reset-btn">
                            <span class="fa-stack fa-lg">
                                <i class="fa fa-filter fa-stack-2x"></i>
                                <i class="fa fa-times fa-stack-1x"></i>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
            @endif
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

@section('ext_script_bot')
<script type="text/javascript">
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
        $(document).on('show.bs.modal', '.modal', function (event) {
            var zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(function() {
                $('.modal-backdrop').not('.modal-stack').css('z-index', 0).addClass('modal-stack');
            }, 0);
        });

        var promo = '';
        @if(!empty(Input::get('promotion_id')))
            promo = '&promotion_id='+'{{{Input::get('promotion_id')}}}';
        @endif
        var path = '{{$urlblock->blockedRoute('ci-tenants', ['keyword' => e(Input::get('keyword')), 'sort_by' => 'name', 'sort_mode' => 'asc', 'cid' => e(Input::get('cid')), 'fid' => e(Input::get('fid'))])}}'+promo;
        $('#dLabel').dropdown();
        $('#dLabel2').dropdown();

        $('#category').change(function(){
            var val = '';
            if($('#category > option:selected').attr('value')) {
                val = $('#category > option:selected').attr('value');
            }
            path = updateQueryStringParameter(path, 'cid', val);
            window.location.replace(path);
        });
        $('#floor').change(function(){
            var val = '';
            if($('#floor > option:selected').attr('value')) {
                val = $('#floor > option:selected').attr('value');
            }
            path = updateQueryStringParameter(path, 'fid', val);
            window.location.replace(path);
        });

        var take = {{Config::get('orbit.pagination.per_page', 25)}},
            skip = {{Config::get('orbit.pagination.per_page', 25)}};

        var keyword = '{{{Input::get('keyword', '')}}}';
        var cid = '{{{Input::get('cid', '')}}}';
        var fid = '{{{Input::get('fid', '')}}}';
        var promotion_id = '{{{Input::get('promotion_id', '')}}}';

        $('#load-more-tenants').click(function(){
            var btn = $(this);
            btn.attr('disabled', 'disabled');
            btn.html('<i class="fa fa-circle-o-notch fa-spin"></i>');
            $.ajax({
                url: '{{ url("app/v1/tenant/load-more") }}',
                method: 'GET',
                data: {
                    take: take,
                    skip: skip,
                    keyword: keyword,
                    cid: cid,
                    fid: fid,
                    promotion_id: promotion_id
                }
            }).done(function(data) {
                skip = skip + take;
                if(data.records.length > 0) {
                    for(var i = 0; i < data.records.length; i++) {
                        var list = '<div class="col-xs-12 col-sm-12" id="item-'+data.records[i].merchant_id+'">\
                                <section class="list-item-single-tenant">\
                                    <a class="list-item-link" href="'+data.records[i].url+'">\
                                        <div class="list-item-info">\
                                            <header class="list-item-title">\
                                                <div><strong>'+data.records[i].name+'</strong></div>\
                                            </header>\
                                            <header class="list-item-subtitle">\
                                                <div>\
                                                    <i class="fa fa-map-marker" style="padding-left: 5px;padding-right: 8px;"></i> \
                                                    '+ (data.records[i].floor ?  ' ' + data.records[i].floor : '') + (data.records[i].unit ? ' - ' + data.records[i].unit : '') +'\
                                                </div>\
                                                <div>\
                                                    <div class="col-xs-6">\
                                                        <i class="fa fa-list" style="padding-left: 2px;padding-right: 4px;"></i>\
                                                        <span>'+ (data.records[i].category_string ? data.records[i].category_string : '-') +'</span>\
                                                    </div>\
                                                </div>';
                            if (data.records[i].facebook_like_url) {
                                list += '<div class="fb-like" data-href="' + data.records[i].facebook_like_url + '" data-layout="button_count" data-action="like" data-show-faces="false" data-share="false"></div>';
                            }

                            list += '</header>\
                                            <header class="list-item-badges">\
                                                <div class="col-xs-12 badges-wrapper text-right">\
                                                    '+ (data.records[i].promotion_flag ? '<span class="badges promo-badges text-center"><i class="fa fa-bullhorn"></i></span>' : '') +'\
                                                    '+ (data.records[i].news_flag ? '<span class="badges news-badges text-center"><i class="fa fa-newspaper-o"></i></span>' : '') +'\
                                                    '+ (data.records[i].coupon_flag ? '<span class="badges coupon-badges text-center"><i class="fa fa-ticket"></i></span>' : '') +'\
                                                </div>\
                                            </header>\
                                        </div>\
                                        <div class="list-vignette-non-tenant"></div>\
                                        <img class="img-responsive img-fit-tenant" src="'+ data.records[i].logo_orig +'"/>\
                                    </a>\
                                </section>\
                            </div>';
                        $('.catalogue-wrapper').append(list);
                    };
                    FB.XFBML.parse();
                }
                if (skip >= data.total_records) {
                    btn.remove();
                }
            }).always(function(data){
                btn.removeAttr('disabled', 'disabled');
                btn.html('{{Lang::get('mobileci.notification.view_more_btn')}}');
            });
        });
    });
</script>
@stop
