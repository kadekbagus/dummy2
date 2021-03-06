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
    @if(! empty($skippedCoupons))
      <p>
        List of coupon that not included because it is already in export process:<br>
        <ul>
          @foreach($skippedCoupons as $skip)
            <li>{{ $skip }}</li>
          @endforeach
        </ul>
      </p>
    @endif
    <p>
        Regards,<br/>
        Mr. Robot
    </p>
</body>
</html>