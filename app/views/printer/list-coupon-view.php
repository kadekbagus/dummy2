<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns:v="urn:schemas-microsoft-com:vml" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title><?php echo $pageTitle; ?></title>
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
        table thead {
            display:table-header-group;
        }
        table tbody {
            display:table-row-group;
        }
        table tr td, table tr th {
            border-bottom: 1px solid #ccc;
            padding: 1px;
        }
        table.noborder tr td, table.noborder tr th {
            border: 0;
            padding: 1px;
        }
        #loadingbar {
            position: relative;
            left: 1em;
        }
        h2 {
            padding-bottom: 2px;
            border-bottom: 1px solid #ccc;
        }

        /*th, td {*/
            /*display: inline-block;*/
        /*}*/
    </style>
    <style type="text/css" media="print">
        #payment-date, #printernote { display:none; }
        table thead {
            display:table-header-group;
        }
        table tbody {
            display:table-row-group;
        }
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
        <!-- <button id="printbtn" style="padding:0 6px;" onclick="window.exportToCSV()">Export to CSV</button> -->
    </div>
    <div id="loadingbar">Loading all the data, please wait...</div>
</div>

<div id="main">
    <h2 style="margin-bottom:0.5em;">Coupon List</h2>
    <table style="width:100%; margin-bottom:1em;" class="noborder">
        <tr>
            <td style="width:150px"></td>
            <td style="width:10px;"></td>
            <td><strong></strong></td>
        </tr>
        <tr>
            <td>Total Coupon</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalRec, 0, '.', '.'); ?></strong></td>
        </tr>

        <!-- Filtering -->
        <?php if ($couponName != '') { ?>
            <tr>
                <td>Filter by Coupon Name</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($couponName); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($ruleType != '') { ?>
            <tr>
                <td>Filter by Coupon Rule</td>
                <td>:</td>
                <td>
                    <strong>
                        <?php
                            $ruleString = '';
                            foreach ($ruleType as $key => $valrule){
                                $ruleString .= str_replace("_", " ", $valrule) . ', ';
                            }
                            echo htmlentities(rtrim($ruleString, ', '));
                        ?>
                    </strong>
                </td>
            </tr>
        <?php } ?>

        <?php if ($tenantName != '') { ?>
            <tr>
                <td>Filter by Tenant Name</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($tenantName); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($mallName != '') { ?>
            <tr>
                <td>Filter by Mall Name</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($mallName); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($etcFrom != '' && $etcTo != ''){ ?>
            <tr>
                <td>Filter by Estimated Total Cost</td>
                <td>:</td>
                <td> <strong><?php echo $etcFrom . ' - ' . $etcTo; ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($etcFrom != '' && $etcTo == ''){ ?>
            <tr>
                <td>Filter by Estimated Total Cost (From)</td>
                <td>:</td>
                <td> <strong><?php echo $etcFrom; ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($etcFrom == '' && $etcTo != ''){ ?>
            <tr>
                <td>Filter by Estimated Total Cost (To)</td>
                <td>:</td>
                <td> <strong><?php echo $etcTo; ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($status != '') { ?>
            <tr>
                <td>Filter by Status</td>
                <td>:</td>
                <td>
                    <strong>
                        <?php
                            $statusString = '';
                            foreach ($status as $key => $valstatus){
                                $statusString .= $valstatus . ', ';
                            }
                            echo htmlentities(rtrim($statusString, ', '));
                        ?>
                    </strong>
                </td>
            </tr>
        <?php } ?>

        <?php if ($beginDate != '' && $endDate != ''){ ?>
            <tr>
                <td>Campaign Date</td>
                <td>:</td>
                <td>
                    <?php
                        if ($beginDate != '' && $endDate != ''){
                            $beginDateRangeMallTime = date('d F Y', strtotime($beginDate));
                            $endDateRangeMallTime = date('d F Y', strtotime($endDate));
                            $dateRange = $beginDateRangeMallTime . ' - ' . $endDateRangeMallTime;
                            if ($beginDateRangeMallTime === $endDateRangeMallTime) {
                                $dateRange = $beginDateRangeMallTime;
                            }
                        }
                    ?>
                    <strong><?php echo $dateRange; ?></strong>
                </td>
            </tr>
        <?php } ?>

    </table>


    <table style="width:100%">
        <thead>
            <th style="text-align:left;">No</th>
            <th style="text-align:left;">Coupon Name</th>
            <th style="text-align:left;">Start Date & Time</th>
            <th style="text-align:left;">End Date & Time</th>
            <th style="text-align:left;">Status</th>
            <th style="text-align:left;">Last Update</th>
        </thead>
        <tbody>
            <?php $count = 1; while ($row = $statement->fetch(PDO::FETCH_OBJ)) : ?>
                <tr class="{{ $rowCounter % 2 === 0 ? 'zebra' : '' }}">
                    <td><?php echo $count++; ?></td>
                    <td><?php echo htmlentities($row->name_english); ?></td>
                    <td><?php echo date('d F Y H:i', strtotime($row->begin_date)); ?></td>
                    <td><?php echo date('d F Y H:i', strtotime($row->end_date)); ?></td>
                    <td><?php echo $row->campaign_status; ?></td>
                    <td><?php echo date('d F Y H:i:s', strtotime($row->updated_at)); ?></td>
                </tr>
            <?php endwhile ; ?>
        </tbody>
    </table>
</div>

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
        document.getElementById('loadingbar').style.display = 'none';
    }

    function exportToCSV() {
        // Replace the redundant query string argument 'export'
        var url = window.location.href.replace('&export=print', '').replace('&export=csv', '');

        window.location.href = url + '&export=csv';
    }
</script>

</body>
</html>
