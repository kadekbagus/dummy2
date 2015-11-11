<title>{{ $this_mall->name }} Orbit</title>
<meta charset="utf-8" />
<meta name="description" content="Orbit app mobile customer interface" />
<meta name="author" content="DominoPOS" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<meta name="format-detection" content="telephone=no">
<meta name="mobile-web-app-capable" content="yes">
@if(! empty($this_mall->mediaIcon))
<link rel="apple-touch-icon-precomposed" sizes="57x57" href="{{ asset($this_mall->mediaIcon->path) }}" />
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="{{ asset($this_mall->mediaIcon->path) }}" />
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="{{ asset($this_mall->mediaIcon->path) }}" />
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="{{ asset($this_mall->mediaIcon->path) }}" />
<link rel="icon" type="image/ico" href="{{ asset($this_mall->mediaIcon->path) }}"/>
@else
<link rel="apple-touch-icon-precomposed" sizes="57x57" href="{{ asset('mobile-ci/images/orbit-icon-default.png') }}" />
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="{{ asset('mobile-ci/images/orbit-icon-default.png') }}" />
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="{{ asset('mobile-ci/images/orbit-icon-default.png') }}" />
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="{{ asset('mobile-ci/images/orbit-icon-default.png') }}" />
<link rel="icon" type="image/ico" href="{{ asset('mobile-ci/images/orbit-icon-default.png') }}"/>
@endif

{{ HTML::style('mobile-ci/stylesheet/bootstrap-tour.min.css') }}
{{ HTML::style('mobile-ci/stylesheet/' . Orbit\Helper\Asset\Stylesheet::create()->getMallCss()) }}
{{ HTML::style('mobile-ci/stylesheet/responsiveslides.css') }}
{{ HTML::script('mobile-ci/scripts/jquery-2.1.1.min.js') }}
{{ HTML::script('mobile-ci/scripts/bootstrap.min.js') }}
{{ HTML::script('mobile-ci/scripts/bootstrap-tour.js') }}
{{ HTML::script('mobile-ci/scripts/config.js') }}
