<!doctype html>
<html>
    <head>
        @include('mobile-ci.head')
        {{ HTML::style('mobile-ci/stylesheet/jquery-ui.min.css') }}
        {{ HTML::style('mobile-ci/stylesheet/lightslider.min.css') }}
        @if (! empty(Config::get('orbit.cdn.fonts.ubuntu')))
        <link href="{{Config::get('orbit.cdn.fonts.ubuntu')}}" rel="stylesheet" type="text/css">
        @else 
        <style type="text/css">
            @font-face {
              font-family: 'Ubuntu';
              font-style: normal;
              font-weight: 400;
              src: url("{{asset('mobile-ci/fonts/ubuntu-latin.woff2')}}") format('woff2'), url("{{asset('mobile-ci/fonts/ubuntu-latin.ttf')}}") format('truetype');
              unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2212, U+2215, U+E0FF, U+EFFD, U+F000;
            }
        </style>
        @endif
        @yield('ext_style')
    </head>
    <body>
        @include('mobile-ci.toolbar')
        <div class="headed-layout content-container" @if(is_null($page_title)) style="padding-top:3.1em;"  @endif>
            @yield('content')
        </div>
        @include('mobile-ci.footer')
        @yield('modals')
        <script type="text/javascript">
            var orbitIsViewing = false;
        </script>
        @include('mobile-ci.commonscripts')
        @include('mobile-ci.push-notification-script')
        @yield('ext_script_bot')
        {{-- @include('mobile-ci.orbit-tour') --}}

    </body>
</html>
