<html>
    <head>
        <title>Suspicious Payment</title>
    </head>
    <body>
        <h1>Suspicious Payment</h1>
        <p>
            Hello Admin! We have a pending transaction that need validation from Midtrans Admin Portal (MAP). Below is the transaction detail:
            <ul>

                <li>Order ID: {{ $paymentId }}</li>
                <li>Midtrans Transaction ID: {{ $externalPaymentId }}</li>
                <li>Payment Provider: {{ $paymentMethod }}</li>
                <li>Coupon ID: {{ $couponId }}</li>
            </ul>
        </p>
    </body>
</html>
