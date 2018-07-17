<html>
    <head>
        <title>Unable to Issue Coupon for Customer</title>
    </head>
    <body>
        <h1>Hi, Admin!</h1>
        <p>
            We detected there's a problem while trying to issue coupon for following transaction:
            <br>
            <br>
            <ul>
                <li>Transaction ID: <strong>{{ $paymentId }}</strong></li>
                <li>Customer Name: <strong>{{ $customerName }}</strong></li>
                <li>Customer Email: <strong>{{ $recipientEmail }}</strong></li>
                <li>Coupon ID: {{ $couponId }}</li>
                <li>Coupon Name: {{ $couponName }}</li>
            </ul>
        </p>
    
        <h3>Failure message from system:</h3>
        <p>
            {{ $reason }}
        </p>
    </body>
</html>