<!doctype html>
<html>
    <head>
        <title>{{{$data->title}}}</title>
        <meta property="og:url"           content="{{{$data->url}}}" />
        <meta property="og:type"          content="website" />
        <meta property="og:title"         content="{{{$data->title}}}" />
        <meta property="og:description"   content="{{{$data->description}}}" />
        @if(! empty($data->imageUrl))
        <meta property="og:image"         content="{{asset($data->imageUrl)}}" />
        @endif
        @if(! empty($data->imageUrl))
        @if(! empty($data->imageDimension))
        <meta property="og:image:width"   content="{{$data->imageDimension[0]}}" />
        <meta property="og:image:height"  content="{{$data->imageDimension[1]}}" />
        @endif
        @endif
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:description" content="{{{$data->description}}}">
        <meta name="twitter:title" content="{{{$data->title}}}">
        <meta name="twitter:site" content="@gotomalls">
        <meta name="twitter:domain" content="Gotomalls">
        @if(! empty($data->imageUrl))
        <meta name="twitter:image:src" content="{{asset($data->imageUrl)}}">
        @endif
        <meta name="twitter:creator" content="@gotomalls">
    </head>
    <body>
        <h1>{{{$data->title}}}</h1>
        <p>{{{$data->description}}}</p>
        @if(! empty($data->imageUrl))
        <img src="{{asset($data->imageUrl)}}">
        @endif
    </body>
</html>