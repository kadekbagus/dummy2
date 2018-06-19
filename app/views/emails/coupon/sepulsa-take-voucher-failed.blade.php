<html>
    <head>
        <title>Take Voucher Failed</title>
    </head>
    <body>
        <h1>Take Voucher Failed</h1>
        <p>
            Hello Admin! Take voucher from Sepulsa FAILED for following transaction:
            <ul>
                <li>Internal Transaction ID: {{ $paymentId }}</li>
                <li>External Transaction ID: {{ $externalPaymentId }}</li>
                <li>Payment Provider: {{ $paymentMethod }}</li>
            </ul>

            <br>
            <br>
            Response from Sepulsa API:
            {{ $sepulsaResponse }}
        </p>
    
        <br>
        <br>
        <p>
            System is retrying the process at the moment and will notify you again once failure occurs.
        </p>
    </body>
</html>