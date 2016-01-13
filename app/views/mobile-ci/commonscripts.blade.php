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
<div class="row campaign-cards-back-drop"></div>

{{ HTML::script('mobile-ci/scripts/jquery-ui.min.js') }}
{{ HTML::script('mobile-ci/scripts/offline.js') }}
{{ HTML::script('mobile-ci/scripts/lightslider.min.js') }}
{{ HTML::script('mobile-ci/scripts/jquery.panzoom.min.js') }}
{{ HTML::script('mobile-ci/scripts/jquery.cookie.js') }}
<script type="text/javascript">
    $(document).ready(function(){
        setTimeout(function(){
            if ($.cookie('dismiss_campaign_cards') !== 't') {
                $.ajax({
                    url: apiPath + 'campaign/list',
                    method: 'GET'
                }).done(function(data) {
                    if(data.data.total_records) {
                        for(var i = 0; i < data.data.records.length; i++) {
                            var list = '<li data-thumb="'+ data.data.records[i].campaign_image +'">\
                                    <img class="img-responsive" src="'+ data.data.records[i].campaign_image +'"/>\
                                    <div class="campaign-cards-info">\
                                        <h4><strong>'+ data.data.records[i].campaign_name +'</strong></h4>\
                                        <p>'+ data.data.records[i].campaign_description +'</p>\
                                        <a class="campaign-cards-link" href="'+ data.data.records[i].campaign_url +'"><i>'+ data.data.records[i].campaign_link +'</i></a>\
                                    </div>\
                                </li>';
                            $('#campaign-cards').append(list);
                        }
                        var autoSliderOption = data.data.records.length > 1 ? true : false;
                        $('body').addClass('freeze-scroll');
                        $('.content-container, .header-container, footer').addClass('blurred');
                        $('.campaign-cards-back-drop').fadeIn('slow');
                        $('.campaign-cards-container').toggle('slide', {direction: 'down'}, 'slow');
                        $('#campaign-cards').lightSlider({
                            gallery:false,
                            item:1,
                            slideMargin: 20,
                            speed:500,
                            pause:2000,
                            auto:autoSliderOption,
                            loop:autoSliderOption,
                            pager: autoSliderOption,
                            onSliderLoad: function() {
                                $('#campaign-cards').removeClass('cS-hidden');
                            },
                            onAfterSlide: function() {
                            }
                        });
                    }
                });
            }
        }, ({{ Config::get('orbit.shop.event_delay', 2.5) }} * 1000));

        $('#campaign-cards-close-btn, .campaign-cards-back-drop').click(function(){
            $.cookie('dismiss_campaign_cards', 't', {expires: 3650, path: '/'});
            $('body').removeClass('freeze-scroll');
            $('.content-container, .header-container, footer').removeClass('blurred');
            $('.campaign-cards-back-drop').fadeOut('slow');
            $('.campaign-cards-container').toggle('slide', {direction: 'up'}, 'fast');
        });


        $('body').on('click', '.campaign-cards-link', function(e){
            e.preventDefault();
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
            $('#SearchProducts').modal();
            setTimeout(function(){
                $('#keyword').focus();
            }, 500);
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
        });

        $(document).on('click', '.featherlight-close', function(){
            $("body").removeClass("freeze-scroll");
        });

        $(document).on('click', '.featherlight-content, .featherlight-image', function(){
            fl.close();
            $("body").removeClass("freeze-scroll");
        });

        $('#slide-trigger, .slide-menu-backdrop').click(function(){
            $('.slide-menu-container').toggle('slide', {direction: 'right'}, 'slow');
            $('.slide-menu-backdrop').toggle('fade', 'slow');
            $('html').toggleClass('freeze-scroll');
            $('#orbit-tour-profile').toggleClass('active');
            $('#slide-trigger').toggleClass('active');
        });
    });
</script>
