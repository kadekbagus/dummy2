<!DOCTYPE html>
<html>
<head>
    <title></title>
</head>
<body>
    <p>Hi!</p>
    <p>Here are report for the availability of Sepulsa Vouchers against our Active Campaigns <br>
        <strong style="color: red;font-size: 1.5rem;">per {{ Carbon\Carbon::now()->format('d F Y, H:i') }}</strong>
    </p>
    <table border="1" style="border-collapse: collapse;" cellpadding="8" cellspacing="8">
        <thead>
            <tr>
                <th width="50">No</th>
                <th>Campaign/Coupon Name</th>
                <th>Token</th>
                <th>Available in Sepulsa?</th>
            </tr>
        </thead>
        <tbody>

            <?php $number = 0; ?>
            @foreach($coupons as $coupon)
            <tr>
                <td align="center">{{ ++$number }}</td>
                <td>{{ $coupon->promotion_name }}</td>
                <td>{{ $coupon->token }}</td>
                <td align="center">{{ $coupon->is_available ? 'Yes' : 'No' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if (count($newVouchers) > 0)
        <br>
        <h3>Below are the Sepulsa Vouchers that are not available in our database:</h3>
        <table border="1" style="border-collapse: collapse;" cellpadding="8" cellspacing="8">
            <thead>
                <tr>
                    <th align="center">No.</th>
                    <th>Voucher Name</th>
                    <th>Token</th>
                </tr>
            </thead>

            <tbody>
                <?php $number = 0; ?>
                @foreach($newVouchers as $token => $voucherName)
                <tr>
                    <td align="center">{{ ++$number }}</td>
                    <td>{{ $voucherName }}</td>
                    <td>{{ $token }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p>
        BR
        <br>
        Mr. Robot
    </p>
</body>
</html>
