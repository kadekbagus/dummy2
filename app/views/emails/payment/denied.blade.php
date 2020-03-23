<html>
    <head>
        <title>Denied Payment</title>
    </head>
    <body>
        <h1>Denied Payment</h1>
        <p>
            Hello Admin! We have a denied transaction (canceled/reversed), that need further actions. Below is the transaction detail:
            <ul>

                <li>Order ID: {{ $paymentId }}</li>
                <li>Midtrans Transaction ID: {{ $externalPaymentId }}</li>
                <li>Payment Provider: {{ $paymentMethod }}</li>
                <li>Coupon ID: {{ $couponId }}</li>
            </ul>
        </p>
    </body>
</html>
