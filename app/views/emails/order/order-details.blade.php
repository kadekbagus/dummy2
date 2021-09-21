                        <table width="100%">
                          <thead>
                            <tr>
                              <th colspan="2" class="uppercase reservation-table-title">
                                {{ trans('email-order.labels.order_details', [], '', $lang) }}
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-order.labels.transaction_id', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transaction['id'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-order.labels.order_date', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transactionDateTime }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-order.labels.customer_name', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $customerName }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-order.labels.customer_email', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $customerEmail }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-order.labels.customer_phone', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $customerPhone }}</span>
                              </td>
                            </tr>

                            @if (isset($store) && ! empty($store))
                              <tr>
                                <td class="mobile bold reservation-table-item-label">
                                  <span class="p-8 block">
                                    {{ trans('email-order.labels.store_location', [], '', $lang) }}
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">
                                    {{ trans('email-order.labels.store_location_detail', $store, '', $lang) }}
                                  </span>
                                </td>
                              </tr>
                            @endif

                            {{-- <tr>
                              <td class="mobile bold reservation-table-item-label">
                                <span class="p-8 block">{{ trans('email-order.labels.total_payment', [], '', $lang) }}</span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transaction['total'] }}</span>
                              </td>
                            </tr> --}}
                          </tbody>
                        </table>
