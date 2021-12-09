                        <table width="100%">
                          <thead>
                            <tr>
                              <th colspan="2" class="uppercase reservation-table-title">
                                {{ trans('email-bill.labels.transaction_details', [], '', $lang) }}
                              </th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-bill.labels.transaction_id', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transaction['id'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-bill.labels.transaction_date', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transactionDateTime }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-bill.labels.billing_amount', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $bill->formatted_amount }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-bill.labels.convenience_fee', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transaction['formatted_convenience_fee'] }}</span>
                              </td>
                            </tr>
                            <tr>
                              <td class="mobile w-35 bold reservation-table-item-label">
                                <span class="p-8 block">
                                  {{ trans('email-bill.labels.total_amount', [], '', $lang) }}
                                </span>
                              </td>
                              <td class="mobile reservation-table-item-value">
                                <span class="p-8 block">{{ $transaction['total'] }}</span>
                              </td>
                            </tr>
                          </tbody>
                        </table>
