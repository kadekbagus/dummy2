@extends('mobile-ci.layout')

@section('ext_style')
    {{ HTML::style('mobile-ci/stylesheet/featherlight.min.css') }}
    <style>
        .clipboard-success {
            width:200px;
            height:20px;
            height:auto;
            position:absolute;
            left:50%;
            margin-left:-100px;
            bottom:10px;
            background-color: #383838;
            color: #F0F0F0;
            font-family: Calibri;
            padding:10px;
            text-align:center;
            border-radius: 2px;
            -webkit-box-shadow: 0px 0px 24px -1px rgba(56, 56, 56, 1);
            -moz-box-shadow: 0px 0px 24px -1px rgba(56, 56, 56, 1);
            box-shadow: 0px 0px 24px -1px rgba(56, 56, 56, 1);
        }
    </style>
@stop

@section('content')
    <script>
    /**
     * Read cookie value by name
     */
    var getCookie = function(cookieName) {
        var name = cookieName + "=";
        var ca = document.cookie.split(';');
        for(var i = 0; i <ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0)==' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length,c.length);
            }
        }
        return '';
    };

    var OrbitInternetChecker = {};

    /**
     * Callback to handle if internet is connected from
     * captive portal
     */
    OrbitInternetChecker.connectedFromCaptivePortal = function()
    {
        $('#captive-check-internet').addClass('hide');

        // The user is already connected to the internet, no need to grant.
        // Redirect to our granted page.
        window.location.href = '{{ $granted_url }}';
    };

    /**
     * Callback to handle if internet is connected from
     * any non mall captive portal (i.e 3G, any Wifi etc).
     *
     * We display Free Internet Access dialog but with
     * Get Free Internet Access button disabled
     * to indicate that they cannot get free internet access if they are not
     * connected to mall captive portal.
     */
    OrbitInternetChecker.connectedFromNonCaptivePortal = function()
    {
        $('#captive-check-internet').addClass('hide');
        $('#captive-no-internet').removeClass('hide');
        $('#btn-grant-internet').attr('disabled', 'disabled');
        $('#connection-status').attr('src','{{ asset('mobile-ci/images/free-internet-disconnected.png') }}')
    };

    /**
     * Callback to handle if internet is up
     */
    OrbitInternetChecker.up = function()
    {
        var fromCaptivePortal = getCookie('from_captive');
        if (fromCaptivePortal === 'yes') {
            this.connectedFromCaptivePortal();
        } else {
            this.connectedFromNonCaptivePortal();
        }
    };

    /**
     * Callback to handle if internet is down (image not found)
     */
    OrbitInternetChecker.down = function()
    {
        setTimeout(function() {
            $('#captive-check-internet').addClass('hide');
            $('#captive-no-internet').removeClass('hide');
        }, {{ $timeout }} * 1000);
    };

    /**
     * Callback to handle form submission when Free internet button
     * get clicked.
     */
    OrbitInternetChecker.submit = function(el) {
        el.value = 'Please wait...';
        el.disabled = 'disabled';

        setTimeout(function() {
            document.getElementById('frm-grant-internet').submit();
        }, 1000);

        return false;
    }
    </script>

    <!-- show when browser run in OS !== Android 5+ -->
    <div class="row padded" id="in-any-os" style="display:none">
        <div class="col-xs-12 hide" id="captive-no-internet">
            <h3>{{ Lang::get('mobileci.captive.request_internet.heading') }}</h3>
            <img id="connection-status" style="width:100px;float:left;margin: 0 1em 1em 0; position:relative; top:-1em;" src="{{ asset('mobile-ci/images/free-internet-disconnected.png') }}">
            <p>{{ Lang::get('mobileci.captive.request_internet.message') }}
            <form id="frm-grant-internet" method="get" action="{{ $base_grant_url }}">
                <input onclick="return OrbitInternetChecker.submit(this)" id="btn-grant-internet" type="submit" class="btn btn-block btn-primary" value="{{ Lang::get('mobileci.captive.request_internet.button') }}">
                @foreach ($params as $name=>$value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            </form>
        </div>

        <div class="col-xs-12" id="captive-check-internet">
            <h4>{{ Lang::get('mobileci.captive.request_internet.check_connection') }}</h4>
            <p>{{ Lang::get('mobileci.captive.request_internet.too_long') }}</p>
        </div>
        {{-- Add some random string to force browser to not cache this image otherwise we will get false detection --}}
        <img id="pingdom-icon" class="hide" src="{{ $ping_url.'?rnd='.str_random(10) }}" onerror="OrbitInternetChecker.down()" onload="OrbitInternetChecker.up()">
    </div>

    <!-- show when browser run in OS === Android 5+ -->
    <div class="row padded" id="in-android-5-or-newer"  style="display:none">
        <div class="col-xs-12" id="workaround-captive-no-internet">
           <h3>{{ Lang::get('mobileci.captive.request_internet.message_ex.title') }}</h3>
           <img style="width:100px;float:left;margin: 0 1em 1em 0; position:relative; top:-1em;" src="{{ asset('mobile-ci/images/free-internet-disconnected.png') }}">
           <p>{{ Lang::get('mobileci.captive.request_internet.message_ex.instruction_heading') }}</p>
           <ul>
               @foreach(Lang::get('mobileci.captive.request_internet.message_ex.instructions') as $instruction)
               <li>{{ $instruction }}</li>
               @endforeach
           </ul>
           {{------------------------------------------------------------
               We include url_from_clipboard=yes into query string
               to indicate that user is browsing from copied URL.
               See ExCaptivePortalController.getECaptiveRequestInternet()
               method
            -------------------------------------------------------------}}
           <button id="copy-url" class="btn btn-block btn-primary" data-clipboard-text="{{ URL::route('captive-request-internet', ['url_from_clipboard' => 'yes', $qs_name => $qs_value]) }}">{{ Lang::get('mobileci.captive.request_internet.message_ex.clipboard_caption') }}</button>
           <br/>
            <form id="frm-grant-internet" method="get" action="{{ $base_grant_url }}">
                <input onclick="return OrbitInternetChecker.submit(this)" id="btn-grant-internet" type="submit" class="btn btn-block btn-primary" value="{{ Lang::get('mobileci.captive.request_internet.button') }}">
                @foreach ($params as $name=>$value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            </form>
           <div class='clipboard-success' style='display:none'>{{ Lang::get('mobileci.captive.request_internet.message_ex.clipboard_success') }}</div>
        </div>
    </div>

@stop

@section('ext_script_bot')
    {{-----------------------------------------------------------
      Note config 'orbit.cdn.modernizr' is not defined.
      we do not include CDN for Modernizr config in orbit.php
      because we only need subset of its functionalities
    --------------------------------------------------------------}}
    {{ HTML::script(Config::get('orbit.cdn.modernizr', 'mobile-ci/scripts/modernizr-custom.min.js')) }}
    {{-- Script fallback --}}
    <script>
        if ((typeof Modernizr === 'undefined')) {
          document.write('<script src="{{asset('mobile-ci/scripts/modernizr-custom.min.js')}}">\x3C/script>');
        }
    </script>
    {{-- End of Script fallback --}}

    {{ HTML::script(Config::get('orbit.cdn.clipboard.1_5_10', 'mobile-ci/scripts/clipboard.min.js')) }}
    {{-- Script fallback --}}
    <script>
        if ((typeof Modernizr === 'undefined')) {
          document.write('<script src="{{asset('mobile-ci/scripts/clipboard.min.js')}}">\x3C/script>');
        }
    </script>
    {{-- End of Script fallback --}}

    <script type="text/javascript">
        var AndroidCaptivePortalBrowserDetector = {};
        /**---------------------------------------------------------------
         * Detect if current browser is captive portal browser in Android.
         * ---------------------------------------------------------------
         * Note:
         * Captive portal browser in Android is application that responsible
         * for handle captive portal login. It is implemented in activity
         * named CaptivePortalLoginActivity.java         *
         * This activity using WebView component with minimum functionalities
         * i.e only enable Javascript but not advanced feature such as HTML5 Local Storage
         * functionality. So we check availability of LocalStorage functionality
         *
         * User-Agent for default WebView is as explained in
         * https://developer.chrome.com/multidevice/user-agent#webview_user_agent
         *
         * wv string in user-agent only available in WebView since Android 5+,
         * lower version Android will not have this string
         *
         * This detection however have weakness. For example, someone can build
         * Android application using default WebView. Then this script will result
         * in false detection.
         */
        AndroidCaptivePortalBrowserDetector.isAndroidCaptivePortal = function() {
            var ua = navigator.userAgent;
            return  (!Modernizr.localstorage) &&
                    (ua.indexOf('Android') > -1) &&
                    (ua.indexOf('Chrome/') > -1) &&
                    (ua.indexOf('wv)') > -1);
        };


        if (AndroidCaptivePortalBrowserDetector.isAndroidCaptivePortal() === true) {
            //assume that we are accessed from captive portal of Android 5+
            //we provide different way to grant user internet access
            $('#in-any-os').hide();
            $('#in-android-5-or-newer').show();
        } else {
            $('#in-any-os').show();
            $('#in-android-5-or-newer').hide();
        }

        var clipboard = new Clipboard('#copy-url');
        clipboard.on('success', function(e) {
            $('.clipboard-success').fadeIn(400).delay(3000).fadeOut(400);
        });

        var WifiCookieChecker = function() {
            this.updateUI = function() {
                if (getCookie('from_captive') === 'yes') {
                    $('#btn-grant-internet').removeAttr('disabled');
                    $('#connection-status').attr('src','{{ asset('mobile-ci/images/free-internet-connected.png') }}')
                } else {
                    $('#btn-grant-internet').attr('disabled', 'disabled');
                    $('#connection-status').attr('src','{{ asset('mobile-ci/images/free-internet-disconnected.png') }}')
                }
            }
        }

        var wifiChecker = new WifiCookieChecker();

        window.setInterval(function() {
            wifiChecker.updateUI();
        }, 30000);

    </script>
@stop