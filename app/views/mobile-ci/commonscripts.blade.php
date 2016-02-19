<!-- Search Product Modal -->
<div class="modal fade" id="SearchProducts" tabindex="-1" role="dialog" aria-labelledby="SearchProduct" aria-hidden="true">
    <div class="modal-dialog orbit-modal">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title" id="SearchProduct">{{ Lang::get('mobileci.modals.search_title') }}</h4>
            </div>
            <div class="modal-body">
                <form method="GET" name="searchForm" id="searchForm" action="{{ url('/customer/tenants') }}">
                    <div class="form-group">
                        <label for="keyword">{{ Lang::get('mobileci.modals.search_label') }}</label>
                        <input type="text" class="form-control" name="keyword" id="keyword" placeholder="{{ Lang::get('mobileci.modals.search_placeholder') }}">
                        {{ \Orbit\UrlGenerator::hiddenSessionIdField() }}
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" id="searchProductBtn">{{ Lang::get('mobileci.modals.search_button') }}</button>
            </div>
        </div>
    </div>
</div>
@if(Config::get('orbit.shop.membership'))
<div class="modal fade bs-example-modal-sm" id="membership-card-popup" tabindex="-1" role="dialog" aria-labelledby="membership-card" aria-hidden="true">
    <div class="modal-dialog modal-sm orbit-modal" style="width:320px; margin: 30px auto;">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title">{{ Lang::get('mobileci.modals.membership_title') }}</h4>
            </div>
            <div class="modal-body">
                @if (! empty($user))
                    @if (! empty($user->membershipNumbers->first()) && ($user->membershipNumbers[0]->status === 'active'))
                    <div class="member-card">
                        @if (empty($user->membershipNumbers[0]->membership->media->first()))
                        <img class="img-responsive membership-card" src="{{ asset('mobile-ci/images/membership_card_default.png') }}">
                        @else
                        <img class="img-responsive membership-card" src="{{ asset($user->membershipNumbers[0]->membership->media[0]->path) }}">
                        @endif
                        <h2>
                            <span class="membership-number">
                                <strong>
                                    {{ (mb_strlen($user->user_firstname . ' ' . $user->user_lastname) >= 20) ? substr($user->user_firstname . ' ' . $user->user_lastname, 0, 20) : $user->user_firstname . ' ' . $user->user_lastname }}
                                </strong>
                                <span class='spacery'></span>
                                <br>
                                <span class='spacery'></span>
                                <strong>
                                    {{ $user->membership_number }}
                                </strong>
                            </span>
                        </h2>
                    </div>
                    @else
                    <div class="no-member-card text-center">
                        <h3><strong><i>{{ Lang::get('mobileci.modals.membership_notfound') }}</i></strong></h3>
                        <h4><strong>{{ Lang::get('mobileci.modals.membership_want_member') }}</strong></h4>
                        <p>{{ Lang::get('mobileci.modals.membership_great_deal') }}</p>
                        <p><i>{{ Lang::get('mobileci.modals.membership_contact_our') }}</i></p>
                        <br>
                    </div>
                    @endif
                @endif
            </div>
            <div class="modal-footer">
            </div>
        </div>
    </div>
</div>
@endif
<!-- Language Modal -->
<div class="modal fade bs-example-modal-sm" id="multi-language-popup" tabindex="-1" role="dialog" aria-labelledby="multi-language" aria-hidden="true">
    <div class="modal-dialog modal-sm orbit-modal" style="width:320px; margin: 30px auto;">
        <div class="modal-content">
            <div class="modal-header orbit-modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">{{ Lang::get('mobileci.modals.close') }}</span></button>
                <h4 class="modal-title">{{ Lang::get('mobileci.modals.language_title') }}</h4>
            </div>
            <form method="POST" name="selecLang" action="{{ url('/customer/setlanguage') }}">
                <div class="modal-body">
                    <select class="form-control" name="lang" id="selected-lang">
                        @if (isset($languages))
                                @foreach ($languages as $lang)
                                    <option value="{{{ $lang->language->name }}}" @if (isset($_COOKIE['orbit_preferred_language'])) @if ($lang->language->name === $_COOKIE['orbit_preferred_language']) selected @endif @else @if($lang->language->name === $default_lang) selected @endif @endif>{{{ $lang->language->name_long }}} @if($lang->language->name === $default_lang) (Default) @endif</option>
                                @endforeach
                        @endif
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-info" value="{{ Lang::get('mobileci.modals.ok') }}">{{ Lang::get('mobileci.modals.ok') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="campaign-cards-container">
    <div class="row campaign-cards-wrapper">
        <div class="col-xs-12 text-right campaign-cards-close-btn">
            <button class="close" id='campaign-cards-close-btn'>&times;</button>
        </div>
        <div class="col-xs-12 text-center">
            <ul id="campaign-cards" class="gallery list-unstyled cS-hidden">
                
            </ul>
        </div>
    </div>
