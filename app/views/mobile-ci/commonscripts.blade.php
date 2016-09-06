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
                                    {{{ (mb_strlen($user->user_firstname . ' ' . $user->user_lastname) >= 20) ? substr($user->user_firstname . ' ' . $user->user_lastname, 0, 20) : $user->user_firstname . ' ' . $user->user_lastname }}}
                                </strong>
                                <span class='spacery'></span>
                                <br>
                                <span class='spacery'></span>
                                <strong>
                                    {{{ $user->membership_number }}}
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
                                <option value="{{{ $lang->language->name }}}" @if ($lang->language->name === App::getLocale()) selected @endif>
                                    {{{ $lang->language->name_long }}} @if($lang->language->name === $default_lang) (Default) @endif
                                </option>
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

@if (! $is_logged_in)
<div class="sign-in-popup" style="display:none;">
    <div class="row sign-in-popup-wrapper">
        <div class="col-xs-12 text-center content-signin content-signin-popup">
            <div class="col-xs-12 text-right">
                <button class="close-mark" id="signin-popup-close-btn">&times;</button>
            </div>
            <div class="social-media-container">
                <div class="row vertically-spaced">
                    <div class="col-xs-12 text-center">
                        {{ Lang::get('mobileci.signin.to_access_this_content') }}<br>
                        <b>{{ Lang::get('mobileci.signin.sign_up_sign_in_with') }}</b>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-4 text-center">
                        <form name="fbLoginForm" id="fbLoginForm" action="{{ URL::route('mobile-ci.social_login') }}" method="post">
                            <div class="form-group">
                                <input type="hidden" class="form-control" name="time" value="{{{ time() }}}"/>
                                <input type="hidden" class="form-control" name="from_url" id="from_url" value="{{{ \Route::currentRouteName() }}}"/>
                                <input type="hidden" class="form-control to_url" name="to_url" value=""/>
                                <input type="hidden" class="form-control" name="from_captive" value="{{{ Input::get('from_captive', '') }}}"/>
                                <input type="hidden" class="form-control" name="mac_address"
                                       value="{{{ Input::get('mac_address', '') }}}"/>
                                <input type="hidden" class="form-control" name="{{{ 'orbit_origin' }}}"
                                       value="{{{ 'redirect_to_facebook' }}}"/>
                            </div>
                            <div class="form-group">
                                <button id="fbLoginButton" type="submit" class="btn btn-primary icon-button facebook text-center">
                                        <i class="fa fa-facebook fa-4x"></i>
                                </button>
                            </div>
                            <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="yes"/>
                        </form>
                    </div>
                    <div class="col-xs-4 text-center">
                        <form name="googleLoginForm" id="googleLoginForm" action="{{ Config::get('orbit.social_login.google.callback_url') }}" method="get">
                            <div class="form-group">
                                <input type="hidden" class="form-control" name="time" value="{{{ time() }}}"/>
                                <input type="hidden" class="form-control" name="from_captive" value="{{{ Input::get('from_captive', '') }}}"/>
                                <input type="hidden" class="form-control" name="mac_address" value="{{{ Input::get('mac_address', '') }}}"/>
                                <input type="hidden" class="form-control" name="from_url" value="{{{ \Route::currentRouteName() }}}"/>
                                <input type="hidden" class="form-control" name="mid" value="{{{ $retailer->merchant_id }}}"/>
                                <input type="hidden" class="form-control to_url" name="to_url" value=""/>
                            </div>
                            <div class="form-group">
                                <button id="googleLoginButton" type="submit" class="btn btn-danger icon-button google text-center">
                                    <i class="fa fa-google fa-4x"></i>
                                </button>
                            </div>
                            <input class="agree_to_terms" type="hidden" name="agree_to_terms" value="no"/>
                        </form>
                    </div>
                    <div class="col-xs-4 text-center">
                        <button type="button" class="btn btn-info icon-button form text-center" data-toggle="modal" data-target="#formModal"><i class="fa fa-pencil fa-3x"></i></button>
                    </div>
                </div>
                <br/><br/>
                <div class="row">
                    <div class="col-xs-12 text-center">v{{ORBIT_APP_VERSION}} . <a target="_blank" href="{{ Config::get('orbit.contact_information.privacy_policy_url') }}">Privacy Policy</a> . <a target="_blank" href="{{ Config::get('orbit.contact_information.terms_of_service_url') }}">Terms and Conditions</a></div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row back-drop sign-in-back-drop"></div>
