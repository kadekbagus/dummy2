              <table width="640" class="container mobile-full-width">
                <tr>
                  <td class="rewards-container" align="center">
                    <table width="600" class="container text-center" cellspacing="0" cellpadding="0" border="0">
                      <tr>
                        <td class="text-center">
                          <h2 class="rewards-heading">
                            {{{ trans('email-purchase-rewards.coupon.heading', [], '', $lang) }}}
                          </h2>

                          <p class="rewards-section greeting-text">
                            {{{ trans('email-purchase-rewards.coupon.section_1', ['rewardsCount' => count($rewards)], '', $lang ) }}}
                          </p>
                          <br>

                          <a href="{{ $redeemUrl }}" class="btn btn-light" role="button">
                              {{{ trans('email-purchase-rewards.coupon.btn_my_wallet', [], '', $lang) }}}
                          </a>
                        </td>
                      </tr>

                      <tr>
                        <td>
                          <br>
                          <br>

                          <div class="rewards-list-container">
                            @foreach(array_chunk($rewards, 3) as $rewardChunks)
                              @foreach($rewardChunks as $reward)
                                <div class="rewards-item text-left">
                                  <img class="rewards-item-img" src="{{ $reward['image_url'] }}">
                                  <div class="rewards-item-name">
                                    {{{ $reward['name'] }}}
                                  </div>
                                </div>
                              @endforeach
                              <div class="desktop-spacer"></div>
                            @endforeach
                          </div>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
