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
        @yield('ext_script_bot')
        @include('mobile-ci.commonscripts')
    </body>
</html>