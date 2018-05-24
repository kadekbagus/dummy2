<html>
    <head>Invoice</head>
    <body>
        <h1>Hi, {{{ $customerName }}} !</h1>
        <p>
            Thank you for purchasing <strong>Coupon {{{ $couponName }}}</strong> at GoToMalls.com! You can view the detail of your purchase below.
        </p>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Redeem Code</th>
                    <th>Coupon Name</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>1</td>
                    <td>{{{ $couponRedeemCode }}}</td>
                    <td>{{{ $couponName }}}</td>
                    <td>{{{ $quantity }}}</td>
                    <td>{{{ $couponPrice }}}</td>
                    <td>{{{ $total }}}</td>
                </tr>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right">Grand Total &nbsp;</td>
                    <td>{{{ $couponPrice }}}</td>
                </tr>
            </tfoot>
        </table>

        <br>
        <br>
        <br>
        <a href="<?= $redeemUrl ?>">Redeem This Coupon</a>
    </body>
</html>