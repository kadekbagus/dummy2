<!doctype html>
<html>
    <head>
        @include('mobile-ci.head')
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
        @yield('ext_script_bot')
        @include('mobile-ci.commonscripts')
        @include('mobile-ci.push-notification-script')
        {{-- @include('mobile-ci.orbit-tour') --}}

    </body>
</html>
