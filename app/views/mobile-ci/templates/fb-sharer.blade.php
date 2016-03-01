<!doctype html>
<html>
    <head>
    	<title>{{$data->mall->name}} - {{$data->title}}</title>
    	<meta property="og:url"           content="{{$data->url}}" />
		<meta property="og:type"          content="website" />
		<meta property="og:title"         content="{{$data->title}}" />
		<meta property="og:description"   content="{{$data->description}}" />
		<meta property="og:image"         content="{{is_null($data->image_url) ? '' : asset($data->image_url)}}" />
    </head>
    <body>
    </body>
</html>