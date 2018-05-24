<html>
    <head>Invoice</head>
    <body>
        <h1>Hi, {!! $customerName !!} !</h1>
        <p>
            Thank you for purchasing Coupon {!! $couponName !!} at GoToMalls.com. Here the receipt:
        </p>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Code</th>
                    <th>Coupon Name</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right">Grand Total &nbsp;</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </body>
</html>