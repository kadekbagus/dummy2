                @if (count($campaigns) > 0)
                  <tr>
                    <td height="30">&nbsp;</td>
                  </tr>
                  <tr>
                    <td class="">
                      <h3 style="color: #444;margin: 0;margin-bottom: 10px;font-size: 14pt;">Events</h3>
                    </td>
                  </tr>
                  <?php $itemCounter = 0; ?>
                  <?php $itemPerRow = 2; ?>
                  @foreach($campaigns as $campaign)

                    <?php $itemCounter++; ?>
                    <?php $newRow = $itemCounter % $itemPerRow === 1 || $itemCounter === 1; ?>
                    <?php $lastItemInRow = $itemCounter % $itemPerRow === 0; ?>
                    @if ($newRow)
                      <tr>
                        <td align="center" valign="top">

                          <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                            <tr>
                    @endif


                              <td width="300" class="mobile" align="left" valign="top" style="">
                                <div class="suggestion-list-item {{ $itemCounter % $itemPerRow === 0 ? 'even' : 'odd' }}">

                                  <table>
                                    <tbody>
                                      <tr>
                                        <td width="90" valign="top">
                                          <img src="{{ $campaign['image_url'] }}" alt="Logo" style="-ms-interpolation-mode:bicubic;width:80px;height:80px;border:1px solid #eee;">
                                        </td>
                                        <td width="210" valign="top">
                                          <table width="100%">
                                            <tbody>
                                              <tr>
                                                <td colspan="2" valign="top">
                                                  <h4 style="margin-bottom: 0;margin-top:0;line-height:1.3em;font-size:15px;color:#444;">{{ $campaign['name'] }}</h4>
                                                  <p style="font-size: 12px;margin-top:5px;color:#444;">{{ $campaign['location'] }}</p>
                                                </td>
                                              </tr>
                                            </tbody>
                                          </table>
                                        </td>
                                      </tr>
                                    </tbody>
                                  </table>
                                </div>

                              </td>
                    @if ($lastItemInRow)
                            </tr>
                          </table>
                        </td>
                      </tr>
                    @endif
                  @endforeach

                  <tr>
                    <td align="center" valign="top">

                      <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                        <tr>
                          <td colspan="2" height="20" valign="middle">
                            <a href="{{ $campaignListUrl }}" target="_blank" style="color: #f43d3c;text-decoration: none;font-size:14px;">
                              {{ trans('email-subscription.pulsa.body.buttons.see_more_events', [], '', 'id') }}
                            </a>
                          </td>
                        </tr>
                      </table>

                    </td>
                  </tr>
                @endif
