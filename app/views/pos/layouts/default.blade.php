<!doctype html>
<html lang="en">
<head>
    <title>Orbit POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

   {{--css--}}
    <link rel="stylesheet" href=" {{ URL::asset('templatepos/css/main.css') }} ">
    <link rel="stylesheet" href=" {{ URL::asset('templatepos/css/keypad-numeric.css') }} ">
    <link rel="stylesheet" href="{{ URL::asset('templatepos/vendor/font-awesome-4.2.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ URL::asset('templatepos/vendor/progressjs/progressjs.css') }}">
    <link rel="shortcut icon" href="{{ URL::asset('templatepos/images/favicon.ico') }}">
    <link href='http://fonts.googleapis.com/css?family=Lato:300,400,700,300italic,400italic' rel='stylesheet' type='text/css'> 
   {{--js--}}

    <script src="{{ URL::asset('templatepos/vendor/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular/angular.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular-animate/angular-animate.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular-local-storage/dist/angular-local-storage.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular-ui-bootstrap/ui-bootstrap-tpls-0.12.0.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/angular/angular-touch.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/accounting/accounting.min.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/moment/moment.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/progressjs/progress.js') }}"></script>
    <script src="{{ URL::asset('templatepos/vendor/bootstrap/bootstrap.min.js') }}"></script>

    <script  data-main="{{ URL::asset('templatepos/js/main.js') }}" src="{{ URL::asset('templatepos/vendor/require/require.js') }}"></script>
     <!-- stylesheet -->
    {{--TODO:AGUNG: move style to main.ccss--}}
    <style type="text/css">
        [ng\:cloak], [ng-cloak], [data-ng-cloak], [x-ng-cloak], .ng-cloak, .x-ng-cloak {
            display: none !important;
        }
        .loading-visible{
            display:block;
        }
        .loading-invisible{
            display:none;
        }
        body{
            background-color: #f3f3f3;
        }
    </style>

    <style>
    .header, .footer {  }
        .header img   { float: left;}
        .header h1    { float: left; margin: 0px; padding: 15px; }
        .login-status { margin: 0px; padding: 15px; float: right; }
    </style>
	<title></title>
</head>
<body data-ng-controller="layoutCtrl">

    @yield('content')

</body>
</html>
