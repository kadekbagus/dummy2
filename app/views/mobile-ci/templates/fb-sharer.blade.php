<!doctype html>
<html>
    <head>
    	<title>{{$data->mall->name}} - {{$data->title}}</title>
    	<meta property="og:url"           content="{{$data->url}}" />
		<meta property="og:type"          content="website" />
		<meta property="og:title"         content="{{$data->title}}" />
		<meta property="og:description"   content="{{$data->description}}" />
		<meta property="og:image"         content="{{is_null($data->image_url) ? '' : asset($data->image_url)}}" />
		@if(! empty($data->image_dimension))
		<meta property="og:image:width"   content="{{$data->image_dimension[0]}}" />
		<meta property="og:image:height"  content="{{$data->image_dimension[1]}}" />
		@endif
    </head>
    <body>
    </body>
</html>