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
    </head>
    <body>
        <h1>{{{$data->title}}}</h1>
        <p>{{{$data->description}}}</p>
        @if(! empty($data->imageUrl))
        <img src="{{asset($data->imageUrl)}}">
        @endif
    </body>
</html>