<title>{{{ $this_mall->name }}}</title>
<meta charset="utf-8" />
<meta name="description" content="Orbit app mobile customer interface" />
<meta name="author" content="DominoPOS" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<meta name="format-detection" content="telephone=no">
<meta name="mobile-web-app-capable" content="yes">
<!-- @if(! empty($this_mall->mediaIcon))
<link rel="apple-touch-icon-precomposed" sizes="57x57" href="{{ asset($this_mall->mediaIcon->path) }}" />
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="{{ asset($this_mall->mediaIcon->path) }}" />
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="{{ asset($this_mall->mediaIcon->path) }}" />
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="{{ asset($this_mall->mediaIcon->path) }}" />
<link rel="icon" type="image/ico" href="{{ asset($this_mall->mediaIcon->path) }}"/>
@else -->
<link rel="apple-touch-icon-precomposed" sizes="57x57" href="{{ asset('mobile-ci/images/gotomalls.ico') }}" />
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="{{ asset('mobile-ci/images/gotomalls.ico') }}" />
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="{{ asset('mobile-ci/images/gotomalls.ico') }}" />
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="{{ asset('mobile-ci/images/gotomalls.ico') }}" />
<link rel="icon" type="image/ico" href="{{ asset('mobile-ci/images/gotomalls.ico') }}"/>
<!-- @endif -->

{{-- HTML::style('mobile-ci/stylesheet/bootstrap-tour.min.css') --}}
{{ HTML::style('mobile-ci/stylesheet/' . Orbit\Helper\Asset\Stylesheet::create()->getMallCss()) }}
{{-- HTML::style('mobile-ci/vendor/toastr/toastr.min.css') --}}
{{ HTML::script(Config::get('orbit.cdn.jquery.2_1_1', 'mobile-ci/scripts/jquery-2.1.1.min.js')) }}
{{-- Script fallback --}}
<script>window.jQuery || document.write('<script src="{{asset('mobile-ci/scripts/jquery-2.1.1.min.js')}}">\x3C/script>')</script>
{{-- End of Script fallback --}}

{{ HTML::script(Config::get('orbit.cdn.bootstrap.3_3_1', 'mobile-ci/scripts/bootstrap.min.js')) }}
{{-- Script fallback --}}
<script>
    if (typeof $().emulateTransitionEnd === 'undefined') {
        document.write('<script src="{{asset('mobile-ci/scripts/bootstrap.min.js')}}">\x3C/script>');
    }
</script>
{{-- End of Script fallback --}}

{{-- HTML::script('mobile-ci/scripts/bootstrap-tour.min.js') --}}
{{ HTML::script('mobile-ci/scripts/config.js') }}
{{ HTML::script('mobile-ci/vendor/toastr/toastr.min.js') }}
