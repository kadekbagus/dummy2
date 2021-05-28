<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <title>Your Monthly Report on Gotomalls.com</title>

  @include('emails.components.styles')

</head>
<body style="margin:0; padding:0; background-color:#F2F2F2;">

  <span style="display: block; width: 640px !important; max-width: 640px; height: 1px" class="mobileOff"></span>

  <center>
    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#F2F2F2">
      <tr>
        <td align="center" valign="middle">
          <img src="https://cloudfront.gotomalls.com/uploads/emails/gtm-logo.png" class="logo" alt="Logo">
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
                      <h1 class="greeting-title">Your Monthly Report</h1>
                    </td>
                  </tr>
                </table>

                <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                  <tr>
                    <td width="600" class="mobile" align="left" valign="top">
                      <table>
                        <tr>
                          <td width="450" class="mobile" align="left" valign="top">
                            <h3 class="greeting-username">
                            <strong>Hi {{$user_name}},</strong></h3>
                            <p class="greeting-text">
                              {{ trans('email-report.body.greeting_line_1') }}
                            </p>

                            <p class="greeting-text">
                              {{ trans('email-report.body.greeting_line_2') }}
                            </p>
                          </td>
                          <td width="200" class="mobile mobile-align-center" align="right" valign="top">
                            <a href="{{ $landing_page_url }}" class="btn btn-visit">
                              {{ trans('email-report.body.btn-visit') }}
                            </a>
                          </td>
                        </tr>
                        <tr>
                          <td align="center" colspan="2" class="separator">&nbsp;</td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>

                <table width="600" cellpadding="0" cellspacing="0" border="0" class="container statistic-container">
                  <tr>
                    <td width="40%" class="mobile statistic-item" valign="middle">
                      <table width="100%">
                        <tr>
                          <td valign="middle">
                            <h3 class="statistic-title">{{ trans('email-report.body.rank_title') }}</h3>
                          </td>
                          <td valign="middle">
                            <h3 class="statistic-title">{{ trans('email-report.body.gtm_point') }}</h3>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div class="statistic-value" valign="bottom">
                              {{$user_rank}}
                            </div>
                          </td>
                          <td>
                            <div class="statistic-value" valign="bottom">
                              {{$user_point}}
                            </div>
                          </td>
                        </tr>
                      </table>
                    </td>

                    <td width="auto" class="mobile statistic-item" valign="middle">
                      <table width="100%">
                        <tr>
                          <td valign="middle">
                            <h3 class="statistic-title">{{ trans('email-report.body.voucher_purchased') }}</h3>
                          </td>
                          <td valign="middle">
                            <h3 class="statistic-title">{{ trans('email-report.body.pulsa_purchased') }}</h3>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div class="statistic-value" valign="bottom">
                              {{$coupon_purchased}}
                            </div>
                          </td>
                          <td>
                            <div class="statistic-value" valign="bottom">
                              {{$pulsa_purchased}}
                            </div>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td align="center" colspan="4">
                      <hr class="separator" style="border: 0; height: 1px;background-color:#ddd;">
                    </td>
                  </tr>
                </table>

                <table width="600" cellpadding="0" cellspacing="0" border="0" class="container statistic-container">
                  <tr>
                    <td width="100%" class="mobile statistic-item" valign="middle">
                      <table width="100%">
                        <tr>
                          <td valign="middle">
                            <h3 class="statistic-title">{{ trans('email-report.body.game_voucher_purchased') }}</h3>
                          </td>
                          <td valign="middle">
                            <h3 class="statistic-title">{{ trans('email-report.body.pln_purchased') }}</h3>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div class="statistic-value" valign="bottom">
                              {{$game_voucher_purchased}}
                            </div>
                          </td>
                          <td>
                            <div class="statistic-value" valign="bottom">
                              {{$pln_purchased}}
                            </div>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td align="center" colspan="4">
                      <hr class="separator" style="border: 0; height: 1px;background-color:#ddd;">
                    </td>
                  </tr>
                </table>

                <table width="600" cellpadding="0" cellspacing="0" class="container container-pulsa-banner">
                  <tr>
                    <td class="mobile" width="300">
                      <div style="padding-right: 4px" class="pulsa-banner-container">
                        <a href="{{ $pulsa_page_url }}">
                          <img src="https://cloudfront.gotomalls.com/uploads/emails/pulsa-may-2021.jpg" class="pulsa-banner-img">
                        </a>
                      </div>
                    </td>
                    <td class="mobile" width="300">
                      <div style="padding-left: 4px" class="pulsa-banner-container">
                        <a href="{{ $pln_page_url }}">
                          <img src="https://cloudfront.gotomalls.com/uploads/emails/pln-token-may-2021.jpg" class="pulsa-banner-img">
                        </a>
                      </div>
                    </td>
                  </tr>
                </table>

                <table width="600" cellpadding="0" cellspacing="0" class="container container-pulsa-banner">
                  <tr>
                    <td class="mobile" width="300">
                      <div style="padding-right: 4px" class="pulsa-banner-container">
                        <a href="{{ $game_voucher_page_url }}">
                          <img src="https://cloudfront.gotomalls.com/uploads/emails/voucher-game-may-2021.jpg" class="pulsa-banner-img">
                        </a>
                      </div>
                    </td>
                    <td class="mobile" width="300">
                      <div style="padding-left: 4px" class="pulsa-banner-container">
                        <a href="{{ $product_list_page_url }}">
                          <img src="https://cloudfront.gotomalls.com/uploads/emails/product-may-2021.jpg" class="pulsa-banner-img">
                        </a>
                      </div>
                    </td>
                  </tr>
                </table>

