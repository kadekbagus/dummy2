<!doctype html>
<html>
    <head>
        @include('mobile-ci.head')
        @yield('ext_style')
        
    </head>
    <body>
        <div class="spinner-backdrop hide" id="spinner-backdrop">
            <div class="spinner-container">
                <i class="fa fa-spin fa-spinner"></i>
            </div>
        </div>
        @include('mobile-ci.content-signIn')
        @include('mobile-ci.sticky-footer')
        @yield('ext_script_bot')
    </body>
</html>