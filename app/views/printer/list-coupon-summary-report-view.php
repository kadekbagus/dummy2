<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns:v="urn:schemas-microsoft-com:vml" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title><?php echo htmlentities($pageTitle); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="generator" content="Orbit" />
    <style media="all">
        * {
            margin: 0;
            padding: 0;
        }
        tr.zebra {
            background-color: #f1f1f1;
        }
        .hide {
            display: none;
        }
        #printernote {
            margin: 0;
            padding: 4px 8px;
            background: #FFFFAF;
            color: #555;
            font-size: 14px;
            height: 22px;
            position: relative;
            width: 100%;
            clear: both;
            font-family: Verdana, Arial, Serif;
        }
        table {
            border-collapse: collapse;
        }
        table tr td, table tr th {
            border-bottom: 1px solid #ccc;
            padding: 1px;

        }
    </style>
    <style type="text/css" media="print">
        #payment-date, #printernote { display:none; }
    </style>
</head>
<body>
<div id="printernote">
    <div style="float:left">
        <div style="margin-right:2em;top:4px;position:relative;">You are on printer friendly view.</div>
    </div>

    <div style="float:left">
        <select id="fontname" name="fontname">
            <option value="Arial">Arial</option>
            <option value="Courier New">Courier New</option>
            <option value="Sans-Serif">Sans-Serif</option>
            <option value="Serif">Serif</option>
            <option value="Tahoma">Tahoma</option>
            <option value="Times New Roman">Times New Roman</option>
            <option value="Verdana">Verdana</option>
        </select>
        <select id="fontsize" name="fontsize" style="width:50px;">
            <option value="8">8</option>
            <option value="9">9</option>
            <option value="10">10</option>
            <option value="11">11</option>
            <option value="12" selected="selected">12</option>
            <option value="13">13</option>
            <option value="14">14</option>
            <option value="15">15</option>
            <option value="16">16</option>
            <option value="17">17</option>
            <option value="18">18</option>
            <option value="19">19</option>
            <option value="20">20</option>
            <option value="17">21</option>
            <option value="18">22</option>
            <option value="19">23</option>
            <option value="20">24</option>
        </select>
        <button id="printbtn" style="padding:0 6px;" onclick="window.print()">Print Page</button>
    </div>
</div>