<div class="modal fade" id="formModal" tabindex="-1" role="dialog" aria-labelledby="formModalLabel" style="z-index: 1005;">
    <div class="modal-dialog">
        <div class="modal-content" id="signin-form-wrapper">
            <form  name="signinForm" id="signinForm" method="post">
                <div class="modal-body text-center">
                    <input type="hidden" class="form-control to_url" name="to_url" value=""/>
                    <button type="button" class="close close-form" data-dismiss="modal" aria-label="Close">
                        <i class="fa fa-times"></i>
                    </button>
                    <div class="error-msg-box">
                        <div class="error-msg-box-close">
                            &times;
                        </div>
                        <div class="error-msg-message"></div>
                    </div>
                    <div class="form-group">
                        <input type="email" value="{{{ $user_email }}}" class="form-control text-center" name="email" id="email" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}">
                    </div>

                    <div class="form-group">
                        <input type="password" value="" class="form-control text-center" name="password" id="password" placeholder="{{ Lang::get('mobileci.signup.password_placeholder') }}">
                        <p class="password-message text-left">{{ Lang::get('mobileci.signin.password_message') }} </p>
                    </div>
                    <div class="form-group">
                        <input type="submit" name="submit" id="btn-signin-form" class="btn btn-info btn-block icon-button form text-center" value="{{ Lang::get('mobileci.signin.sign_in') }}">
                    </div>
                    <div class="row vertically-spaced">
                        <div class="col-xs-6 text-left">
                            <a id="forgot_password">{{ Lang::get('mobileci.signin.forgot_link') }}</a>
                        </div>
                    </div>
                    <div class="form-group">
                        <i><span>{{ Lang::get('mobileci.signin.doesnt_have_account') }} <a href="#1" id="sign-up-link">{{ Lang::get('mobileci.signin.sign_up') }}</a></span></i>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-content hide" id="forget-form-wrapper">
            <form  name="forgotForm" id="forgotForm" method="post">
                <div class="modal-body text-center">
                    <a href="#1" id="forgot-sign-in-link" style="float: left;color: #000;font-weight: bold;font-size: 18px;"><i class="fa fa-chevron-left"></i></a>
                    <button type="button" class="close close-form" data-dismiss="modal" aria-label="Close">
                        <i class="fa fa-times"></i>
                    </button>
                    <div class="error-msg-box">
                        <div class="error-msg-box-close">
                            &times;
                        </div>
                        <div class="error-msg-message"></div>
                    </div>
                    <div class="form-group">
                        <input type="email" value="" class="form-control text-center" name="email_forgot" id="email_forgot" placeholder="{{ Lang::get('mobileci.signin.email_placeholder') }}">
                    </div>
                    <div class="form-group">
                        <button type="button" id="btn-forgot-form" class="btn btn-info btn-block icon-button form text-center" disabled>{{ Lang::get('mobileci.signin.forgot_button') }}</button>
                    </div>
                </div>
            </form>
            <div id="forget-mail-sent" class="vertically-spaced text-center hide">
                <img class="img-responsive img-center" src="{{asset('mobile-ci/images/mail_sent.png')}}">
                <h4><strong>{{ Lang::get('mobileci.signin.forgot_sent_title') }}</strong></h4>
                <p>{{ Lang::get('mobileci.signin.forgot_sent_sub_title') }}</p>
            </div>
        </div>
        <div class="modal-content hide" id="signup-form-wrapper">
            <form  name="signupForm" id="signupForm" method="post">
                <div class="modal-body">
                    <input type="hidden" class="form-control to_url" name="to_url" value=""/>
                    <button type="button" class="close close-form" data-dismiss="modal" aria-label="Close">
                        <i class="fa fa-times"></i>
                    </button>
                    <div class="error-msg-box">
                        <div class="error-msg-box-close">
                            &times;
                        </div>
                        <div class="error-msg-message"></div>
                    </div>
                    <span class="mandatory-label" style="display:none;">{{ Lang::get('mobileci.signup.fields_are_mandatory') }}</span>
                    <div class="form-group icon-group">
                        <input type="email" value="{{{ $user_email }}}" class="form-control orbit-auto-login" name="email" id="email" placeholder="{{ Lang::get('mobileci.signup.email_placeholder') }}">
                        <div class="form-icon"></div>
                    </div>
                    <div class="form-group icon-group">
                        <input type="password" value="" class="form-control" name="password" id="password" placeholder="{{ Lang::get('mobileci.signup.password_placeholder') }}">
                        <div class="form-icon"></div>
                    </div>
                    <div class="form-group icon-group">
                        <input type="password" value="" class="form-control" name="password_confirmation" id="password_confirmation" placeholder="{{ Lang::get('mobileci.signup.password_confirm_placeholder') }}">
                        <div class="form-icon"></div>
                    </div>
                    <div class="form-group icon-group">
                        <input type="text" class="form-control userName" value="" placeholder="{{ Lang::get('mobileci.signup.first_name') }}" name="firstname" id="firstName">
                        <div class="form-icon"></div>
                    </div>
                    <div class="form-group icon-group">
                        <input type="text" class="form-control" placeholder="{{ Lang::get('mobileci.signup.last_name') }}" name="lastname" id="lastName">
                        <div class="form-icon"></div>
                    </div>
                    <div class="form-group icon-group">
                        <select class="form-control" name="gender" id="gender">
                            <option value="">{{ Lang::get('mobileci.signup.gender') }}</option>
                            <option value="m">{{ Lang::get('mobileci.signup.male') }}</option>
                            <option value="f">{{ Lang::get('mobileci.signup.female') }}</option>
                        </select>
                        <div class="form-icon"></div>
                    </div>
                    <div class="form-group date-of-birth">
                        <div class="row">
                            <div class="col-xs-12">
                                <b>{{ Lang::get('mobileci.signup.date_of_birth') }}</b>
                            </div>
                        </div>
                        <div class="row">
                            <div class="icon-group col-xs-4">
                                <select class="form-control" name="day">
                                    <option value="">{{ Lang::get('mobileci.signup.day') }}</option>
                                @for ($i = 1; $i <= 31; $i++)
                                    <option value="{{$i}}">{{$i}}</option>
                                @endfor
                                </select>
                                <div class="form-icon form-icon-select"></div>
                            </div>
                            <div class="icon-group col-xs-4">
                                <select class="form-control" name="month">
                                    <option value="">{{ Lang::get('mobileci.signup.month') }}</option>
                                @for ($i = 1; $i <= 12; $i++)
                                    <option value="{{$i}}">{{$i}}</option>
                                @endfor
                                </select>
                                <div class="form-icon form-icon-select"></div>
                            </div>
                            <div class="icon-group col-xs-4">
                                <select class="form-control" name="year">
                                    <option value="">{{ Lang::get('mobileci.signup.year') }}</option>
                                @for ($i = date('Y'); $i >= date('Y') - 150; $i--)
                                    <option value="{{$i}}">{{$i}}</option>
                                @endfor
                                </select>
                                <div class="form-icon form-icon-select"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <i>{{ sprintf(Lang::get('mobileci.signup.policy_terms_message'), Config::get('orbit.contact_information.privacy_policy_url'), Config::get('orbit.contact_information.terms_of_service_url')) }}</i>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-xs-12">
                                <input type="submit" name="submit" id="btn-signup-form" class="btn btn-info btn-block icon-button form text-center orbit-auto-login" value="{{ Lang::get('mobileci.signin.sign_up') }}">
                            </div>
                        </div>
                        <div class="row vertically-spaced">
                            <div class="col-xs-12 text-center">
                                <i><span>{{{ Lang::get('mobileci.signup.already_have_an_account') }}} <a href="#1" id="sign-in-link">{{{ Lang::get('mobileci.signin.sign_in') }}}</a></span></i>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
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
    // force reload page on every visit including back button
    $(window).bind("pageshow", function(event) {
        if (event.originalEvent.persisted) {
            document.body.style.display = "none";
            window.location.reload();
        }
    });
    @if (! $is_logged_in && Input::get('do_sign_in') === 'true')
    var popup_default_url = '{{\URL::route('ci-customer-home')}}';
    var popup_current_url = '{{Input::get('redirect_url')}}';

    if (popup_current_url) {
        popup_default_url = popup_current_url;
    }

    $('.to_url').val(popup_default_url);
    $('.sign-in-back-drop').fadeIn('fast');
    $('.sign-in-popup').toggle('slide', {direction: 'down'}, 'fast');

    @if (! Config::get('orbit.shop.dismissable_signin_popup', TRUE))
    $('#signin-popup-close-btn').remove();
    @endif
    @endif
    var keyword = '{{{Input::get('keyword', '')}}}';
    var take = {{Config::get('orbit.pagination.per_page', 25)}},
        skip = {{Config::get('orbit.pagination.per_page', 25)}};
        total_x_item = 0;
    /* Load more X function
     * It is used on news, promotion, lucky draw and coupon list
     * parameters: itemtype(news,promotion,lucky-draw,my-coupon)
     *             ids(array(list of already loaded ids))
     */
    function loadMoreX(itemtype, ids, helperObject, callback) {
        var catalogueWrapper = $('.catalogue-wrapper'),
            itemList = [],
            btn = $('#load-more-x'),
            ajaxParams = {
                take: take,
                keyword: keyword,
                skip: skip,
                ids: ids
            };

        if (helperObject !== undefined) {
            /* skip page for coupon only */
            if (helperObject.skip !== undefined) {
                ajaxParams.skip = helperObject.skip;
            }

            if (helperObject.coupon_type !== undefined) {
                ajaxParams.coupon_type = helperObject.coupon_type;
            }
        }

        btn.attr('disabled', 'disabled');
        btn.html('<i class="fa fa-circle-o-notch fa-spin"></i>');
        switch (itemtype) {
            case 'my-coupon':
            var url = '{{ url('/app/v1/my-coupon/load-more') }}';
            break;

            case 'news':
            var url = '{{ url('/app/v1/news/load-more') }}';
            break;

            case 'promotion':
            var url = '{{ url('/app/v1/promotion/load-more') }}';
            break;

            case 'service':
            var url = '{{ url('/app/v1/service/load-more') }}';
            break;

            case 'tenant':
            var url = '{{ url('app/v1/tenant/load-more') }}';
            break;

            case 'lucky-draw':
            var url = '{{ url('/app/v1/lucky-draw/load-more') }}';
            break;

            default:
            break;
        }
        $.ajax({
            url: url,
            method: 'GET',
            data: ajaxParams
        }).done(function(data) {
            if (helperObject !== undefined) {
                /* skip page for coupon only */
                if (helperObject.skip !== undefined) {
                    helperObject.skip += ajaxParams.take;
                }

                if (helperObject.isProgress !== undefined) {
                    helperObject.isProgress = false;
                }
            }
            if(data.status == 1) {

                skip = skip + take;
                if(data.records.length > 0) {
                    for(var i = 0; i < data.records.length; i++) {
                        ids.push(data.records[i].item_id);
                        var coupon_badge = '',
                            walletIcon = 'fa-plus';
                            walletText = '{{ Lang::get("mobileci.coupon.add_wallet") }}',
                            circleColor = '',
                            couponWallet = '';

                        if(itemtype === 'my-coupon') {
                            if (data.records[i].added_to_wallet === 'true') {
                                walletIcon = 'fa-check';
                                walletText = '{{ Lang::get("mobileci.coupon.added_wallet") }}';
                                circleColor = 'added';
                            }
                        }

                         if (helperObject !== undefined) {
                            if (helperObject.coupon_type !== undefined) {
                                if ('available' === helperObject.coupon_type) {
                                    var connectionChar = /\?/.test(data.records[i].add_to_wallet_hash_url) ? '&' : '?',
                                        couponId = 'idForAddWallet=' + data.records[i].item_id;

                                    // couponWallet = '\
                                    //     <div class="coupon-wallet pull-right">\
                                    //         <a data-href="' + data.records[i].add_to_wallet_hash_url + connectionChar + couponId + '" href="' + data.records[i].add_to_wallet_hash + '">\
                                    //             <span class="fa-stack fa-2x clickable" data-ids="' + data.records[i].item_id + '" data-isaddedtowallet="' + data.records[i].added_to_wallet + '">\
                                    //                 <i class="fa fae-wallet fa-stack-2x"></i>\
                                    //                 <i class="fa ' + circleColor + ' fa-circle fa-stack-2x"></i>\
                                    //                 <i class="fa ' + walletIcon + ' fa-stack-1x state-icon"></i>\
                                    //             </span>\
                                    //         </a>\
                                    //         <span class="wallet-text">' + walletText + '</span>\
                                    //     </div>';

                                    //Vannessa wants to hide add coupon wallet in coupon list
                                    //to enable just uncomment above code and remove following code
                                    couponWallet = '';
                                }
                            }
                        }

                        var list = '<div class="col-xs-12 col-sm-12 item-x" data-ids="' + data.records[i].item_id + '" id="item-' + data.records[i].item_id + '">\
                                <section class="list-item-single-tenant">\
                                    '+ couponWallet +'\
                                    <a class="list-item-link" data-href="'+data.records[i].redirect_url+'" href="'+data.records[i].url+'">\
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

                    $('#load-more-container').remove();
                    if(data.returned_records < data.total_records) {
                        var viewMoreButton = '\
                            <div class="row" id="load-more-container">\
                                <div class="col-xs-12 padded">\
                                    <button class="btn btn-info btn-block" id="load-more-x">{{Lang::get('mobileci.notification.load_more_btn')}}</button>\
                                </div>\
                            </div>';

                        itemList.push(viewMoreButton);
                    }
                    catalogueWrapper.append(itemList.join(''));
                } else {
                    if (helperObject !== undefined) {
                        if (helperObject.coupon_type !== undefined) {
                            var message = "{{ Lang::get('mobileci.greetings.no_coupons_listing') }}";
                            if ('wallet' === helperObject.coupon_type) {
                                message = " {{ Lang::get('mobileci.greetings.no_coupon_wallet_1') }}\
                                            <div class='coupon-wallet-message-icon'>\
                                              <div>\
                                                <span class='fa-stack fa-2x'>\
                                                    <i class='fa fae-wallet fa-stack-2x'></i>\
                                                    <i class='fa fa-circle fa-stack-2x'></i>\
                                                    <i class='fa fa-plus fa-stack-1x state-icon'></i>\
                                                </span>\
                                                <span class=\"wallet-text\">{{ Lang::get("mobileci.coupon.add_wallet") }}</span>\
                                              </div>\
                                            </div>\
                                            {{ Lang::get('mobileci.greetings.no_coupon_wallet_2') }}";
                            }

                            var elementNoCouponWallet = '<div class="col-xs-12 notification-message">\
                                                            <h4>' + message + '</h4>\
                                                         </div>';
                            catalogueWrapper.html(elementNoCouponWallet);
                        }
                    }
                }
            } else {
                if(data.message === 'session_expired') {
                    window.location.replace('/customer');
                }
            }
        }).always(function(data){
            btn.removeAttr('disabled', 'disabled');
            btn.html('{{Lang::get('mobileci.notification.load_more_btn')}}');
            if (callback) {
                callback();
            }
        });
    }
    var notInMessagesPage = true; {{-- this var is used to enable/disable pop up notification --}}
    var tabOpen = false; {{-- this var is for tabs on tenant detail views --}}
    $(document).ready(function(){
        if($(window).width() > $(window).height()) {
            $('.sign-in-popup-wrapper img').css('max-width', '20%');
        } else {
            $('.sign-in-popup-wrapper img').css('max-width', '50%');
        }
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
            return;
        }
        $.fn.removeBlur = function(){
            return;
        }
        function isInArray(value, str) {
            return str.indexOf(value) > -1;
        }
        function viewPopUpActivity(campaign_id, campaign_type) {
            var url = '{{ url('/app/v1/campaign/activities') }}';
            $.ajax({
                url: url,
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
        <?php $displayPopup = Config::get('orbit.shop.display_campaign_popup', function() {return FALSE;}); ?>
        @if (is_callable($displayPopup) && $displayPopup($_GET))
        setTimeout(function(){
            if ($.cookie('dismiss_campaign_cards') !== 't') {
                var url = '{{ url('/app/v1/campaign/list') }}';
                var qm = '?';
                if (url.indexOf('?') > -1) {
                    qm = '&';
                }
                $.ajax({
                    url: url + qm + 'lang='+cookieLang,
                    method: 'GET'
                }).done(function(data) {
                    if(data.data.total_records) {
                        for(var i = 0; i < data.data.records.length; i++) {
                            var list = '<li data-thumb="'+ data.data.records[i].campaign_image +'" data-campaign-id="'+ data.data.records[i].campaign_id +'" data-campaign-type="'+ data.data.records[i].campaign_type +'">\
                                    <img class="img-responsive" src="'+ data.data.records[i].campaign_image +'"/>\
                                    <div class="campaign-cards-info">\
                                        <h4><strong>'+ data.data.records[i].campaign_name +'</strong></h4>\
                                        <p>'+ data.data.records[i].campaign_description +'</p>\
                                        <a class="campaign-cards-link" data-id="'+ data.data.records[i].campaign_id +'" data-type="'+ data.data.records[i].campaign_type +'" data-href="'+ data.data.records[i].redirect_campaign_url +'" href="'+ data.data.records[i].campaign_url +'"><i>{{ Lang::get('mobileci.campaign_cards.go_to_page') }}</i></a>\
                                    </div>\
                                </li>';
                            $('#campaign-cards').append(list);
                        }
                        var autoSliderOption = data.data.records.length > 1 ? true : false;
                        $('body').addClass('freeze-scroll');
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
        @endif

        $('#campaign-cards-close-btn, .campaign-cards-back-drop').click(function(){
            slider.pause();
            $.cookie('dismiss_campaign_cards', 't', {expires: 3650, path: '/'});
            $('body').removeClass('freeze-scroll');
            $('.campaign-cards-back-drop').fadeOut('slow');
            $('.campaign-cards-container').toggle('slide', {direction: 'up'}, 'fast');
        });

        $('body').on('click', '.campaign-cards-link', function(e){
            e.preventDefault();
            var campaign_id = $(this).data('id');
            var campaign_type = $(this).data('type');
            var url = '{{ url('/app/v1/campaign/activities') }}';
            $.ajax({
                url: url,
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

            setTimeout(function(){
                $('#search-type').focus();
            }, 10);
        });
        $('#search-close-btn').click(function(){
            $('.search-container').toggle('slide', {direction: 'down'}, 'slow');
            $('.search-top').toggle('slide', {direction: 'down'}, 'fast');
            $('.search-back-drop').fadeOut('fast');

            $('#search-type').val('');
            $('.search-results').hide();
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

                var url = '{{ url('/app/v1/keyword/search') }}';
                var qm = '?';
                if (url.indexOf('?') > -1) {
                    qm = '&';
                }
                $.ajax({
                    url: url + qm + "keyword=" + keyword + '&lang=' + cookieLang,
                    method: 'GET'
                }).done(function(data) {
                    if (data.data.total_records > 0) {
                        // var show_result = '<div class="search-btn"><a id="show_all_result"><span class="col-xs-8"><strong>{{Lang::get('mobileci.search.show_all_result')}}</strong></span><span class="col-xs-4 text-right"><i class="fa fa-chevron-right"></i></span></a></div>';
                        var show_result = '';
                        var tenants='',promotions='',news='',coupons='',lucky_draws='',services='';
                        if (data.data.grouped_records.tenants.length > 0) {
                            search_results.tenants = data.data.grouped_records.tenants;
                            tenants = '<h4>{{Lang::get('mobileci.page_title.tenant_directory')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.tenants.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                var description = data.data.grouped_records.tenants[i].object_description ? data.data.grouped_records.tenants[i].object_description : '';
                                description = description.indexOf('<br') > 0 ? description.slice(0, description.indexOf('<br')) : description;
                                tenants += '<li class="search-result-group '+ hide +'">\
                                        <a data-href="'+ data.data.grouped_records.tenants[i].object_redirect_url +'" href="'+ data.data.grouped_records.tenants[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.tenants[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.tenants[i].object_name +'</strong></h5>\
                                                <p>'+ description +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.tenants.length > 3) {
                                tenants += '<a data-href="'+ data.data.grouped_records.tenants_redirect_url +'" href="'+ data.data.grouped_records.tenants_url +'" class="text-right" style="display:block;color:#fff;">{{ Lang::get('mobileci.search.show_more') }}</a>';
                            }
                            tenants += '</ul>';
                        }
                        if (data.data.grouped_records.services.length > 0) {

                            search_results.services = data.data.grouped_records.services;
                            services = '<h4>{{Lang::get('mobileci.page_title.service_directory')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.services.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                var description = data.data.grouped_records.services[i].object_description ? data.data.grouped_records.services[i].object_description : '';
                                description = description.indexOf('<br') > 0 ? description.slice(0, description.indexOf('<br')) : description;
                                services += '<li class="search-result-group '+ hide +'">\
                                        <a data-href="'+ data.data.grouped_records.services[i].object_redirect_url +'" href="'+ data.data.grouped_records.services[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.services[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.services[i].object_name +'</strong></h5>\
                                                <p>'+ description +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.services.length > 3) {
                                services += '<a data-href="'+ data.data.grouped_records.services_redirect_url +'" href="'+ data.data.grouped_records.services_url +'" class="text-right" style="display:block;color:#fff;">{{ Lang::get('mobileci.search.show_more') }}</a>';
                            }
                            services += '</ul>';
                        }
                        if (data.data.grouped_records.promotions.length > 0) {
                            search_results.promotions = data.data.grouped_records.promotions;
                            promotions = '<h4>{{Lang::get('mobileci.page_title.promotions')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.promotions.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                var description = data.data.grouped_records.promotions[i].object_description ? data.data.grouped_records.promotions[i].object_description : '';
                                description = description.indexOf('<br') > 0 ? description.slice(0, description.indexOf('<br')) : description;
                                promotions += '<li class="search-result-group '+ hide +'">\
                                        <a data-href="'+ data.data.grouped_records.promotions[i].object_redirect_url +'" href="'+ data.data.grouped_records.promotions[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.promotions[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.promotions[i].object_name +'</strong></h5>\
                                                <p>'+ description +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.promotions.length > 3) {
                                promotions += '<a data-href="'+ data.data.grouped_records.promotions_redirect_url +'" href="'+ data.data.grouped_records.promotions_url +'" class="text-right" style="display:block;color:#fff;">{{ Lang::get('mobileci.search.show_more') }}</a>';
                            }
                            promotions += '</ul>';
                        }
                        if (data.data.grouped_records.news.length > 0) {
                            search_results.news = data.data.grouped_records.news;
                            news = '<h4>{{Lang::get('mobileci.page_title.news')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.news.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                var description = data.data.grouped_records.news[i].object_description ? data.data.grouped_records.news[i].object_description : '';
                                description = description.indexOf('<br') > 0 ? description.slice(0, description.indexOf('<br')) : description;
                                news += '<li class="search-result-group '+ hide +'">\
                                        <a data-href="'+ data.data.grouped_records.news[i].object_redirect_url +'" href="'+ data.data.grouped_records.news[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.news[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.news[i].object_name +'</strong></h5>\
                                                <p>'+ description +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.news.length > 3) {
                                news += '<a data-href="'+ data.data.grouped_records.news_redirect_url +'" href="'+ data.data.grouped_records.news_url +'" class="text-right" style="display:block;color:#fff;">{{ Lang::get('mobileci.search.show_more') }}</a>';
                            }
                            news += '</ul>';
                        }
                        if (data.data.grouped_records.coupons.length > 0) {
                            search_results.coupons = data.data.grouped_records.coupons;
                            coupons = '<h4>{{Lang::get('mobileci.page_title.coupons')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.coupons.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                var description = data.data.grouped_records.coupons[i].object_description ? data.data.grouped_records.coupons[i].object_description : '';
                                description = description.indexOf('<br') > 0 ? description.slice(0, description.indexOf('<br')) : description;
                                coupons += '<li class="search-result-group '+ hide +'">\
                                        <a data-href="'+ data.data.grouped_records.coupons[i].object_redirect_url +'" href="'+ data.data.grouped_records.coupons[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.coupons[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.coupons[i].object_name +'</strong></h5>\
                                                <p>'+ description +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.coupons.length > 3) {
                                coupons += '<a data-href="'+ data.data.grouped_records.coupons_redirect_url +'" href="'+ data.data.grouped_records.coupons_url +'" class="text-right" style="display:block;color:#fff;">{{ Lang::get('mobileci.search.show_more') }}</a>';
                            }
                            coupons += '</ul>';
                        }
                        if (data.data.grouped_records.lucky_draws.length > 0) {
                            search_results.lucky_draws = data.data.grouped_records.lucky_draws;
                            lucky_draws = '<h4>{{Lang::get('mobileci.page_title.lucky_draws')}}</h4><ul>'
                            for(var i = 0; i < data.data.grouped_records.lucky_draws.length; i++) {
                                var hide = i > 2 ? 'limited hide' : '';
                                var description = data.data.grouped_records.lucky_draws[i].object_description ? data.data.grouped_records.lucky_draws[i].object_description : '';
                                description = description.indexOf('<br') > 0 ? description.slice(0, description.indexOf('<br')) : description;
                                lucky_draws += '<li class="search-result-group '+ hide +'">\
                                        <a data-href="'+ data.data.grouped_records.lucky_draws[i].object_redirect_url +'" href="'+ data.data.grouped_records.lucky_draws[i].object_url +'">\
                                            <div class="col-xs-2 text-center">\
                                                <img src="'+ data.data.grouped_records.lucky_draws[i].object_image +'">\
                                            </div>\
                                            <div class="col-xs-10">\
                                                <h5><strong>'+ data.data.grouped_records.lucky_draws[i].object_name +'</strong></h5>\
                                                <p>'+ description +'</p>\
                                            </div>\
                                        </a>\
                                    </li>';
                            }
                            if (data.data.grouped_records.lucky_draws.length > 3) {
                                lucky_draws += '<a data-href="'+ data.data.grouped_records.lucky_draws_redirect_url +'" href="'+ data.data.grouped_records.lucky_draws_url +'" class="text-right" style="display:block;color:#fff;">{{ Lang::get('mobileci.search.show_more') }}</a>';
                            }
                            lucky_draws += '</ul>';
                        }
                        var zonk = '<div style="width:100%;height:160px;background:transparent;">&nbsp;</div>'
                        $('.search-results').html(show_result + tenants + promotions + news + coupons + lucky_draws + services + zonk);
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
            $('.slide-menu-middle-container').css('height', ($(window).height() - $('.header-buttons-container').height()) + 'px');
            if($(window).width() > $(window).height()) {
                $('.sign-in-popup-wrapper img').css('max-width', '20%');
            } else {
                $('.sign-in-popup-wrapper img').css('max-width', '50%');
            }
        });

        $(document).on('click', '.featherlight-close', function(){
            $("body").removeClass("freeze-scroll");
        });

        $(document).on('click', '.featherlight-content, .featherlight-image', function(){
            fl.close();
            $("body").removeClass("freeze-scroll");
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
                $('body').addClass('freeze-scroll');
                $('#orbit-tour-profile').addClass('active');
                $('#slide-trigger').addClass('active');
            } else {
                $('.header-container').css('height', '92px');
                if(!tabOpen){
                    $('body').removeClass('freeze-scroll');
                }
                $('#orbit-tour-profile').removeClass('active');
                $('#slide-trigger').removeClass('active');
            }
        });

        $('body').on('click', 'a[href=#]', function(e) {
            e.preventDefault();
            var default_url = 'ci-customer-home';
            if ($(this).data('href')) {
                default_url = $(this).data('href');
            }
            $('.to_url').val(default_url);
            $('.sign-in-back-drop').fadeIn('fast');
            $('.sign-in-popup').toggle('slide', {direction: 'down'}, 'fast');
        });
        $('body').on('click', '#signin-popup-close-btn', function(){
            $('.sign-in-back-drop').fadeOut('fast');
            $('.sign-in-popup').toggle('slide', {direction: 'down'}, 'fast');
        });
        $('#forgotForm').on('keyup keypress', function(e) {
            // $('#btn-forgot-form').click();
        });
        $('#forgot_password').click(function(){
            orbitSignUpForm.hideErrorMessageBox();
            $('#signin-form-wrapper').addClass('hide');
            $('#forget-form-wrapper').removeClass('hide');
            $('#forgotForm #email_forgot').focus();
        });
        $('#forgot-sign-in-link').click(function(){
            orbitSignUpForm.hideErrorMessageBox();
            $('#signin-form-wrapper').removeClass('hide');
            $('#forget-form-wrapper').addClass('hide');
            $('#signinForm #email').focus();
        });
        $('#forgotForm #email_forgot').on('input', function(e) {
            var value = $(this).val();

            if (isValidEmailAddress(value)) {
                $('#btn-forgot-form').removeAttr('disabled');
            } else {
                $('#btn-forgot-form').attr('disabled', 'disabled');
            }
        });
        $('#btn-forgot-form').click(function() {
            orbitSignUpForm.hideErrorMessageBox();
            var value = $('#email_forgot').val();
            if (isValidEmailAddress(value)) {
                $.ajax({
                    url: '{{route('pub-user-reset-password-link', array('app'))}}',
                    method: 'POST',
                    data:{
                        email : $('#email_forgot').val()
                    }
                }).done(function(data){
                    if (data.status === 'success') {
                        $('#forgotForm').fadeOut('fast');
                        $('#forgotForm').addClass('hide');
                        $('#forget-mail-sent').removeClass('hide');
                        $('#forget-mail-sent').fadeIn('fast');
                        setTimeout(function(){
                            $('#forgotForm').fadeIn('fast');
                            $('#forgotForm').removeClass('hide');
                            $('#forget-mail-sent').addClass('hide');
                            $('#forget-mail-sent').fadeOut('fast');
                        }, 4000);
                    } else {
                        orbitSignUpForm.showErrorMessageBox(data.message);
                    }
                }).fail(function(data){
                    orbitSignUpForm.showErrorMessageBox(JSON.parse(data.responseText).message);
                });
            }
        });
        $('.error-msg-box-close').click(function(){
            $('.error-msg-box').hide();
        });
        $('#formModal').on('show.bs.modal', function () {
            orbitSignUpForm.hideErrorMessageBox();
            $('#signin-form-wrapper').removeClass('hide');
            $('#forget-form-wrapper').addClass('hide');
            $('#signup-form-wrapper').addClass('hide');
            $('#slogan-container, #social-media-wrapper').addClass('hide');
        });

        $('#formModal').on('shown.bs.modal', function () {
            $('#signinForm #email').focus();
            $('#signupForm #firstName').focus();
        });

        $('#formModal').on('hide.bs.modal', function () {
            $('#slogan-container, #social-media-wrapper').removeClass('hide');
        });

        function isValidEmailAddress(emailAddress) {
            var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);

            return pattern.test(emailAddress);
        };

        var orbitSignUpForm = {
            'userActive': false,
            'dataCompleted': false,
            'activeForm': 'signin',
            'formElementsInput': [
                '#firstName',
                '#lastName'
            ],
            'formElementsSelect': [
                '#gender',
                '#signupForm [name=day]',
                '#signupForm [name=month]',
                '#signupForm [name=year]'
            ]
        };

        orbitSignUpForm.hideErrorMessageBox = function () {
            $('.error-msg-box .error-msg-message').text('');
            $('.error-msg-box').hide();
        };
        orbitSignUpForm.showErrorMessageBox = function (message) {
            $('.error-msg-box .error-msg-message').text(message);
            $('.error-msg-box').show();
        };
        /**
         * Log in the user.
         *
         * @author Rio Astamal <rio@dominopos.com>
         * @return void
         */
        orbitSignUpForm.doLogin = function() {
            orbitSignUpForm.hideErrorMessageBox();
            var custEmail = $('#signinForm #email').val().trim();
            var custPassword = $('#signinForm #password').val();
            // Flag the processing
            if (orbitSignUpForm.isProcessing) {
                return;
            }
            orbitSignUpForm.isProcessing = true;
            // Check if this email already registered or not
            // We suppose to not let user login when they are not registered yet
            // which is different from the old Orbit behavior
            var userIdentified = function() {
                $('#btn-signin-form').attr('disabled', 'disabled');
                var url = '{{ url('/app/v1/customer/login') }}';
                $.ajax({
                    method: 'post',
                    url: url,
                    data: {
                        email: custEmail,
                        password: custPassword,
                        mode: 'login',
                        payload: "{{{ Input::get('payload', '') }}}",
                        mac_address: {{ json_encode(Input::get('mac_address', '')) }},
                        auto_login: "{{{ Input::get('auto_login', 'no') }}}",
                        from_captive: "{{{ Input::get('from_captive', 'no') }}}",
                        socmed_redirect_to: "{{{ Input::get('socmed_redirect_to', '') }}}",
                        from_url: $('#from_url').val(),
                        to_url: $('.to_url').val()
                    }
                }).done(function (response, status, xhr) {
                    if (response.code !== 0 && response.code !== 302) {
                        orbitSignUpForm.showErrorMessageBox(response.message);
                        orbitSignUpForm.isProcessing = false;

                        return;
                    }
                    var shiftHostName = window.location.hostname.split('.');
                        shiftHostName.shift();
                    var baseDomain = shiftHostName.join('.');
                    $.cookie('login_from', 'Form', {
                        path: '/',
                        expires: 3650,
                        domain: baseDomain
                    });
                    // redirection?
                    if (response.data.redirect_to) {
                        document.location = response.data.redirect_to;
                        return;
                    }

                    // @Todo: Replace the hardcoded name
                    session_id = xhr.getResponseHeader('Set-X-Orbit-Session');
                    {{-- var landing_url = '{{ $landing_url }}'; --}}
                    var landing_url = '{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-customer-home', [], $session) }}';

                    if (session_id) {
                        if (landing_url.indexOf('orbit_session=') < 0) {
                            // orbit_session= is not exists, append manually
                            if(landing_url.indexOf('?') < 0) {
                                landing_url += '?orbit_session=' + session_id;
                            } else {
                                landing_url += '&orbit_session=' + session_id;
                            }
                        } else {
                            landing_url = landing_url.replace(/orbit_session=(.*)$/, 'orbit_session=' + session_id);
                        }
                    }

                    window.location.replace(landing_url);
                }).fail(function (data) {
                    orbitSignUpForm.isProcessing = false;
                }).always(function() {
                    $('#btn-signin-form').removeAttr('disabled');
                });
            };

            orbitSignUpForm.checkCustomerEmail(custEmail,
                // Send back to sign up form for unknown email
                function() {
                    $('#signupForm #email').val(custEmail);
                    orbitSignUpForm.isProcessing = false;
                    orbitSignUpForm.showErrorMessageBox('{{Lang::get('mobileci.signin.email_not_exist')}}');
                    orbitSignUpForm.switchForm('signup');
                },
                // Proceed the login for identified user
                userIdentified,
                function() {
                    orbitSignUpForm.isProcessing = false;
                    orbitSignUpForm.showErrorMessageBox('Something went wrong, please try again in a few moments.');
                }
            );
        }

       /**
         * Register new user.
         *
         * @author Rio Astamal <rio@dominopos.com>
         * @return void
         */
        orbitSignUpForm.doRegister = function()
        {
            orbitSignUpForm.hideErrorMessageBox();
            var custEmail = $('#signupForm #email').val().trim();
            var custPassword = $('#signupForm #password').val();
            var custPasswordConfirmation = $('#signupForm #password_confirmation').val();

            // Flag the processing
            if (orbitSignUpForm.isProcessing) {
                return;
            }
            orbitSignUpForm.isProcessing = true;

            // Check if this email already registered or not
            // We suppose to not let user login when they are not registered yet
            // which is different from the old Orbit behavior
            var saveUser = function() {
                var birthdate = {
                    'day': $('#signupForm [name=day]').val(),
                    'month': $('#signupForm [name=month]').val(),
                    'year': $('#signupForm [name=year]').val()
                };
                $('#btn-signup-form').attr('disabled', 'disabled');
                var url = '{{ url('/app/v1/customer/login') }}';
                $.ajax({
                    method: 'post',
                    url: url,
                    data: {
                        email: custEmail,
                        payload: "{{{ Input::get('payload', '') }}}",
                        mac_address: {{ json_encode(Input::get('mac_address', '')) }},
                        mode: 'registration',
                        first_name: $('#firstName').val(),
                        last_name: $('#lastName').val(),
                        password: custPassword,
                        password_confirmation: custPasswordConfirmation,
                        gender: $('#gender').val(),
                        birthdate: birthdate.day + '-' + birthdate.month + '-' + birthdate.year,
                        socmed_redirect_to: "{{{ Input::get('socmed_redirect_to', '') }}}",
                        from_url: $('#from_url').val(),
                        to_url: $('.to_url').val()
                    }
                }).done(function (resp, status, xhr) {
                    if (resp.status === 'error') {
                        orbitSignUpForm.showErrorMessageBox(resp.message);
                        orbitSignUpForm.isProcessing = false;
                        return;
                    }

                    // redirection?
                    if (resp.data.redirect_to) {
                        document.location = resp.data.redirect_to;
                        return;
                    }

                    // Todo check the login from captive
                    // the '?from_captive=yes'

                    // @Todo: Replace the hardcoded name
                    session_id = xhr.getResponseHeader('Set-X-Orbit-Session');
                    {{-- var landing_url = '{{ $landing_url }}'; --}}
                    var landing_url = '{{ \Orbit\Helper\Net\UrlChecker::blockedRoute('ci-customer-home', [], $session) }}';

                    if (session_id) {
                        if (landing_url.indexOf('orbit_session=') < 0) {
                            // orbit_session= is not exists, append manually
                            if(landing_url.indexOf('?') < 0) {
                                landing_url += '?orbit_session=' + session_id;
                            } else {
                                landing_url += '&orbit_session=' + session_id;
                            }
                        } else {
                            landing_url = landing_url.replace(/orbit_session=(.*)$/, 'orbit_session=' + session_id);
                        }
                    }

                    window.location.replace(landing_url);
                }).fail(function (data) {
                    orbitSignUpForm.isProcessing = false;

                    // Something bad happens
                    // @todo isplay this the error
                }).always(function() {
                    $('#btn-signup-form').removeAttr('disabled');
                });
            }

            orbitSignUpForm.checkCustomerEmail(custEmail,
                saveUser,

                // Send back to sign in form if it is known user
                function() {
                    $('#signinForm #email').val(custEmail);
                    orbitSignUpForm.isProcessing = false;
                    orbitSignUpForm.showErrorMessageBox('{{Lang::get('mobileci.signup.email_exist')}}');
                },

                function() {
                    orbitSignUpForm.isProcessing = false;
                    orbitSignUpForm.showErrorMessageBox('Something went wrong, please try again in a few moments.');
                }
            );
        }

       /**
         * Disable or enable the sign up and sign in button.
         *
         * @author Rio Astamal <rio@dominopos.com>
         * @return void
         */
        orbitSignUpForm.disableEnableAllButton = function () {
            if (!orbitSignUpForm.isProcessing) {
                $('#spinner-backdrop').addClass('hide');
                return;
            }

            $('#spinner-backdrop').removeClass('hide');
        }

        /**
         * Switch the form between sign up and sign in or toggle in between.
         *
         * @author Rio Astamal <rio@dominopos.com>
         * @param string formName
         * @return void
         */
        orbitSignUpForm.switchForm = function(formName) {
            theForm = formName || 'signin';

            if (theForm === 'signin') {
                $('#signin-form-wrapper').removeClass('hide');
                $('#signup-form-wrapper').addClass('hide');
                $('#signinForm #email').focus();
            } else {
                $('#signin-form-wrapper').addClass('hide');
                $('#signup-form-wrapper').removeClass('hide');
                $('#signupForm #email').focus();
            }
        };

        /**
         * Get the basic data to determine the way we show the form to the user.
         *
         * @author Rio Astamal <rio@dominopos.com>
         * @param string custEmail - Customer email
         * @param callback emptyCallback - Calback called when empty data returned
         * @param callback dataCallback - Callback called when user data is found
         * @param callback errorCallback - Callback called when there is an error
         * @return void|object
         */
        orbitSignUpForm.checkCustomerEmail = function(custEmail, emptyCallback, dataCallback, errorCallback) {
            var url = '{{ url('/app/v1/customer/basic-data') }}';
            $.ajax({
                method: 'POST',
                url: url,
                data: { email: custEmail }
            }).done(function (data, status, xhr) {
                if (data.length === 0) {

                    return emptyCallback();
                }

                return dataCallback(data[0]);
            }).fail(function (xhr, status, exception) {
                return errorCallback();
            });
        };

        /**
         * Show the sign up form since the user is either not active or the profile is not complete.
         *
         * @author Rio Astamal <rio@dominopos.com>
         * @param callback callback - Callback to run after the method finish
         * @param string cssClass - Valid value: 'hide' or 'show'
         * @return void
         */
        orbitSignUpForm.showFullForm = function(callback, cssClass) {
            theClass = cssClass || 'hide';

            if (cssClass !== 'hide') {
                // default value
                theClass = 'show';
            }

            for (var i=0; i<orbitSignUpForm.formElements.length; i++) {
                $(orbitSignUpForm.formElements[i]).removeClass(theClass);
            }

            // run the callback
            callback();
        }

        /**
         * Enable or disable the Sign up button depend on the completeness of the form.
         *
         * @author Rio Astamal <rio@dominopos.com>
         * @return void
         */
        orbitSignUpForm.enableDisableSignup = function() {
            $('#signupForm #email, #signupForm [name=password], #signupForm [name=password_confirmation], #firstName, #lastName, #gender, #signupForm [name=day], #signupForm [name=month], #signupForm [name=year]').css('border-color', '#ccc');
            $('.form-icon').removeClass('has-error');
            $('.form-icon').removeClass('has-success');
            $('.mandatory-label').hide();
            orbitSignUpForm.dataCompleted = $('#signupForm #email').val() &&
                isValidEmailAddress($('#signupForm #email').val()) &&
                $('#firstName').val() &&
                $('#lastName').val() &&
                $('#signupForm #password').val() &&
                $('#password_confirmation').val() &&
                $('#gender').val() &&
                $('#signupForm [name=day]').val() &&
                $('#signupForm [name=month]').val() &&
                $('#signupForm [name=year]').val();

            if (orbitSignUpForm.dataCompleted) {
                // $('#btn-signup-form').removeAttr('disabled');
                return true;
            } else {
                $('.mandatory-label').css('color', 'red').show();
                if (! $('#signupForm #email').val() || ! isValidEmailAddress($('#signupForm #email').val())) {
                    $('#signupForm #email').css('border-color', 'red');
                    $('#signupForm [name=email]').next('.form-icon').addClass('has-error');
                } else {
                    $('#signupForm [name=email]').next('.form-icon').addClass('has-success');
                }
                if (! $('#signupForm #password').val()) {
                    $('#signupForm [name=password]').css('border-color', 'red');
                    $('#signupForm [name=password]').next('.form-icon').addClass('has-error');
                } else {
                    $('#signupForm [name=password]').next('.form-icon').addClass('has-success');
                }
                if (! $('#password_confirmation').val()) {
                    $('#password_confirmation').css('border-color', 'red');
                    $('#password_confirmation').next('.form-icon').addClass('has-error');
                } else {
                    $('#password_confirmation').next('.form-icon').addClass('has-success');
                }
                if (! $('#firstName').val()) {
                    $('#firstName').css('border-color', 'red');
                    $('#firstName').next('.form-icon').addClass('has-error');
                } else {
                    $('#firstName').next('.form-icon').addClass('has-success');
                }
                if (! $('#lastName').val()) {
                    $('#lastName').css('border-color', 'red');
                    $('#lastName').next('.form-icon').addClass('has-error');
                } else {
                    $('#lastName').next('.form-icon').addClass('has-success');
                }
                if (! $('#gender').val()) {
                    $('#gender').css('border-color', 'red');
                    $('#gender').next('.form-icon').addClass('has-error');
                } else {
                    $('#gender').next('.form-icon').addClass('has-success');
                }
                if (! $('#signupForm [name=day]').val()) {
                    $('#signupForm [name=day]').css('border-color', 'red');
                    $('#signupForm [name=day]').next('.form-icon').addClass('has-error');
                } else {
                    $('#signupForm [name=day]').next('.form-icon').addClass('has-success');
                }
                if (! $('#signupForm [name=month]').val()) {
                    $('#signupForm [name=month]').css('border-color', 'red');
                    $('#signupForm [name=month]').next('.form-icon').addClass('has-error');
                } else {
                    $('#signupForm [name=month]').next('.form-icon').addClass('has-success');
                }
                if (! $('#signupForm [name=year]').val()) {
                    $('#signupForm [name=year]').css('border-color', 'red');
                    $('#signupForm [name=year]').next('.form-icon').addClass('has-error');
                } else {
                    $('#signupForm [name=year]').next('.form-icon').addClass('has-success');
                }
                // $('#btn-signup-form').attr('disabled', 'disabled');
                return false;
            }
        }

        var errorValidationFn = function () {
            var errorMessage = '{{isset($error) ? $error : 'No Error'}}';
            if (errorMessage !== 'No Error') {
                toastr(errorMessage);
                $('#spinner-backdrop').addClass('hide');
            }
        },
        inProgressFn = function () {
            var progressStatus = {{isset($isInProgress) ? $isInProgress : 'false'}};
            if (progressStatus === true) {
                $('#spinner-backdrop').removeClass('hide');
                return;
            }
            $('#spinner-backdrop').addClass('hide');
        },
        isSignedInFn = function () {
            var displayName = '{{isset($display_name) ? $display_name : ''}}',
                userEmail = '{{isset($user_email) ? $user_email : ''}}';

            if (displayName === '' && userEmail === '') {
                $('.logged-in-user').addClass('hide');
                $('.logged-in-container').addClass('hide');

                $('.social-media-container').removeClass('hide');
                return;
            }

            $('.logged-in-user').removeClass('hide');
            $('.logged-in-container').removeClass('hide');

            $('.social-media-container').addClass('hide');
        },
        isFromCaptiveFn = function () {
            if ('{{{ Input::get('from_captive', 'no') }}}' === 'yes') {
                $('#social-media-wrapper').addClass('hide');
            }
        };

        orbitSignUpForm.boot = function() {
            inProgressFn();
            isFromCaptiveFn();
            errorValidationFn();

            $('#signupForm #email').keyup(function(e) {
                var value = $(this).val();

                if (isValidEmailAddress(value)) {

                }
            });

            $('#signinForm #email').on('input', function(e) {
                var value = $(this).val();
            });

            $('#logged-in-signin-button').click(function() {
                var loginFrom = '{{isset($_COOKIE['login_from']) ? $_COOKIE['login_from'] : 'Form'}}';

                switch (loginFrom) {
                    case 'Form':
                        orbitSignUpForm.doLogin();
                        break;
                    case 'Facebook':
                        $('#fbLoginButton').click();
                        break;
                    case 'Google':
                        $('#googleLoginButton').click();
                        break;
                }
            });

            $('#not-me').click(function () {
                var currentDomain = orbitGetDomainName();
                $.removeCookie('orbit_email', {path: '/', domain: currentDomain});
                $.removeCookie('orbit_firstname', {path: '/', domain: currentDomain});
                window.location.replace('/customer/logout?not_me=true');
            });

            $('#btn-signin-form').click(function(e) {
                $('#signinForm #email, #signinForm #password').css('border-color', '#ccc')
                var value = $('#signinForm #email').val();
                if (! value || ! isValidEmailAddress(value)) {
                    $('#signinForm #email').css('border-color', 'red');
                }
                if (! $('#signinForm #password').val()) {
                    $('#signinForm #password').css('border-color', 'red');
                }
                if ((value || isValidEmailAddress(value)) && $('#signinForm #password').val()) {
                    orbitSignUpForm.doLogin();
                }
                return false;
            });

            $('#btn-signup-form').click(function(e) {
                if(orbitSignUpForm.enableDisableSignup()) {
                    orbitSignUpForm.doRegister();
                }
                return false;
            });

            $('#signinForm, #signupForm, #forgotForm').submit(function(e) {
                e.preventDefault();
            });

            $('#sign-up-link').click(function(e) {
                orbitSignUpForm.switchForm('signup');
            });

            $('#sign-in-link').click(function(e) {
                orbitSignUpForm.switchForm('signin');
            });

            if (isValidEmailAddress( $('#signinForm #email').val() )) {
                $('#btn-signin-form').removeAttr('disabled');
            }
        }

        orbitSignUpForm.boot();
    });
</script>
