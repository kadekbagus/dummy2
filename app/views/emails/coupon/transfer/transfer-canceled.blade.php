<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <title>Transfer Canceled</title>

  @include('emails.coupon.transfer.styles')
</head>
<body style="margin:0; padding:0; background-color:#F2F2F2;">

  <span style="display: block; width: 640px !important; max-width: 640px; height: 1px" class="mobileOff"></span>

  <center>
    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#F2F2F2">
      <tr>
        <td align="center" valign="middle">
          <img src="https://s3-ap-southeast-1.amazonaws.com/asset1.gotomalls.com/uploads/emails/gtm-logo.png" class="logo" alt="Logo">
        </td>
      </tr>

      <tr>
        <td align="center" valign="top">

          <table width="640" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#FFFFFF" style="background-color: transparent;">
            <tr>
              <td align="center" valign="middle" style="box-shadow: 0 0 20px #e0e0e0; border-radius:5px;background-color: #FFF;">

                <table width="640" cellpadding="0" cellspacing="0" border="0" class="container mobile-full-width">
                  <tr>
                    <td align="center" valign="middle" height="184" class="greeting-title-container" style="border-radius: 5px 5px 0 0;">
                        <h1 class="greeting-title">{{ $header }}</h1>
                    </td>
                  </tr>
                </table>

                <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                  <tr>
                    <td class="mobile" align="left" valign="top">
                      <h3 class="greeting-username">{{ $greeting }}</h3>
                      <p class="greeting-text">
                        {{ $body }}
                      </p>
                    </td>
                  </tr>
                  <tr>
                    <td height="20" align="center">&nbsp;</td>
                  </tr>
                  <tr>
                    <td width="600" class="mobile center" valign="middle" style="text-align: center;">
                      <a href="{{ $couponUrl }}" style="display: block;text-decoration: none;">
                        <span style="display: inline-block;width:80px;height:80px;border:1px solid #ddd;" class="coupon-image">
                          <img src="{{ $couponImage }}" style="vertical-align:middle;width:100%;height:100%;object-fit: contain;">
                        </span>

                        <span style="display: inline-block;text-align:left;color:#222;margin-left:10px;vertical-align: middle;">
                          <h3 style="margin-bottom: 7px;margin-top: 0;" class="coupon-name">{{ $couponName }}</h3>
                          <p style="margin-top: 5px;margin-bottom:0;" class="coupon-location">{{ $brandName }}</p>
                        </span>
                      </a>
                    </td>
                  </tr>
                  <tr>
                    <td height="20" align="center" class="separator">&nbsp;</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <tr>
        <td align="center" valign="top">
          @include('emails.components.new-basic-footer')
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
