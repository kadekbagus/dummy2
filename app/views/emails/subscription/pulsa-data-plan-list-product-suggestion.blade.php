                @if (count($products) > 0)
                  <tr>
                    <td class="">
                      <h3 class="suggestion-list-title">Check Out Our Hot Product List!</h3>
                    </td>
                  </tr>
                  <?php $itemPerRow = 2; ?>
                  @foreach($products as $chunk)
                    <tr>
                      <td align="" valign="top">

                        <table width="600" cellpadding="0" cellspacing="0" border="0" class="mobile">
                          <tr>

                            @foreach($chunk as $index => $product)
                              <td width="300" class="mobile" align="left" valign="top">
                                <a href="{{ $product['detail_url'] }}" style="text-decoration: none;">
                                  <div class="suggestion-list-item {{ ($index+1) % $itemPerRow === 0 ? 'even' : 'odd' }}">
                                    <table width="100%">
                                      <tbody>
                                        <tr>
                                          <td valign="top" class="suggestion-list-item-img-container">
                                            <span class="suggestion-list-item-img">
                                              <img src="{{ $product['image_url'] }}" alt="Logo" style="-ms-interpolation-mode:bicubic;">
                                            </span>
                                          </td>
                                          <td valign="top" class="suggestion-list-item-info">
                                            <table width="100%">
                                              <tbody>
                                                <tr>
                                                  <td valign="top">
                                                    <h4 style="margin-bottom: 0;margin-top:0;line-height:1.3em;color:#444;"  class="suggestion-list-item-title">{{ $product['name'] }}</h4>
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

                      <table width="600" cellpadding="0" cellspacing="0" border="0" class="mobile">
                        <tr>
                          <td colspan="2" height="20" valign="middle">
                            <a href="{{ $campaignListUrl }}" target="_blank" style="color: #f43d3c;text-decoration: none;" class="suggestion-list-more-url">
                              {{ trans('email-subscription.pulsa.body.buttons.see_more_events', [], '', 'id') }}
                            </a>
                          </td>
                        </tr>
                      </table>

                    </td>
                  </tr>
                  <tr>
                    <td height="30" align="center" class="separator">&nbsp;</td>
                  </tr>
                @endif
