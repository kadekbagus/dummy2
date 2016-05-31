@extends('mobile-ci.layout')

@section('content')
    @if($data->status === 1)
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
                        <a data-href="{{{ route('ci-service-list', ['keyword' => Input::get('keyword')]) }}}" href="{{{ $urlblock->blockedRoute('ci-service-list', ['keyword' => Input::get('keyword')]) }}}" class="btn btn-info btn-block reset-btn">
                            <span class="fa-stack fa-lg">
                                <i class="fa fa-filter fa-stack-2x"></i>
                                <i class="fa fa-times fa-stack-1x"></i>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        @if(sizeof($data->records) > 0)
            <div id="catContainer" class="container">
                <div class="mobile-ci list-item-container">
                    <div class="row">
                        <div class="catalogue-wrapper">
                        @foreach($data->records as $service)
                            <div class="col-xs-12 col-sm-12" id="item-{{$service->merchant_id}}">
                                <section class="list-item-single-tenant">
                                    <a class="list-item-link" data-href="{{ route('ci-service-detail', ['id' => $service->merchant_id]) }}" href="{{ $urlblock->blockedRoute('ci-service-detail', ['id' => $service->merchant_id]) }}">
                                        <div class="list-item-info">
                                            <header class="list-item-title">
                                                <div><strong>{{{ $service->name }}}</strong></div>
                                            </header>
                                            <header class="list-item-subtitle">
                                                <div>
                                                    <i class="fa fa-map-marker" style="padding-left: 5px;padding-right: 8px;"></i>
                                                    {{{ !empty($service->floor) ? ' ' . $service->floor : '' }}}{{{ !empty($service->unit) ? ' - ' . $service->unit : '' }}}
                                                </div>
                                                <div>
                                                    <div class="col-xs-6">
                                                        <i class="fa fa-list" style="padding-left: 2px;padding-right: 4px;"></i>
                                                        @if(empty($service->category_string))
                                                            <span>-</span>
                                                        @else
                                                            <span>{{{ mb_strlen($service->category_string) > 30 ? mb_substr($service->category_string, 0, 30, 'UTF-8') . '...' : $service->category_string }}}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </header>
                                        </div>
                                        <div class="list-vignette-non-tenant"></div>
                                        @if(!count($service->mediaLogo) > 0)
                                        <img class="img-responsive img-fit-tenant" src="{{ asset('mobile-ci/images/default_services_directory.png') }}"/>
                                        @endif
                                        @foreach($service->mediaLogo as $media)
                                        @if($media->media_name_long == 'service_logo_orig')
                                        <img class="img-responsive img-fit-tenant" alt="" data-original="{{ asset($media->path) }}"/>
                                        @endif
                                        @endforeach
                                    </a>
                                </section>
                            </div>
                        @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @else
            @if($data->search_mode)
                <div class="row padded">
                    <div class="col-xs-12">
                        <h4>{{ Lang::get('mobileci.search.no_item') }}</h4>
                    </div>
                </div>
            @else
                {{-- Showing info for there is no stores when search mode is false --}}
                <div class="row padded">
                    <div class="col-xs-12">
                        <h4>{{ Lang::get('mobileci.greetings.no_stores_listing') }}</h4>
                    </div>
                </div>
            @endif
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
{{ HTML::script('mobile-ci/scripts/jquery.lazyload.min.js') }}
<script type="text/javascript">

    var take = {{ Config::get('orbit.pagination.per_page', 25) }},
        skip = {{ Config::get('orbit.pagination.per_page', 25) }},
        keyword = '{{{ Input::get('keyword', '') }}}',
        cid = '{{{ Input::get('cid', '') }}}',
        fid = '{{{ Input::get('fid', '') }}}',
        isFromDetail = false,
        defaultServiceLogoUrl = '{{ asset('mobile-ci/images/default_services_directory.png') }}',
        isLoggedIn = Boolean({{ $urlblock->isLoggedIn() }}),
        canLoadMoreService = Boolean({{ $data->returned_records < $data->total_records }});

    var initImageLazyload = function(jImageElems) {
        if (jImageElems instanceof jQuery) {
            jImageElems.lazyload({
                threshold : 100,
                effect: "fadeIn",
                placeholder: defaultServiceLogoUrl
            });
        }
    };

    /**
     * Get Query String from the URL
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string n - Name of the parameter
     */
    function get(n) {
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

    var generateListItem = function(merchantId, redirectUrl, url, name, floor, unit, category, facebook_like_url, logoUrl) {
        var $listDiv = $('<div />').addClass('col-xs-12 col-sm-12').attr({
            'id': 'item-' + merchantId
        });
        var $listSection = $('<section />').addClass('list-item-single-tenant');

        var $itemLink = $('<a />').addClass('list-item-link').attr({
            'data-href': redirectUrl,
            'href': isLoggedIn ? redirectUrl : '#'
        });

        var $itemListInfo = $('<div />').addClass('list-item-info');
        var $titleHeader = $('<header />').addClass('list-item-title').append(
            $('<div />').append(
                $('<strong />').text(name)
            )
        );

        var $subtitleHeader = $('<header />').addClass('list-item-subtitle');
        var markerText = (floor ? ' ' + floor : '') + (unit ? '- ' + unit : '');
        var $divMarker = $('<div />').append(
            $('<i />').addClass('fa fa-map-marker').attr('style', 'padding-left: 5px;padding-right: 8px;')
        ).append(markerText);

        var categoryText = category ? category : '-';
        var $divCategory = $('<div />').append(
            $('<div />').addClass('col-xs-6').append(
                $('<i />').addClass('fa fa-list').attr('style', 'padding-left: 2px;padding-right: 4px;')
            ).append(
                $('<span />').text(categoryText)
            )
        );

        $subtitleHeader.append($divMarker);
        $subtitleHeader.append($divCategory);
        $itemListInfo.append($titleHeader);
        $itemListInfo.append($subtitleHeader);
        $itemLink.append($itemListInfo);
        $listSection.append($itemLink);
        $listDiv.append($listSection);

        if (facebook_like_url) {
            var $fbLikeDiv = $('<div />').addClass('fb-like').attr({
                'data-href': facebook_like_url,
                'data-layout': 'button_count',
                'data-action': 'like',
                'data-show-faces': 'false',
                'data-share': 'false'
            });
            $subtitleHeader.append($fbLikeDiv);
        }

        var $badgeHeader = $('<header />').addClass('list-item-badges');
        var $badgeWrapper = $('<div />').addClass('col-xs-12 badges-wrapper text-right');
        var $theBadge;

        $badgeHeader.append($badgeWrapper);
        $itemListInfo.append($badgeHeader);

        var $nonTenantDiv = $('<div />').addClass('list-vignette-non-tenant');
        var $tenantLogo;

        if (/default_product.png/i.test(logoUrl)){
            $tenantLogo = $('<img />').addClass('img-responsive img-fit-tenant').attr('src', logoUrl);
        }
        else {
            $tenantLogo = $('<img />').addClass('img-responsive img-fit-tenant').attr('data-original', logoUrl);
            // Apply lazy load to tenantLogo image.
            initImageLazyload($tenantLogo);
        }

        $itemLink.append($nonTenantDiv);
        $itemLink.append($tenantLogo);

        return $listDiv;
    };

    var insertRecords = function(records) {
        var promises = [];
        for(var i = 0; i < records.length; i++) {
            var deferred = new $.Deferred();

            var merchantId = records[i].merchant_id;
            var redirectUrl = records[i].redirect_url;
            var url = records[i].url;
            var name = records[i].name;
            var floor = records[i].floor;
            var unit = records[i].unit;
            var category = records[i].category_string;
            var facebook_like_url = records[i].facebook_like_url;
            var logoUrl = records[i].logo_orig;

            var $listDiv = generateListItem(merchantId, redirectUrl, url, name, floor, unit, category, facebook_like_url, logoUrl);

            $('.catalogue-wrapper').append($listDiv);
            deferred.resolve();
            promises.push(deferred);
        };
        return $.when.apply(undefined, promises).promise();
    }

    var loadMoreTenant = function() {
        $.ajax({
            url: '{{ url("app/v1/service/load-more") }}',
            method: 'GET',
            timeout: 60000,
            async: true,
            data: {
                take: take,
                skip: skip,
                keyword: keyword,
                cid: cid,
                fid: fid
            },
            error: function(xhr, textStatus, errorThrown) {
                if (textStatus === 'timeout') {
                    alert('Request timeout. Failed to retrieve more tenants');
                }
            }
        })
        .done(function(data) {
            skip = skip + take;

            if(data.records.length > 0) {
                insertRecords(data.records);

                // Check if browser supports LocalStorage
                if(typeof(Storage) !== 'undefined') {
                    var dataJson = data;
                    var serviceData = localStorage.getItem('serviceData');

                    // Check if serviceData exists.
                    if (serviceData) {
                        var jsonObj = JSON.parse(serviceData);
                        // Concat the current record with the existing serviceData.
                        dataJson.records = jsonObj.records.concat(dataJson.records);
                    }

                    // Set serviceData in localStorage.
                    localStorage.setItem('serviceData', JSON.stringify(dataJson));
                }

                //FB.XFBML.parse();
            }

            canLoadMoreService = (skip < data.total_records);
        });
    };


    $(window).on('scroll', function() {
        var scrollTop = $(window).scrollTop();
        // Check if browser supports LocalStorage
        if(typeof(Storage) !== 'undefined') {
            // Prevent Safari to set scrollTop position to 0 on page load.
            if (scrollTop) {
                localStorage.setItem('scrollTop', scrollTop);
            }
        }

        // Auto load more implementation.
        var totalHeight = $(document).height();

        // Check if scroll has reached 75% of total page height.
        if (canLoadMoreService && scrollTop >= (totalHeight * 0.75)) {
            canLoadMoreService = false;
            loadMoreTenant();
        }
    });

    $(document).ready(function(){
        // Apply lazy loads to images.
        initImageLazyload($('img.img-fit-tenant[data-original]'));

        // Check if browser supports LocalStorage
        if(typeof(Storage) !== 'undefined') {
            // This feature is implemented for tracking whether this page is loaded from detail page. (Which is back button)
            var currentValue = localStorage.getItem('fromSource');
            if (currentValue && currentValue === 'detail') {
                // Set isFromDetail to true
                isFromDetail = true;
            }
            else {
                // Clear serviceData in localStorage.
                localStorage.removeItem('serviceData');
            }

            // Set fromSource in localStorage.
            localStorage.setItem('fromSource', 'store');
        }

        $(document).on('show.bs.modal', '.modal', function (event) {
            var zIndex = 1040 + (10 * $('.modal:visible').length);
            $(this).css('z-index', zIndex);
            setTimeout(function() {
                $('.modal-backdrop').not('.modal-stack').css('z-index', 0).addClass('modal-stack');
            }, 0);
        });

        var path = '{{$urlblock->blockedRoute('ci-service-list', ['keyword' => e(Input::get('keyword')), 'sort_by' => 'name', 'sort_mode' => 'asc', 'cid' => e(Input::get('cid')), 'fid' => e(Input::get('fid'))])}}';
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

        // Check if page is from back button.
        if (isFromDetail) {
            var serviceData = localStorage.getItem('serviceData');
            // Check if there's serviceData in localStorage.
            if (serviceData) {
                // Re-insert all the tenant data records back.
                var tenants = JSON.parse(serviceData);
                var records = tenants.records;
                skip += records.length;

                insertRecords(records).then(function() {
                    // See if there is a scrollTop and go there.
                    var scrollTop = +localStorage.getItem('scrollTop');
                    if (scrollTop) {
                        // This setTimeout is needed for iOS mobile browser.
                        setTimeout(function() {
                            $(window).scrollTop(scrollTop);
                        }, 750);
                    }
                });

                canLoadMoreService = (skip < tenants.total_records);
            }
            else {
                // Just maintain scroll position.
                var scrollTop = +localStorage.getItem('scrollTop');
                if (scrollTop) {
                    // This setTimeout is needed for mostly iOS mobile browser.
                    setTimeout(function() {
                        $(window).scrollTop(scrollTop);
                    }, 750);
                }
            }
        }


    });
</script>
@stop
