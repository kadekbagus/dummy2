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
    <h2 style="margin-bottom:0.5em;">Campaign Summary Report</h2>
    <table style="width:100%; margin-bottom:1em;" class="noborder">
        <tr>
            <td style="width:150px"></td>
            <td style="width:10px;"></td>
            <td><strong></strong></td>
        </tr>
        <tr>
            <td>Number of campaigns</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalRecord, 0, '.', '.'); ?></strong></td>
        </tr>
        <tr>
            <td>Total page views</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalPageViews, 0, '.', '.'); ?></strong></td>
        </tr>
        <tr>
            <td>Total pop up views</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalPopUpViews, 0, '.', '.'); ?></strong></td>
        </tr>
        <tr>
            <td>Estimated total cost</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalEstimatedCost, 0, '.', '.'); ?></strong></td>
        </tr>
        <tr>
            <td>Total spending</td>
            <td>:</td>
            <td><strong><?php echo number_format($totalSpending, 0, '.', '.'); ?></strong></td>
        </tr>

        <!-- Filtering -->
        <?php if($startDate != '' && $endDate != ''){ ?>
            <tr>
                <td>Campaign date</td>
                <td>:</td>
                <td><strong><?php echo $this->printDateTime($startDate, $timezone, 'd M Y') . ' - ' . $this->printDateTime($endDate, $timezone, 'd M Y'); ?></strong></td>
            </tr>
        <?php } ?>

        <?php if ($campaignName != '') { ?>
            <tr>
                <td>Filter by Campaign Name</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($campaignName); ?></strong></td>
            </tr>
        <?php } elseif($campaignType != '') { ?>
            <tr>
                <td>Filter by Campaign Type</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($campaignType); ?></strong></td>
            </tr>
        <?php } elseif($tenant != '') { ?>
            <tr>
                <td>Filter by Tenant</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($tenant); ?></strong></td>
            </tr>
        <?php } elseif($mallName != '') { ?>
            <tr>
                <td>Filter by  Location</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($mallName); ?></strong></td>
            </tr>
        <?php } elseif($status != '') { ?>
            <tr>
                <td>Filter by Status</td>
                <td>:</td>
                <td><strong><?php echo htmlentities($status); ?></strong></td>
            </tr>
        <?php } ?>

    </table>

    <table style="width:100%">
        <thead>
            <th style="text-align:left;">No</th>
            <th style="text-align:left;">Campaign Name</th>
            <th style="text-align:left;">Campaign Type</th>
            <th style="text-align:left;">Tenants</th>
            <th style="text-align:left;">Mall</th>
            <th style="text-align:left;">Campaign Dates</th>
            <th style="text-align:left;">Page Views</th>

            <th style="text-align:left;">Views Popup</th>
            <th style="text-align:left;">Clicks Popup</th>
            <th style="text-align:left;">Daily Cost</th>

            <th style="text-align:left;">Estimated Total Cost</th>
            <th style="text-align:left;">Spending</th>

            <th style="text-align:left;">Status</th>
        </thead>

        <tbody>
            <?php
            $no  = 1;
            if ($totalRecord > 0) {
                foreach ($data as $key => $value) {
                    ?>
                        <tr class="{{ $rowCounter % 2 === 0 ? 'zebra' : '' }}">
                            <td><?php echo $no; ?></td>
                            <td><?php echo htmlentities($value->campaign_name); ?></td>
                            <td><?php echo htmlentities($value->campaign_type); ?></td>
                            <td><?php echo $value->total_tenant; ?></td>
                            <td><?php echo htmlentities($value->mall_name); ?></td>
                            <td><?php echo $this->printDateTime($value->begin_date, $timezone, 'd M Y H:i:s') . ' - ' . $this->printDateTime($value->end_date, $timezone, 'd M Y H:i:s'); ?></td>
                            <td><?php echo $value->page_views; ?></td>
                            <td><?php echo $value->popup_views; ?></td>
                            <td><?php echo $value->popup_clicks; ?></td>
                            <td><?php echo $value->base_price; ?></td>
                            <td><?php echo $value->estimated_total; ?></td>
                            <td><?php echo $value->spending; ?></td>
                            <td><?php echo $value->status; ?></td>
                        </tr>
                    <?php
                    $no++;
                }
            }
            ?>
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
