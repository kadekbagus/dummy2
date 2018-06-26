<html>
    <head>
        <title>Coupon Not Available</title>
    </head>
    <body>
        <h1>Hi, {{ $customerName }}</h1>
        <p>
            We are sorry to inform you that we can not get the coupon <strong>{{ $couponName }}</strong>. 
            Don't worry about your money, we will refund it 100% as soon as possible before {{ $maxRefundDate }}.
            <br>
            <br>
            Your transaction ID is: <strong>{{ $paymentId }}</strong>
            <br>
            <br>
            Meanwhile, you can still buy other coupons at our website!
        </p>
    
        <br>
        <br>
        <p>
            For any question regarding the refund process, feel free to contact us via our support channels below:
            <br>
        </p>
    </body>
</html>