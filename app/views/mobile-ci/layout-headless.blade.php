<!doctype html>
<html>
    <head>
        @include('mobile-ci.head')
        @yield('ext_style')
    </head>
    <body class="bg">
        <div class="container">
            @yield('content')
        </div>
        @yield('footer')
        @yield('modals')
        @yield('ext_script_bot')
        @include('mobile-ci.commonscripts')
    </body>
</html>
