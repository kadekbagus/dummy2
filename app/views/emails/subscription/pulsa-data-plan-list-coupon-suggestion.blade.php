                @if (count($campaigns) > 0)
                  <tr>
                    <td height="30">&nbsp;</td>
                  </tr>
                  <tr>
                    <td class="">
                      <h3 style="color: #444;margin: 0;margin-bottom: 10px;" class="suggestion-list-title">Vouchers</h3>
                    </td>
                  </tr>
                  <?php $itemPerRow = 2; ?>
                  @foreach($campaigns as $chunk)
                    <tr>
                      <td align="center" valign="top">

                        <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                          <tr>

                            @foreach($chunk as $index => $campaign)

                              <td width="300" class="mobile" align="left" valign="middle" style="">
                                <a href="{{ $campaign['detail_url'] }}" style="text-decoration: none;">
                                  <div class="suggestion-list-item {{ ($index+1) % $itemPerRow === 0 ? 'even' : 'odd' }}">

                                    <table>
                                      <tbody>
                                        <tr>
                                          <td width="90" valign="top">
                                            <img src="{{ $campaign['image_url'] }}" alt="Logo" style="-ms-interpolation-mode:bicubic;border:1px solid #eee;" class="suggestion-list-item-img">
                                          </td>
                                          <td width="210" valign="top">
                                            <table width="100%">
                                              <tbody>
                                                <tr>
                                                  <td colspan="2" valign="top">
                                                    <h4 style="margin-bottom: 0;margin-top:0;line-height:1.3em;color:#444;"  class="suggestion-list-item-title">{{ $campaign['name'] }}</h4>
                                                    <p style="margin-top:5px;color:#444;" class="suggestion-list-item-location">{{ $campaign['store_names'] }}</p>
                                                  </td>
                                                </tr>
                                                <tr style="font-size: 14px;">
                                                  <td align="left" valign="top">
                                                    <span style="color: #888;text-decoration:line-through;" class="suggestion-list-item-price">{{ $campaign['price_old'] }}</span>
                                                  </td>
                                                  <td align="right" valign="top">
                                                    <span style="color: #2196f3" class="suggestion-list-item-price">{{ $campaign['price_selling'] }}</span>
                                                  </td>
                                                </tr>
                                              </tbody>
                                            </table>
                                          </td>
                                        </tr>
                                      </tbody>
                                    </table>
                                  </div>
                                </a>

                              </td>

                            @endforeach

                          </tr>
                        </table>
                      </td>
                    </tr>
                  @endforeach

                  <tr>
                    <td align="center" valign="top">

                      <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                        <tr>
                          <td colspan="2" height="20" valign="middle">
                            <a href="{{ $campaignListUrl }}" target="_blank" style="color: #f43d3c;text-decoration: none;" class="suggestion-list-more-url">
                              {{ trans('email-subscription.pulsa.body.buttons.see_more_vouchers', [], '', 'id') }}
                            </a>
                          </td>
                        </tr>
                      </table>

                    </td>
                  </tr>
                @endif
