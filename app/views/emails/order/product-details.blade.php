                        <table width="100%" style="margin-bottom: 10px;">
                          <thead>
                            <tr>
                              <th colspan="3" class="uppercase reservation-table-title">
                                {{ trans('email-order.labels.product_details', [], '', $lang) }}
                              </th>
                            </tr>
                            {{-- <tr>
                              <th class="reservation-table-item-label text-left product-details-subtitle" width="50%">
                                {{ trans('email-order.labels.product_name', [], '', $lang) }}
                              </th> --}}
                              {{-- <th>{{ trans('email-order.labels.product_variant', [], '', $lang) }}</th> --}}
                              {{-- <th>{{ trans('email-order.labels.product_sku', [], '', $lang) }}</th> --}}
                              {{-- <th class="reservation-table-item-label product-details-subtitle">
                                {{ trans('email-order.labels.quantity', [], '', $lang) }}
                              </th>
                              <th class="reservation-table-item-label product-details-subtitle" style="border-right: 1px solid #ddd;">
                                {{ trans('email-order.labels.product_price', [], '', $lang) }}
                              </th> --}}
                            </tr>
                          </thead>

                          <tbody>
                            @foreach($transaction['orders'] as $index => $order)

                              @if ($index > 0)
                                <tr>
                                  <td colspan="3" style="height:3px;background-color:#ddd;border-left:1px solid #ddd;"></td>
                                </tr>
                              @endif

                              <tr>
                                <td width="50%"
                                  style="border-right: 0;font-weight:bold;color:#444;"
                                  class="reservation-table-item-label text-left product-details-subtitle">
                                  {{ trans('email-order.labels.store_location_detail', $order['store'], '', $lang) }}
                                </td>
                                <td colspan="2"
                                  style="border-left: 0;border-right:1px solid #ddd;font-weight:bold;color:#444;"
                                  class="reservation-table-item-label product-details-subtitle text-right">
                                  {{ trans('email-order.labels.order_id', [], '', $lang ) }}: {{ $order['id'] }}
                                </td>
                              </tr>

                              @foreach($order['items'] as $item)
                                <tr>
                                  <td class="reservation-table-item-value" style="border-left: 1px solid #ddd;">
                                    <span class="p-8 block">
                                      {{ $item['name'] }}
                                      <br>
                                      <em>
                                        <small>
                                          {{ trans('email-order.labels.product_variant', [], '', $lang) }}:
                                          {{ $item['variant'] }} {{-- $item['sku'] --}}
                                        </small>
                                      </em>
                                    </span>
                                  </td>
                                  <td class="reservation-table-item-value">
                                    <span class="p-8 block text-center">{{ $item['quantity'] }}</span>
                                  </td>
                                  <td class="reservation-table-item-value">
                                    <span class="p-8 block text-center">{{ $item['total'] }}</span>
                                  </td>
                                </tr>
                              @endforeach

                            @endforeach
                          </tbody>

                          <tfoot>
                            <tr>
                              <td colspan="2"
                                class="text-right"
                                style="border:1px solid #ddd;border-right:0;">
                                <span class="p-8 bold block">
                                  {{ trans('email-order.labels.total_payment', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="text-center"
                                style="border:1px solid #ddd;">
                                <span class="p-8 bold block">
                                  {{ $transaction['total'] }}
                                </span>
                              </td>
                            </tr>
                          </tfoot>
                        </table>
