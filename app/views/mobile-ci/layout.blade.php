<!doctype html>
<html>
    <head>
        @include('mobile-ci.head')
        @yield('ext_style')
    </head>
    <body>
        @include('mobile-ci.toolbar')
        <div class="headed-layout content-container">
            @yield('content')
        </div>
        @include('mobile-ci.footer')
        @yield('modals')
        <script type="text/javascript">
            var orbitIsViewing = false;
        </script>
        @include('mobile-ci.commonscripts')
        @yield('ext_script_bot')
        @include('mobile-ci.push-notification-script')
        {{-- @include('mobile-ci.orbit-tour') --}}

    </body>
</html>