</div>
<div class="row back-drop campaign-cards-back-drop"></div>

<div class="search-container">
    <div class="row search-wrapper">
        <div class="search-top">
            <div class="col-xs-12 text-right campaign-cards-close-btn search-close-btn">
                <button class="close" id="search-close-btn">&times;</button>
            </div>
            <div class="col-xs-12 text-left search-box">
                <span class="col-xs-1"><i class="fa fa-search"></i></span>
                <input id="search-type" class="col-xs-11 search-type" type="text" placeholder="{{Lang::get('mobileci.search.search_placeholder')}}">
            </div>
        </div>
        <div class="search-bottom">
            <div class="col-xs-12 text-left search-results" style="display:none;"></div>
        </div>
    </div>
</div>
<div class="row back-drop search-back-drop"></div>

{{ HTML::script(Config::get('orbit.cdn.jqueryui.1_11_2', 'mobile-ci/scripts/jquery-ui.min.js')) }}
{{-- Script fallback --}}
<script>
    if (typeof jQuery.ui === 'undefined') {
        document.write('<script src="{{asset('mobile-ci/scripts/jquery-ui.min.js')}}">\x3C/script>');
    }
</script>
{{-- End of Script fallback --}}

{{ HTML::script('mobile-ci/scripts/offline.js') }}
{{ HTML::script(Config::get('orbit.cdn.lightslider.1_1_2', 'mobile-ci/scripts/lightslider.min.js')) }}
{{-- Script fallback --}}
<script>
    if (typeof $().lightSlider === 'undefined') {
        document.write('<script src="{{asset('mobile-ci/scripts/lightslider.min.js')}}">\x3C/script>');
    }
</script>
{{-- End of Script fallback --}}

{{ HTML::script(Config::get('orbit.cdn.panzoom.2_0_5', 'mobile-ci/scripts/jquery.panzoom.min.js')) }}
{{-- Script fallback --}}
<script>
    if (typeof $().panzoom === 'undefined') {
        document.write('<script src="{{asset('mobile-ci/scripts/jquery.panzoom.min.js')}}">\x3C/script>');
    }
</script>
{{-- End of Script fallback --}}