<div id="main">
    <h2 style="margin-bottom:0.5em;">Coupon Summary Report</h2>
    <table style="width:100%; margin-bottom:1em;" class="noborder">
        <tr>
            <td style="width:200px"></td>
            <td style="width:10px;"></td>
            <td><strong></strong></td>
        </tr>
        <tr>
            <td>Total Coupon Campaigns</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalCoupons, 0, '', ','); ?></strong></td>
        </tr>
        <tr>
            <td>Total Issued Coupons</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalIssued, 0, '', ','); ?></strong></td>
        </tr>
        <tr>
            <td>Total Redeemed Coupons</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalRedeemed, 0, '', ','); ?></strong></td>
        </tr>

        <!-- Filtering -->
        <?php if ($promotion_name != '') { ?>
            <tr>
                <td>Filter by Coupon Name</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($promotion_name); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($tenant_name != '') { ?>
            <tr>
                <td>Filter by Tenant Name</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($tenant_name); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($mall_name != '') { ?>
            <tr>
                <td>Filter by Location</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($mall_name); ?></strong></td>
            </tr>
        <?php } ?>


        <?php
        if ( is_array($rule_type) && count($rule_type) > 0) {
            $rule_type_string = '';
            foreach ($rule_type as $key => $val_rule_type){

                $rule_type = $val_rule_type;
                if ($rule_type === 'auto_issue_on_first_signin') {
                    $rule_type = 'coupon blast upon first sign in';
                } elseif ($rule_type === 'auto_issue_on_signup') {
                    $rule_type = 'coupon blast upon sign up';
                } elseif ($rule_type === 'auto_issue_on_every_signin') {
                    $rule_type = 'coupon blast upon every sign in';
                } elseif ($rule_type === 'manual') {
                    $rule_type = 'manual issued';
                }

                $rule_type_string .= $rule_type . ', ';
            }
        ?>
            <tr>
                <td>Filter by Coupon Rule</td>
                <td>:</td>
                <td><strong><?php echo htmlentities(rtrim($rule_type_string, ', ')); ?></strong></td>
            </tr>
        <?php } ?>

        <?php
        if ( is_array($status) && count($status) > 0) {
            $status_string = '';
            foreach ($status as $key => $valstatus){
                $status_string .= $valstatus . ', ';
            }
        ?>
            <tr>
                <td>Filter by Status</td>
                <td>:</td>
                <td><strong><?php echo htmlentities(rtrim($status_string, ', ')); ?></strong></td>
            </tr>
        <?php } ?>

        <?php
        if ($start_validity_date != '' && $end_validity_date != ''){
            $startDateRangeMallTime = date('d M Y', strtotime($start_validity_date));
            $endDateRangeMallTime = date('d M Y', strtotime($end_validity_date));
            $dateRange = $startDateRangeMallTime . ' - ' . $endDateRangeMallTime;
            if ($startDateRangeMallTime === $endDateRangeMallTime) {
                $dateRange = $startDateRangeMallTime;
            }
        ?>
            <tr>
                <td>Validity Date</td>
                <td>:</td>
                <td><strong><?php echo $dateRange; ?></strong></td>
            </tr>
        <?php } ?>
    </table>
    <table style="width:100%">
        <thead>
            <th style="text-align:left;">No</th>
            <th style="text-align:left;">Coupon Name</th>
            <th style="text-align:left;">Campaign Dates</th>
            <th style="text-align:left;">Validity Date</th>
            <th style="text-align:left;">Location</th>
            <th style="text-align:left;">Coupon Rule</th>
            <th style="text-align:left;">Issued (Issued/Available)</th>
            <th style="text-align:left;">Redeemed (Redeemed/Issued)</th>
            <th style="text-align:left;">Status</th>
        </thead>
        <tbody>
        <?php while ($row = $statement->fetch(PDO::FETCH_OBJ)) : ?>
            <tr class="{{ $rowCounter % 2 === 0 ? 'zebra' : '' }}">
                <td><?php echo (++$rowCounter); ?></td>
                <td><?php echo $row->promotion_name; ?></td>
                <td><?php echo date('d M Y', strtotime($row->begin_date)) . ' - ' . date('d M Y', strtotime($row->end_date)); ?></td>
                <td><?php echo date('d M Y', strtotime($row->coupon_validity_in_date)); ?></td>
                <td>
                    <?php
                        $locations = explode(', ', $row->campaign_location_names);
                        for($x = 0; $x < count($locations); $x++) {
                            echo $locations[$x] . '<br>';
                        }
                    ?>
                </td>
                <td>
                    <?php
                        $rule_type = $row->rule_type;
                        if ($rule_type === 'auto_issue_on_first_signin') {
                            $rule_type = 'Coupon blast upon first sign in';
                        } elseif ($rule_type === 'auto_issue_on_signup') {
                            $rule_type = 'Coupon blast upon sign up';
                        } elseif ($rule_type === 'auto_issue_on_every_signin') {
                            $rule_type = 'Coupon blast upon every sign in';
                        } elseif ($rule_type === 'manual') {
                            $rule_type = 'Manual issued';
                        }

                        echo $rule_type;
                    ?>
                </td>

                <?php
                    $total_issued = $row->total_issued != 'Unlimited' ? number_format($row->total_issued, 0, '', ',') : 'Unlimited' ;
                    $available = $row->available != 'Unlimited' ? number_format($row->available, 0, '', ',') : 'Unlimited' ;
                    $total_redeemed = $row->total_redeemed != 'Unlimited' ? number_format($row->total_redeemed, 0, '', ',') : 'Unlimited' ;
                ?>
                <td><?php echo $total_issued . ' / ' . $available; ?></td>
                <td><?php echo $total_redeemed . ' / ' .$total_issued; ?></td>

                <td><?php echo $row->campaign_status; ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!--<div style="page-break-after:always;"></div>-->

<script type="text/javascript">
    window.onload = function() {
        // window.print();
        document.getElementById('fontname').onchange = function() {
            var selectedFont = document.getElementById('fontname').value;
            document.getElementById('main').style.fontFamily = selectedFont;
        };

        document.getElementById('fontsize').onchange = function() {
            var selectedSize = document.getElementById('fontsize').value;
            document.getElementById('main').style.fontSize = selectedSize + "px";
        };

        document.getElementById('main').style.fontFamily = "Arial";
        document.getElementById('main').style.fontSize = "12px";
    }
</script>

</body>
</html>
