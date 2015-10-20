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
        <footer>
            <div class="text-center">
                {{ 'Orbit v' . ORBIT_APP_VERSION }}
            </div>
        </footer>
        @yield('modals')
        <script type="text/javascript">
            var orbitIsViewing = true;
        </script>
        @yield('ext_script_bot')
        @include('mobile-ci.commonscripts')
        @include('mobile-ci.push-notification-script')
        {{-- @include('mobile-ci.orbit-tour') --}}

    </body>
</html>
