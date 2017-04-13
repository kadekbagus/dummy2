<html>
<head>
<title></title>
</head>
<body>
    <p>Hello,</p>
    <p>User {{ $userEmail}} started reward CSV Export process at {{ $exportDate }} UTC. The details as follow:</p>
    <p>
        Export ID: {{ $exportId }} <br/>
        Coupon(s) to Export: {{ $totalExport }} <br/>
    </p>
    <p>
        List Coupon(s):<br>
        <ul>
        @foreach ($coupons as $c)
            <li>{{ $c }}</li>
        @endforeach
        </ul>
    </p>
    </p>
    <p>Once the export process is completed you will get notified by email.</p>
    <p>
        Regards,<br/>
        Mr. Robot
    </p>
</body>
</html>