{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
<script type="text/javascript">
    var keyword = '{{{Input::get('keyword', '')}}}';
    var take = {{Config::get('orbit.pagination.per_page', 25)}}, 
        skip = {{Config::get('orbit.pagination.per_page', 25)}};
        total_x_item = 0;
    /* Load more X function
     * It is used on news, promotion, lucky draw and coupon list
     * parameters: itemtype(news,promotion,lucky-draw,my-coupon)
     *             ids(array(list of already loaded ids))
     */
    function loadMoreX(itemtype, ids) {
        var catalogueWrapper = $('.catalogue-wrapper');
        var itemList = [];
        var btn = $('#load-more-x');
        btn.attr('disabled', 'disabled');
        btn.html('<i class="fa fa-circle-o-notch fa-spin"></i>');
        $.ajax({
            url: apiPath + itemtype + '/load-more',
            method: 'GET',
            data: {
                take: take,
                keyword: keyword,
                skip: skip,
                ids: ids
            }
        }).done(function(data) {
            if(data.status == 1) {
                skip = skip + take;
                if(data.records.length > 0) {
                    for(var i = 0; i < data.records.length; i++) {
                        var coupon_badge = '';
                        if(itemtype === 'my-coupon') {
                            coupon_badge = '<div class="coupon-new-badge"><div class="new-number">'+data.records[i].quantity+'</div></div>';
                        }
                        var list = '<div class="col-xs-12 col-sm-12 item-x" data-ids="'+data.records[i].item_id+'" id="item-'+data.records[i].item_id+'">\
                                <section class="list-item-single-tenant">\
                                    <a class="list-item-link" href="'+data.records[i].url+'">\
                                        '+coupon_badge+'\
                                        <div class="list-item-info">\
                                            <header class="list-item-title">\
                                                <div><strong>'+data.records[i].name+'</strong></div>\
                                            </header>\
                                            <header class="list-item-subtitle">\
                                                <div>'+data.records[i].description+'</div>\
                                            </header>\
                                        </div>\
                                        <div class="list-vignette-non-tenant"></div>\
                                        <img class="img-responsive img-fit-tenant" src="'+data.records[i].image+'"/>\
                                    </a>\
                                </section>\
                            </div>';

                        itemList.push(list);
                    }
                    catalogueWrapper.append(itemList.join(''));
                }
                if (data.total_records - take <= 0) {
                    btn.remove();
                }
            } else {
                if(data.message === 'session_expired') {
                    window.location.replace('/customer');
                }
            }
        }).always(function(data){
            btn.removeAttr('disabled', 'disabled');
            btn.html('{{Lang::get('mobileci.notification.load_more_btn')}}');
        });
    }

    var tabOpen = false; // this var is for tabs on tenant detail views
    $(document).ready(function(){
        var menuOpen = false;
        navigator.getBrowser= (function(){
            var ua = navigator.userAgent, tem,
                M = ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
            if(/trident/i.test(M[1])){
                tem=  /\brv[ :]+(\d+)/g.exec(ua) || [];
                return 'IE '+(tem[1] || '');
            }
            if(M[1]=== 'Chrome'){
                tem= ua.match(/\b(OPR|Edge)\/(\d+)/);
                if(tem!= null) return tem.slice(1).join(' ').replace('OPR', 'Opera');
            }
            M= M[2]? [M[1], M[2]]: [navigator.appName, navigator.appVersion, '-?'];
            if((tem= ua.match(/version\/(\d+)/i))!= null) M.splice(1, 1, tem[1]);
            return M;
        })();
        var browser = navigator.getBrowser[0];
        $.fn.addBlur = function(){
            if(browser.indexOf('Firefox') < 0) {
                $(this).removeClass('unblurred');
                $(this).addClass('blurred');
            }
        }
        $.fn.removeBlur = function(){
            if(browser.indexOf('Firefox') < 0) {
                $(this).removeClass('blurred');
                $(this).addClass('unblurred');
            }
        }
        function isInArray(value, str) {
            return str.indexOf(value) > -1;
        }
        function viewPopUpActivity(campaign_id, campaign_type) {
            $.ajax({
                url: apiPath + 'campaign/activities',
                method: 'POST',
                data: {
                    campaign_id: campaign_id,
                    campaign_type: campaign_type,
                    activity_type: 'view'
                }
            });
        }
        var slider = null;
        var cookieLang = $.cookie('orbit_preferred_language') ? $.cookie('orbit_preferred_language') : 'en'; //send user lang from cookie
        setTimeout(function(){
            if ($.cookie('dismiss_campaign_cards') !== 't') {
                $.ajax({
                    url: apiPath + 'campaign/list?lang='+cookieLang,
                    method: 'GET'
                }).done(function(data) {
                    if(data.data.total_records) {
                        for(var i = 0; i < data.data.records.length; i++) {
                            var list = '<li data-thumb="'+ data.data.records[i].campaign_image +'" data-campaign-id="'+ data.data.records[i].campaign_id +'" data-campaign-type="'+ data.data.records[i].campaign_type +'">\
                                    <img class="img-responsive" src="'+ data.data.records[i].campaign_image +'"/>\
                                    <div class="campaign-cards-info">\
                                        <h4><strong>'+ data.data.records[i].campaign_name +'</strong></h4>\
                                        <p>'+ data.data.records[i].campaign_description +'</p>\
                                        <a class="campaign-cards-link" data-id="'+ data.data.records[i].campaign_id +'" data-type="'+ data.data.records[i].campaign_type +'" href="'+ data.data.records[i].campaign_url +'"><i>{{ Lang::get('mobileci.campaign_cards.go_to_page') }}</i></a>\
                                    </div>\
                                </li>';
                            $('#campaign-cards').append(list);
                        }
                        var autoSliderOption = data.data.records.length > 1 ? true : false;
                        $('body').addClass('freeze-scroll');
                        $('.content-container, .header-container, footer').addBlur();
                        $('.campaign-cards-back-drop').fadeIn('slow');
                        $('.campaign-cards-container').toggle('slide', {direction: 'down'}, 'slow');
                        slider = $('#campaign-cards').lightSlider({
                            gallery:false,
                            item:1,
                            slideMargin: 20,
                            speed:500,
                            pause:2000,
                            auto:autoSliderOption,
                            loop:autoSliderOption,
                            pager: autoSliderOption,
                            onSliderLoad: function(el) {
                                $.cookie('dismiss_campaign_cards', 't', {expires: 3650, path: '/'});
                                $('#campaign-cards').removeClass('cS-hidden');
                                var active_card_id = $(el).children('.active').data('campaign-id');
                                var active_card_type = $(el).children('.active').data('campaign-type');
                                var recorded_popup = localStorage.getItem('campaign_popup') ? localStorage.getItem('campaign_popup') : '';
                                if(!recorded_popup) {
                                    localStorage.setItem('campaign_popup', '');
                                }
                                if(!isInArray(active_card_id, recorded_popup)){
                                    localStorage.setItem('campaign_popup', recorded_popup + ', ' + active_card_id);
                                    viewPopUpActivity(active_card_id, active_card_type);
                                }
                            },
                            onBeforeSlide: function (el) {
                                $('#campaign-cards-close-btn').fadeOut('fast');
                            },
                            onAfterSlide: function(el) {
                                $('#campaign-cards-close-btn').fadeIn('fast');
                                var active_card_id = $(el).children('.active').data('campaign-id');
                                var active_card_type = $(el).children('.active').data('campaign-type');
                                var recorded_popup = localStorage.getItem('campaign_popup');
                                if(!isInArray(active_card_id, recorded_popup)){
                                    localStorage.setItem('campaign_popup', recorded_popup + ', ' + active_card_id);
                                    viewPopUpActivity(active_card_id, active_card_type);
                                }
                            }
                        });
                        // if(browser.indexOf('IE')) {
                        //     $('#campaign-cards .img-responsive').css('height', 'auto');
                        // }
                    }
                });
            }
        }, ({{ Config::get('orbit.shop.event_delay', 2.5) }} * 1000));

        $('#campaign-cards-close-btn, .campaign-cards-back-drop').click(function(){
            slider.pause();
            $.cookie('dismiss_campaign_cards', 't', {expires: 3650, path: '/'});
            $('body').removeClass('freeze-scroll');
            $('.content-container, .header-container, footer').removeBlur();
            $('.campaign-cards-back-drop').fadeOut('slow');
            $('.campaign-cards-container').toggle('slide', {direction: 'up'}, 'fast');
        });

        $('body').on('click', '.campaign-cards-link', function(e){
            e.preventDefault();
            var campaign_id = $(this).data('id');
            var campaign_type = $(this).data('type');
            $.ajax({
                url: apiPath + 'campaign/activities',
                method: 'POST',
                data: {
                    campaign_id: campaign_id,
                    campaign_type: campaign_type,
                    activity_type: 'click'
                }
            });
            $.cookie('dismiss_campaign_cards', 't', {expires: 3650, path: '/'});
            window.location = $(this).attr('href');
        });

        var run = function () {
            if (Offline.state === 'up') {
              $('#offlinemark').attr('class', 'fa fa-check fa-stack-1x').css({
                'color': '#3c9',
                'left': '6px',
                'top': '0px',
                'font-size': '1em'
              });
              Offline.check();
            } else {
              $('#offlinemark').attr('class', 'fa fa-times fa-stack-1x').css({
                'color': 'red',
                'left': '6px',
                'top': '0px',
                'font-size': '1em'
              });
            }
        };

        @if (Config::get('orbit.shop.offline_check.enable'))
            run();
            setInterval(run, {{ Config::get('orbit.shop.offline_check.interval', 5000) }} );
        @endif

        $('#barcodeBtn').click(function(){
            $('#get_camera').click();
        });
        $('#get_camera').change(function(){
            $('#qrform').submit();
        });
        $('#searchBtn').click(function(){
            $('.search-container').toggle('slide', {direction: 'down'}, 'slow');
            $('.search-top').toggle('slide', {direction: 'down'}, 'fast');
            $('.search-back-drop').fadeIn('fast');
            $('#search-type').val('');
            $('.content-container, .header-container, footer').addBlur();
            //$('#SearchProducts').modal();
            setTimeout(function(){
                $('#search-type').focus();
            }, 10);
        });
        $('#search-close-btn').click(function(){
            $('.search-container').toggle('slide', {direction: 'down'}, 'slow');
            $('.search-top').toggle('slide', {direction: 'down'}, 'fast');
            $('.search-back-drop').fadeOut('fast');
            if(!menuOpen){
                $('.content-container, footer').removeBlur();
            }
            $('.header-container').removeBlur();
            $('#search-type').val('');
            // ------------- cuma dummy
            $('.search-results').hide();
            // ---------------- -------
        });
        var search_results = {};
        search_results.tenants = [];
        search_results.news = [];
        search_results.promotions = [];
        search_results.coupons = [];
        search_results.lucky_draws = [];
        $('#search-type').keydown(function (e){
            if(e.keyCode == 13){
                $('#search-type').blur();
                $('#search-type').attr('disabled', 'disabled');
                $('.search-results').fadeOut('fast');
                var keyword = encodeURIComponent($('#search-type').val());
                var loader = '<div class="text-center" id="search-loader" style="font-size:48px;color:#fff;"><i class="fa fa-spinner fa-spin"></i></div>';
                $('.search-wrapper').append(loader);

                $.ajax({
                    url: apiPath + 'keyword/search?keyword=' + keyword + '&lang=' + cookieLang,
                    method: 'GET'
                }).done(function(data) {
                    if (data.data.total_records > 0) {
                        // var show_result = '<div class="search-btn"><a id="show_all_result"><span class="col-xs-8"><strong>{{Lang::get('mobileci.search.show_all_result')}}</strong></span><span class="col-xs-4 text-right"><i class="fa fa-chevron-right"></i></span></a></div>';
                        var show_result = '';
                        var tenants='',promotions='',news='',coupons='',lucky_draws='';
                        if (data.data.grouped_records.tenants.length > 0) {
                            search_results.tenants = data.data.grouped_records.tenants;
                            tenants = '<h4>{{Lang::get('mobileci.page_title.tenant_directory')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.tenants.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                tenants += '<li class="search-result-group '+ hide +'">\
                                        <a href="'+ data.data.grouped_records.tenants[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.tenants[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.tenants[i].object_name +'</strong></h5>\
                                                <p>'+ (data.data.grouped_records.tenants[i].object_description ? data.data.grouped_records.tenants[i].object_description : '') +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.tenants_counts > 3) {
                                tenants += '<a href="'+ data.data.grouped_records.tenants_url +'" class="text-right" style="display:block;color:#fff;">Show more</a>';
                            }
                            tenants += '</ul>';
                        }
                        if (data.data.grouped_records.promotions.length > 0) {
                            search_results.promotions = data.data.grouped_records.promotions;
                            promotions = '<h4>{{Lang::get('mobileci.page_title.promotions')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.promotions.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                promotions += '<li class="search-result-group '+ hide +'">\
                                        <a href="'+ data.data.grouped_records.promotions[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.promotions[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.promotions[i].object_name +'</strong></h5>\
                                                <p>'+ (data.data.grouped_records.promotions[i].object_description ? data.data.grouped_records.promotions[i].object_description : '') +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.promotions_counts > 3) {
                                promotions += '<a href="'+ data.data.grouped_records.promotions_url +'" class="text-right" style="display:block;color:#fff;">Show more</a>';
                            }
                            promotions += '</ul>';
                        }
                        if (data.data.grouped_records.news.length > 0) {
                            search_results.news = data.data.grouped_records.news;
                            news = '<h4>{{Lang::get('mobileci.page_title.news')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.news.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                news += '<li class="search-result-group '+ hide +'">\
                                        <a href="'+ data.data.grouped_records.news[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.news[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.news[i].object_name +'</strong></h5>\
                                                <p>'+ (data.data.grouped_records.news[i].object_description ? data.data.grouped_records.news[i].object_description : '') +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.news_counts > 3) {
                                news += '<a href="'+ data.data.grouped_records.news_url +'" class="text-right" style="display:block;color:#fff;">Show more</a>';
                            }
                            news += '</ul>';
                        }
                        if (data.data.grouped_records.coupons.length > 0) {
                            search_results.coupons = data.data.grouped_records.coupons;
                            coupons = '<h4>{{Lang::get('mobileci.page_title.coupons')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.coupons.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                coupons += '<li class="search-result-group '+ hide +'">\
                                        <a href="'+ data.data.grouped_records.coupons[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.coupons[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.coupons[i].object_name +'</strong></h5>\
                                                <p>'+ (data.data.grouped_records.coupons[i].object_description ? data.data.grouped_records.coupons[i].object_description : '') +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.coupons_counts > 3) {
                                coupons += '<a href="'+ data.data.grouped_records.coupons_url +'" class="text-right" style="display:block;color:#fff;">Show more</a>';
                            }
                            coupons += '</ul>';
                        }
                        if (data.data.grouped_records.lucky_draws.length > 0) {
                            search_results.lucky_draws = data.data.grouped_records.lucky_draws;
                            lucky_draws = '<h4>{{Lang::get('mobileci.page_title.lucky_draws')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.lucky_draws.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                lucky_draws += '<li class="search-result-group '+ hide +'">\
                                        <a href="'+ data.data.grouped_records.lucky_draws[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.lucky_draws[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.lucky_draws[i].object_name +'</strong></h5>\
                                                <p>'+ (data.data.grouped_records.lucky_draws[i].object_description ? data.data.grouped_records.lucky_draws[i].object_description : '') +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.lucky_draws_counts > 3) {
                                lucky_draws += '<a href="'+ data.data.grouped_records.lucky_draws_url +'" class="text-right" style="display:block;color:#fff;">Show more</a>';
                            }
                            lucky_draws += '</ul>';
                        }
                        var zonk = '<div style="width:100%;height:160px;background:transparent;">&nbsp;</div>'
                        $('.search-results').html(show_result + tenants + promotions + news + coupons + lucky_draws + zonk);
                    } else {
                        if(data.message == 'Your session has expired.' || data.message == 'Invalid session data.') {
                            window.location.href = 'http://' + location.host;
                        }
                        $('.search-results').html('<h5><i>{{Lang::get('mobileci.search.no_result')}}</i></h5>');
                    }
                }).fail(function(data){
                    $('.search-results').html('<h5><i>{{Lang::get('mobileci.search.error')}}</i></h5>');
                }).always(function(data) {
                    $('#search-type').removeAttr('disabled');
                    $('.search-results').fadeIn('fast');
                    $('#search-loader').remove();
                });
            }
        });
        $('body').on('click', '#show_all_result', function(){
            $('.search-btn').html('<a id="show_by_categories"><span class="col-xs-8"><strong>{{Lang::get('mobileci.search.show_by_categories')}}</strong></span><span class="col-xs-4 text-right"><i class="fa fa-chevron-left"></i></span></a>');
            $('.search-results').fadeOut('fast', function(){
                $('.search-result-group.limited').removeClass('hide');
            });
            $('.search-results').fadeIn('slow');
        });
        $('body').on('click', '#show_by_categories', function(){
            $('.search-btn').html('<a id="show_all_result"><span class="col-xs-8"><strong>{{Lang::get('mobileci.search.show_all_result')}}</strong></span><span class="col-xs-4 text-right"><i class="fa fa-chevron-right"></i></span></a>');
            $('.search-results').fadeOut('fast', function(){
                $('.search-result-group.limited').addClass('hide');
            });
            $('.search-results').fadeIn('slow');
        });
        $('#searchProductBtn').click(function(){
            $('#SearchProducts').modal('toggle');
            $('#searchForm').submit();
        });
        $('#backBtn').click(function(){
            window.history.back()
        });
        $('.backBtn404').click(function(){
            window.history.back()
        });
        $('#search-tool-btn').click(function(){
            $('#search-tool').toggle();
        });
        if($('#cart-number').attr('data-cart-number') == '0'){
            $('.cart-qty').css('display', 'none');
        }
        @if(Config::get('orbit.shop.membership'))
        $('#membership-card').click(function(){
            $('#membership-card-popup').modal();
        });
        $('#dropdown-disable').click(function(){ event.stopPropagation(); });
        @endif
        $('#multi-language').click(function(){
            $('#multi-language-popup').modal();
        });

        function resetImage() {
            $('.featherlight-image').css('margin', '0 auto');
            $('.featherlight-content').css('width', '100%');
            $('.featherlight-image').css({
                'height': 'auto',
                'width': '100%'
            });
            // this cause problems when zoomed
            // if($(window).height() < $(window).width()) {
            //     $('.featherlight-image').css({
            //         'height': '100%',
            //         'width': 'auto'
            //     });
            // } else {
            //     $('.featherlight-image').css({
            //         'height': 'auto',
            //         'width': '100%'
            //     });
            // }
        }

        function parseMatrix (_str) {
            return _str.replace(/^matrix(3d)?\((.*)\)$/,'$2').split(/, /);
        }

        function getScaleDegrees (obj) {
            var matrix = this.parseMatrix(this.getMatrix(obj)),
                scale = 1;

            if(matrix[0] !== 'none') {
                var a = matrix[0],
                    b = matrix[1],
                    d = 10;
                scale = Math.round( Math.sqrt( a*a + b*b ) * d ) / d;
            }

            return scale;
        }
        var zoomer, fl;
        $(document).on('click', '.zoomer', function(){
            zoomer = $(this);
            setTimeout(function(){
                resetImage();
                fl = $.featherlight.current();
                $("body").addClass("freeze-scroll");
                $('.content-container, .header-container, footer').addBlur();
                $(".featherlight-image").panzoom({
                    minScale: 1,
                    maxScale: 5,
                    $zoomRange: $("input[type='range']"),
                    contain: 'invert',
                    onStart: function() {
                        $('.featherlight-image').css('margin', '0 auto');
                        $('.featherlight-content').css('width', '100%');
                    },
                    onChange: function(){
                        var matrix = parseMatrix($(this).panzoom('getTransform'));
                        var currentScale = matrix[3];
                        if (currentScale <= 1) {
                            resetImage();
                        }
                        $('.featherlight-image').css('margin', '0 auto');
                        $('.featherlight-content').css('width', '100%');
                    }
                });
                $(".featherlight-image").on('panzoomend', function(e, panzoom, matrix, changed) {
                    if(! changed) {
                        fl.close();
                        $("body").removeClass("freeze-scroll");
                        if(!menuOpen){
                            $('.content-container, footer').removeBlur();
                        }
                        $('.header-container').removeBlur();
                    }
                });
            }, 50);
        });

        $(window).on('resize', function() {
            var transforms = [];
            transforms.push('scale(1)');
            transforms.push('translate(0px,0px)');
            $('.featherlight-image').css("transform", transforms.join(' '));
            $(".featherlight-image").panzoom('resetDimensions');
            if(zoomer){
                zoomer.featherlight();
            }
            resetImage();
            $('.slide-menu-middle-container').css('height', ($(window).height() - $('.header-buttons-container').height()) + 'px');
        });

        $(document).on('click', '.featherlight-close', function(){
            $("body").removeClass("freeze-scroll");
            if(!menuOpen){
                $('.content-container, footer').removeBlur();
            }
            $('.header-container').removeBlur();
        });

        $(document).on('click', '.featherlight-content, .featherlight-image', function(){
            fl.close();
            $("body").removeClass("freeze-scroll");
            if(!menuOpen){
                $('.content-container, footer').removeBlur();
            }
            $('.header-container').removeBlur();
        });
        $('#slide-trigger, .slide-menu-backdrop').click(function(){
            if(menuOpen) {
                menuOpen = false;
            } else {
                menuOpen = true;
            }
            // $('html, body').animate({scrollTop:0}, 'fast');
            $('.slide-menu-middle-container').css('height', ($(window).height() - $('.header-buttons-container').height()) + 'px');
            $('.slide-menu-container').toggle('slide', {direction: 'right'}, 'slow');
            $('.slide-menu-backdrop').toggle('fade', 'slow');
            if(menuOpen) {
                $('.header-container').css('height', '100%');
                $('.content-container, .header-location-banner, .header-tenant-tab, footer').addBlur();
                $('body').addClass('freeze-scroll');
                $('#orbit-tour-profile').addClass('active');
                $('#slide-trigger').addClass('active');
            } else {
                if(!menuOpen){
                    $('.content-container, footer').removeBlur();
                }
                $('.header-container').css('height', '92px');
                $('.header-location-banner, .header-tenant-tab').removeBlur();
                if(!tabOpen){
                    $('body').removeClass('freeze-scroll');
                }
                $('#orbit-tour-profile').removeClass('active');
                $('#slide-trigger').removeClass('active');
            }
        });
    });
</script>
