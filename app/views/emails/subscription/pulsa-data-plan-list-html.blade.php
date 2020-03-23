<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <title>Confirm Coupon Transfer</title>

  @include('emails.components.styles')

  <style type="text/css">
    .greeting-title {
      letter-spacing: 5px;
      padding-left: 20px;
      padding-right: 20px;
    }

    .greeting-username {
      font-size: 18px;
      margin-bottom: 20px;
      color: #444;
    }

    .pulsa-promo-container {
      border: 1px solid #d0d0d0;
      padding: 15px;
      border-radius:8px;
    }

    .pulsa-promo-img {
      width:130px;
      height:130px;
      border-radius:5px;
    }

    .pulsa-promo-description {
      margin: 15px 5px 15px 15px;
      font-size: 17px;
      color: #444;
      line-height: 1.5em;
    }

    .btn-pulsa-list-url {
      font-size:16px;
      margin-left: 15px;
      margin-right: 15px;
      padding-left: 10px;
      padding-right: 10px;
      display: inline-block;
    }

    .campaign-suggestion-text {
      font-size: 17px;
      margin-top: 5px;
      margin-bottom: 5px;
      color:#999;
    }

    .suggestion-list-title {
      font-size: 24px;
      color: #444;
      margin: 25px 0 20px 0;
    }

    .suggestion-list-item {
      padding-bottom: 30px !important;
    }
    .suggestion-list-item.odd {
      padding-right:10px;
    }
    .suggestion-list-item.even {
      padding-left: 10px;
    }

    .suggestion-list-item-img-container {
      width: 80px;
    }

    .suggestion-list-item-img {
      width:80px;
      height:80px;
      border-radius: 5px;
      border: 1px solid #d0d0d0;
      display: inline-block;
    }

    .suggestion-list-item-img img {
      object-fit: contain;
      width: 100%;
      height: 100%;
      vertical-align:middle;
    }

    .suggestion-list-item-info {
      padding-left: 10px;
    }

    .suggestion-list-item-title {
      font-size: 16px;
    }

    .suggestion-list-item-location {
      font-size: 15px;
    }

    .suggestion-list-item-price {
      font-size: 16px;
    }

    .suggestion-list-more-url {
      font-size: 16px;
    }

    /* MEDIA QUERIES */
    @media all and (max-width:639px) {

      .greeting-username {
        font-size: 26px;
      }

      .pulsa-promo-container {
        padding: 25px 10px;
      }

      .pulsa-promo-img {
        width: 180px;
        height: 180px;
      }

      .pulsa-promo-description {
        font-size: 22px;
      }

      .btn-pulsa-list-url {
        font-size: 22px;
      }

      .campaign-suggestion-text {
        font-size: 22px;
        margin-top: 10px;
        margin-bottom: 10px;
      }

      .suggestion-list-title {
        font-size: 26px;
      }

      .suggestion-list-item,
      .suggestion-list-item.even,
      .suggestion-list-item.odd {
        padding-right: 0 !important;
        padding-left: 0 !important;
        padding-bottom: 15px !important;
      }

      .suggestion-list-item-img-container {
        width: 140px;
      }

      .suggestion-list-item-img {
        width:140px;
        height:140px;
      }

      .suggestion-list-item-info {
        padding-left: 10px;
        width: 440px;
      }

      .suggestion-list-item-title {
        font-size: 22px;
      }

      .suggestion-list-item-location {
        font-size: 20px;
      }

      .suggestion-list-item-price {
        font-size: 20px;
      }

      .suggestion-list-more-url {
        font-size: 20px;
      }
    }
  </style>
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
                        <h1 class="greeting-title">{{ trans('email-subscription.pulsa.body.greetings.title', [], '', 'id') }}</h1>
                    </td>
                  </tr>
                </table>

                <table width="580" cellpadding="0" cellspacing="0" border="0" class="container">
                  <tr>
                    <td class="mobile" align="left" valign="top">
                      <h3 class="greeting-username">{{ trans('email-subscription.pulsa.body.greetings.customer', ['customerName' => $customerName], '', 'id') }}</h3>
                    </td>
                  </tr>
                  <tr>
                    <td class="mobile" align="left" valign="top">
                      <div style="" class="pulsa-promo-container">
                        <table border="0" cellpadding="0" cellspacing="0">
                          <tr>
                            <td class="mobile center" valign="top">
                              <img src="https://mall-api-v420.gotomalls.cool/uploads/coupon/translation/2019/05/MDNvYtXoxRCXdkXx--1558592400_1.jpg" style="" class="pulsa-promo-img">
                            </td>
                            <td class="mobile center" valign="top">
                              <p class="pulsa-promo-description">{{ trans('email-subscription.pulsa.body.marketing_body', [], '', 'id') }}</p>
                              <a href="{{ $pulsaListUrl }}" class="btn btn-pulsa-list-url">{{ trans('email-subscription.pulsa.body.buttons.pulsa_list', [], '', 'id') }}</a>
                            </td>
                          </tr>
                        </table>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td height="20" align="center">&nbsp;</td>
                  </tr>
                  @if (count($campaigns['coupons']) > 0 || count($campaigns['events']) > 0)
                    <tr>
                      <td width="600" class="mobile center" valign="middle">
                        <p class="campaign-suggestion-text">{{ trans('email-subscription.pulsa.body.campaign_suggestion_text', [], '', 'id') }}</p>
                      </td>
                    </tr>

                    @include('emails.subscription.pulsa-data-plan-list-coupon-suggestion', ['campaigns' => $campaigns['coupons'], 'campaignListUrl' => $campaigns['couponListUrl']])

                    @include('emails.subscription.pulsa-data-plan-list-event-suggestion', ['campaigns' => $campaigns['events'], 'campaignListUrl' => $campaigns['eventListUrl']])
                  @endif
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