{{-- single column banner area --}}
{{--
                <table width="600" cellpadding="0" cellspacing="0" class="container container-pulsa-banner">
                  <tr>
                    <td class="mobile" width="600">
                      <a href="{{ $game_voucher_page_url }}">
                        <img src="https://cloudfront.gotomalls.com/uploads/emails/game-voucher-banner-03-2020.jpg" alt="game-voucher-banner" style="width: 100%;margin-top:5px;margin-bottom:20px;">
                      </a>
                    </td>
                  </tr>
                </table>
--}}

                <table width="600" cellpadding="0" cellspacing="0" class="container campaigns-container">
                  <tr>
                    <td width="280" class="mobile campaigns-item-container" valign="top">
                      <table cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                          <td width="220" valign="top">
                            <h3 class="campaigns-title">{{ trans('email-report.body.campaigns.mall_title') }}</h3>
                            <p class="campaigns-desc">{{ trans('email-report.body.campaigns.mall_desc') }}</p>
                          </td>
                          <td class="statistic-value number-of-views" valign="top" align="right">
                            <h3 class="campaigns-views">{{$mall_view}}</h3>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <table>
                              <tr>
                                @if(!empty($mall_data))
                                  @foreach($mall_data as $mall)
                                    <td width="70" height="70" style="padding-right: 10px;" valign="middle">
                                      <a href="{{ $mall['link_url'] }}" style="position:relative;width: 100%;height: 100%;display:block;border:1px solid #eee;">
                                        <img src="{{ $mall['cdn_url'] }}" alt="" style="width: 100%;height:100%;position:absolute;object-fit:contain;">
                                      </a>
                                    </td>
                                  @endforeach
                                @endif
                              </tr>
                            </table>
                          </td>
                          <td align="right">
                            <a href="{{ $mall_list_page_url }}" class="see-all">See All</a>
                          </td>
                        </tr>
                      </table>
                    </td>

                    <td class="mobile hide-on-mobile campaigns-item-separator">&nbsp;</td>

                    <td width="280" class="mobile campaigns-item-container" valign="top">
                      <table cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                          <td width="220">
                            <h3 class="campaigns-title">{{ trans('email-report.body.campaigns.brand_title') }}</h3>
                            <p class="campaigns-desc">{{ trans('email-report.body.campaigns.brand_desc') }}</p>
                          </td>
                          <td class="statistic-value number-of-views" valign="top" align="right">
                            <h3 class="campaigns-views">{{ $store_view }}</h3>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <table>
                              <tr>
                                @if(!empty($store_data))
                                  @foreach($store_data as $store)
                                    <td width="70" height="70" style="padding-right: 10px;" valign="middle">
                                      <a href="{{ $store['link_url'] }}" style="position:relative;width: 100%;height: 100%;display:block;border:1px solid #eee;">
                                        <img src="{{ $store['cdn_url'] }}" alt="" style="width: 100%;height:100%;position:absolute;object-fit:contain;">
                                      </a>
                                    </td>
                                  @endforeach
                                @endif
                              </tr>
                            </table>
                          </td>
                          <td align="right">
                            <a href="{{ $store_list_page_url }}" class="see-all">See All</a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>

                  <tr>
                    <td width="280" class="mobile campaigns-item-container" valign="top">
                      <table cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                          <td width="220" valign="top">
                            <h3 class="campaigns-title">{{ trans('email-report.body.campaigns.promotion_title') }}</h3>
                            <p class="campaigns-desc">{{ trans('email-report.body.campaigns.promotion_desc') }}</p>
                          </td>
                          <td class="statistic-value number-of-views" valign="top" align="right">
                            <h3 class="campaigns-views">{{ $promotion_view }}</h3>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <table>
                              <tr>
                                @if(!empty($promotion_data))
                                  @foreach($promotion_data as $promotion)
                                    <td width="70" height="70" style="padding-right: 10px;" valign="middle">
                                      <a href="{{ $promotion['link_url'] }}" style="position:relative;width: 100%;height: 100%;display:block;border:1px solid #eee;">
                                        <img src="{{ $promotion['cdn_url'] }}" alt="" style="width: 100%;height:100%;position:absolute;object-fit:contain;">
                                      </a>
                                    </td>
                                  @endforeach
                                @endif
                              </tr>
                            </table>
                          </td>
                          <td align="right">
                            <a href="{{ $promotion_list_page_url }}" class="see-all">See All</a>
                          </td>
                        </tr>
                      </table>
                    </td>

                    <td class="mobile hide-on-mobile campaigns-item-separator">&nbsp;</td>

                    <td width="280" class="mobile campaigns-item-container" valign="top">
                      <table cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                          <td width="220">
                            <h3 class="campaigns-title">{{ trans('email-report.body.campaigns.event_title') }}</h3>
                            <p class="campaigns-desc">{{ trans('email-report.body.campaigns.event_desc') }}</p>
                          </td>
                          <td class="statistic-value number-of-views" valign="top" align="right">
                            <h3 class="campaigns-views">{{ $event_view }}</h3>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <table>
                              <tr>
                                @if(!empty($event_data))
                                  @foreach($event_data as $event)
                                    <td width="70" height="70" style="padding-right: 10px;" valign="middle">
                                      <a href="{{ $event['link_url'] }}" style="position:relative;width: 100%;height: 100%;display:block;border:1px solid #eee;">
                                        <img src="{{ $event['cdn_url'] }}" alt="" style="width: 100%;height:100%;position:absolute;object-fit:contain;">
                                      </a>
                                    </td>
                                  @endforeach
                                @endif
                              </tr>
                            </table>
                          </td>
                          <td align="right">
                            <a href="{{ $event_list_page_url }}" class="see-all">See All</a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>

                  <tr>
                    <td width="280" class="mobile campaigns-item-container" valign="top">
                      <table cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                          <td width="220" valign="top">
                            <h3 class="campaigns-title">{{ trans('email-report.body.campaigns.article_title') }}</h3>
                            <p class="campaigns-desc">{{ trans('email-report.body.campaigns.article_desc') }}</p>
                          </td>
                          <td class="statistic-value number-of-views" valign="top" align="right">
                            <h3 class="campaigns-views">{{ $article_view }}</h3>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <table>
                              <tr>
                                @if(!empty($article_data))
                                  @foreach($article_data as $article)
                                    <td width="70" height="70" style="padding-right: 10px;" valign="middle">
                                      <a href="{{ $article['link_url'] }}" style="position:relative;width: 100%;height: 100%;display:block;border:1px solid #eee;">
                                        <img src="{{ $article['cdn_url'] }}" alt="" style="width: 100%;height:100%;position:absolute;object-fit:contain;">
                                      </a>
                                    </td>
                                  @endforeach
                                @endif
                              </tr>
                            </table>
                          </td>
                          <td align="right">
                            <a href="{{ $article_list_page_url }}" class="see-all">See All</a>
                          </td>
                        </tr>
                      </table>
                    </td>

                    <td class="mobile hide-on-mobile campaigns-item-separator">&nbsp;</td>

                    <td width="280" class="mobile campaigns-item-container hide-on-mobile" valign="top">
                      <table cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                          <td width="220" valign="top">
                            <h3 class="campaigns-title">{{ trans('email-report.body.campaigns.product_title') }}</h3>
                            <p class="campaigns-desc">{{ trans('email-report.body.campaigns.product_desc') }}</p>
                          </td>
                          <td class="statistic-value number-of-views" valign="top" align="right">
                            <h3 class="campaigns-views">{{ $product_view }}</h3>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <table>
                              <tr>
                                @if(!empty($product_data))
                                  @foreach($product_data as $product)
                                    <td width="70" height="70" style="padding-right: 10px;" valign="middle">
                                      <a href="{{ $product['link_url'] }}" style="position:relative;width: 100%;height: 100%;display:block;border:1px solid #eee;">
                                        <img src="{{ $product['cdn_url'] }}" alt="" style="width: 100%;height:100%;position:absolute;object-fit:contain;">
                                      </a>
                                    </td>
                                  @endforeach
                                @endif
                              </tr>
                            </table>
                          </td>
                          <td align="right">
                            <a href="{{ $product_list_page_url }}" class="see-all">See All</a>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>

                <table width="600" cellpadding="0" cellspacing="0" class="container">
                  <tr>
                    <td>
                      <hr style="border: 0; height: 1px;background-color:#ddd;margin-top:4px;margin-bottom:0;">
                    </td>
                  </tr>
                  <tr>
                    <td class="mobile" width="600">
                      <p class="help-text user-report-help-text">
                        {{ trans('email-report.body.help_line_1') }}
                      </p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <tr>
          <td height="30" align="center" class="separator">&nbsp;</td>
      </tr>

      <tr>
        <td align="center" valign="top">
          <table width="640" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#F2F2F2" style="margin-top: 20px;">
            <tr>
              <td align="center" valign="top">
                <table width="640" border="0" class="container footer-container" cellpadding="0" cellspacing="0">
                  <tr>
                    <td valign="top" width="400" class="mobile full-width">
                      <address class="footer-address">
                        {{ trans('email-report.footer.contact', $contact) }}
                        <br>
                        <br>
                      </address>
                    </td>

                    <td valign="top" width="240" class="mobile full-width footer-follow-container">
                      <p class="footer-follow">
                        {{ trans('email-report.footer.follow_us') }}
                      </p>
                      <a href="https://www.facebook.com/Gotomalls.Indo" class="contact-link" target="_blank">
                        <img src="https://cloudfront.gotomalls.com/uploads/emails/socmed-icon-02.png" alt="FB icon" class="contact-img" style="margin-left: 0;">
                      </a>

                      <a href="https://twitter.com/gotomalls" class="contact-link" target="_blank">
                        <img src="https://cloudfront.gotomalls.com/uploads/emails/socmed-icon-03.png" alt="Twitter icon" class="contact-img">
                      </a>

                      <a href="https://www.instagram.com/gotomalls/" class="contact-link" target="_blank">
                        <img src="https://cloudfront.gotomalls.com/uploads/emails/socmed-icon-04.png" alt="Instagram icon" class="contact-img">
                      </a>

                      <a href="https://www.youtube.com/gotomallscom" class="contact-link" target="_blank">
                        <img src="https://cloudfront.gotomalls.com/uploads/emails/socmed-icon-05.png" alt="Youtube icon" class="contact-img">
                      </a>

                      <a href="https://id.linkedin.com/company/gotomalls" class="contact-link" target="_blank">
                        <img src="https://cloudfront.gotomalls.com/uploads/emails/socmed-icon-06.png" alt="LinkedIn icon" class="contact-img" style="margin-right: 0;">
                      </a>
                    </td>
                  </tr>
                  <tr>
                    <td height="30">&nbsp;</td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
