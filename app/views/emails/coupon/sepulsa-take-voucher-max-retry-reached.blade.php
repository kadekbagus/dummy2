<html>
    <head>
        <title>Take Voucher Failed</title>
    </head>
    <body>
        <h1>Take Voucher Maximum Retry Reached</h1>
        <p>
            Hello Admin! Take voucher from Sepulsa FAILED after MAXIMUM RETRY REACHED ({{{ $maxRetry }}} times) for following transaction:
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
            System will not retry the process.
        </p>
    </body>
</html>