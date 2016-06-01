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
        @if (! empty(Config::get('orbit.cdn.fonts.font_awesome.4_5_0')))
        <style type="text/css">
            @font-face {
              font-family: 'FontAwesome';
              src: url('{{Config::get('orbit.cdn.fonts.font_awesome.4_5_0')}}.eot?v=4.5.0');
              src: url('{{Config::get('orbit.cdn.fonts.font_awesome.4_5_0')}}.eot?#iefix&v=4.5.0') format('embedded-opentype'),
              url('{{Config::get('orbit.cdn.fonts.font_awesome.4_5_0')}}.woff?v=4.5.0') format('woff'),
              url('{{Config::get('orbit.cdn.fonts.font_awesome.4_5_0')}}.ttf?v=4.5.0') format('truetype'),
              url('{{Config::get('orbit.cdn.fonts.font_awesome.4_5_0')}}.svg?v=4.5.0#fontawesomeregular') format('svg');
              font-weight: normal;
              font-style: normal;
            }
        </style>
        @else
        <style type="text/css">
            @font-face {
              font-family: 'FontAwesome';
              src: url('{{asset('mobile-ci/fonts/font-awesome/fontawesome-webfont.eot?v=4.5.0')}}');
              src: url('{{asset('mobile-ci/fonts/font-awesome/fontawesome-webfont.eot?#iefix&v=4.5.0')}}') format('embedded-opentype'),
              url('{{asset('mobile-ci/fonts/font-awesome/fontawesome-webfont.woff?v=4.5.0')}}') format('woff'),
              url('{{asset('mobile-ci/fonts/font-awesome/fontawesome-webfont.ttf?v=4.5.0')}}') format('truetype'),
              url('{{asset('mobile-ci/fonts/font-awesome/fontawesome-webfont.svg?v=4.5.0#fontawesomeregular')}}') format('svg');
              font-weight: normal;
              font-style: normal;
            }
        </style>
        @endif
        @if(! empty(Config::get('orbit.cdn.fonts.glyphicon')))
        <style type="text/css">
            @font-face {
              font-family: 'Glyphicons Halflings';
              src: url('{{Config::get('orbit.cdn.fonts.glyphicon')}}.eot');
              src: url('{{Config::get('orbit.cdn.fonts.glyphicon')}}.eot?#iefix') format('embedded-opentype'),
                   url('{{Config::get('orbit.cdn.fonts.glyphicon')}}.woff') format('woff'),
                   url('{{Config::get('orbit.cdn.fonts.glyphicon')}}.ttf') format('truetype'),
                   url('{{Config::get('orbit.cdn.fonts.glyphicon')}}.svg#glyphicons_halflingsregular') format('svg');
            }
        </style>
        @else
        <style type="text/css">
            @font-face {
              font-family: 'Glyphicons Halflings';
              src: url('{{asset('mobile-ci/fonts/glyphicons-halflings-regular.eot')}}');
              src: url('{{asset('mobile-ci/fonts/glyphicons-halflings-regular.eot?#iefix')}}') format('embedded-opentype'),
                   url('{{asset('mobile-ci/fonts/glyphicons-halflings-regular.woff')}}') format('woff'),
                   url('{{asset('mobile-ci/fonts/glyphicons-halflings-regular.ttf')}}') format('truetype'),
                   url('{{asset('mobile-ci/fonts/glyphicons-halflings-regular.svg#glyphicons_halflingsregular')}}') format('svg');
            }
        </style>
        @endif
        @yield('ext_style')
    </head>
    <body>
        @yield('fb_scripts')

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
        @yield('ext_script_bot')
        @include('mobile-ci.push-notification-script')
        {{-- @include('mobile-ci.orbit-tour') --}}
        @include('mobile-ci.google-analytics')
    </body>
</html>
