<html>
    <head>
        <title>Invoice from Gotomalls.com</title>
        <style>
            body {
                font-family: Sans;
                padding: 20px;
                text-align: left;
            }

            table {
                line-height: 1.7em;
                font-size: 14px;
                color: #222;
            }

            table.no-border  {
                border: 0;
            }
            table.collapse {
                border-collapse: collapse;
            }

            .text-red { color: #f43d3c; }

            .text-right { text-align: right; }
            .text-left { text-align: left; }

            thead tr.invoice-header th {
                padding-bottom: 30px;
            }

            img.logo {
                width: 80%;
            }

            .invoice-title {
                font-size: 28px;
                font-weight: bold;
            }

            .invoice-info {
                padding-top: 20px;
                padding-bottom: 20px;
            }

            .invoice-body {
                padding-top: 10px;
                padding-bottom: 80px;
            }

            table.customer {
                margin-top: 30px;
            }

            table.customer tr th {
                padding-left: 15px;
            }

            table.customer tr td {
                padding: 15px;
            }

            table.customer tr th:first-child,
            table.customer tr td:first-child {
                padding-left: 0;
            }

            table.transaction {
                margin-top: 30px;
            }

            table.transaction tr th {
                padding: 8px;
            }

            table.transaction tr td {
                padding: 15px 8px;
            }

            table.transaction tr th:first-child,
            table.transaction tr td:first-child {
                padding-left: 0;
            }

            tr.transaction-item td {
                border-bottom: 1px solid #999;
                vertical-align: top;
            }
            tr.transaction-item:last-child td {
                border-bottom: 0;
            }

            thead.bordered tr th {
                border-top: 1px solid #999;
                border-bottom: 1px solid #999;
            }

            tfoot.transaction-footer tr td {
                border-top: 1px solid #999;
            }
        </style>
    </head>
    
    <body>
        <table border="0" cellpadding="0" cellspacing="0" class="no-border collapse" width="640px" style="margin: 0 auto;">
            <thead>
                <tr class="invoice-header">
                    <th width="50%" class="text-left">
                        <img src="https://s3-ap-southeast-1.amazonaws.com/asset1.gotomalls.com/themes/default/images/logo-en.png?t=1523326836" alt="" class="logo">
                    </th>
                    <th class="text-right">
                        <span class="text-red invoice-title">Invoice</span>
                    </th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td class="text-left invoice-info"><strong>Order No. {{{ $transaction['id'] }}}</strong></td>
                    <td class="text-right invoice-info">{{{ $transaction['date'] }}}</td>
                </tr>

                <tr>
                    <td colspan="2" class="invoice-body">
                        <p>
                            Hello, {{{ $customerName }}}
                            <br>
                            Thank you for shopping at Gotomalls.com and for your order. A detailed summary of your invoice is below.
                            If you have questions, we are happy to help you. Please send your your email to <a href="mailto:support@gotomalls.com"><span class="text-red">support@gotomalls.com</span></a> 
                            or contact us through our support channel.
                        </p>

                        <table class="no-border collapse customer" width="100%">
                            <thead>
                                <tr>
                                    <th class="text-left" width="25%">Customer</th>
                                    <th class="text-left" width="25%">Email</th>
                                    <th class="text-left">Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{{ $customerName }}}</td>
                                    <td>{{{ $customerEmail }}}</td>
                                    <td>{{{ $customerPhone }}}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table class="no-border collapse transaction" width="100%">
                            <thead class="bordered">
                                <tr>
                                    <th class="text-left" width="45%">Item</th>
                                    <th class="text-left" width="15%">Quantity</th>
                                    <th class="text-left" width="20%">Price</th>
                                    <th class="text-left" width="25%">Subtotal</th>
                                </tr>
                            </thead>

                            <tbody class="transaction-items">
                                @foreach($transaction['items'] as $item)
                                    <tr class="transaction-item">
                                        <td>{{{ $item['name'] }}}</td>
                                        <td>{{{ $item['quantity'] }}}</td>
                                        <td>{{{ $item['price'] }}}</td>
                                        <td>{{{ $item['total'] }}}</td>
                                    </tr>
                                @endforeach
                            </tbody>

                            <tfoot class="transaction-footer">
                                <tr>
                                    <td colspan="2"></td>
                                    <td><strong>Total</strong></td>
                                    <td>{{{ $transaction['total'] }}}</td>
                                </tr>
                            </tfoot>
                        </table>

                        <p>
                            Thank you and have a nice day.
                        </p>
                    </td>
                </tr>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="2" class="text-left footer">
                        <small>
                            <strong>PT. Dominopos Kreasi Jaya</strong><br>
                            Tower E - Lantai 2, 18 Parc Place, SCBD <br>
                            Jalan Jend. Sudirman Kav. 52-53, Senayan, Jakarta Selatan 12190
                        </small>
                    </td>
                </tr>
            </tfoot>
        </table>
    </body>
</html>