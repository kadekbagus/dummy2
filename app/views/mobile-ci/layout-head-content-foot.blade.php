<!doctype html>
<html>
    <head>
        @include('mobile-ci.head')
        @yield('ext_style')
        
    </head>
    <body>
        @include('mobile-ci.content-signIn')
        @include('mobile-ci.sticky-footer')
        @yield('ext_script_bot')
    </body>
</html>