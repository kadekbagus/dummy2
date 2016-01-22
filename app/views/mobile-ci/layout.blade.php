<!doctype html>
<html>
    <head>
        @include('mobile-ci.head')
        {{ HTML::style('mobile-ci/stylesheet/jquery-ui.min.css') }}
        {{ HTML::style('mobile-ci/stylesheet/lightslider.min.css') }}
        <link href='https://fonts.googleapis.com/css?family=Ubuntu:400,700' rel='stylesheet' type='text/css'>
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
