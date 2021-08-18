                        <table width="100%">
                          <thead>
                            <tr>
                              <th colspan="4" class="uppercase reservation-table-title">
                                {{ trans('email-reservation.labels.product_details', [], '', $lang) }}
                              </th>
                            </tr>
                            <tr>
                              <th class="reservation-table-item-label text-left product-details-subtitle" width="40%">
                                {{ trans('email-reservation.labels.product_name', [], '', $lang) }}
                              </th>
                              <th class="reservation-table-item-label product-details-subtitle">
                                {{ trans('email-reservation.labels.product_variant', [], '', $lang) }}
                              </th>
                              <th class="reservation-table-item-label product-details-subtitle">
                                {{ trans('email-reservation.labels.quantity', [], '', $lang) }}
                              </th>
                              <th class="reservation-table-item-label product-details-subtitle" style="border-right: 1px solid #ddd;">
                                {{ trans('email-reservation.labels.product_price', [], '', $lang) }}
                              </th>
                            </tr>
                          </thead>
                          <tbody>

                            @foreach($products as $product)
                              <tr>
                                <td class="reservation-table-item-value" style="border-left: 1px solid #ddd;">
                                  <span class="p-8 block">
                                    {{ $product['name'] }}
                                  </span>
                                </td>
                                <td class="reservation-table-item-value">
                                  <span class="p-8 block">
                                    {{ $product['variant'] }}
                                    <small>
                                      <em>
                                        @if ($product['sku'])
                                          <br>
                                          {{ trans('email-order.labels.product_sku', [], '', $lang) }}:
                                          {{ $product['sku'] }}
                                        @endif

                                        @if ($product['barcode'])
                                          <br>
                                          {{ trans('email-order.labels.product_barcode', [], '', $lang) }}:
                                          {{ $product['barcode'] }}
                                        @endif
                                      </em>
                                    </small>
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block text-center">{{ $product['quantity'] }}</span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block text-center">{{ $product['total_price'] }}</span>
                                </td>
                              </tr>
                            @endforeach
                          </tbody>
                        </table>
