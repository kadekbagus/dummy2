                        @if (! empty($bill))
                          <table width="100%" style="margin-bottom: 10px;">
                            <thead>
                              <tr>
                                <th colspan="2" class="uppercase reservation-table-title">
                                  {{ trans('email-bill.labels.billing_details', [], '', $lang) }}
                                </th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td class="mobile w-35 bold reservation-table-item-label">
                                  <span class="p-8 block">
                                    {{ trans('email-bill.labels.billing_id', [], '', $lang) }}
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $bill->billing_id }}</span>
                                </td>
                              </tr>
                              <tr>
                                <td class="mobile w-35 bold reservation-table-item-label">
                                  <span class="p-8 block">
                                    {{ trans('email-bill.labels.billing_name', [], '', $lang) }}
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $bill->customer_name }}</span>
                                </td>
                              </tr>

                              <tr>
                                <td class="mobile w-35 bold reservation-table-item-label">
                                  <span class="p-8 block">
                                    {{ trans('email-bill.labels.water_bill.periode', [], '', $lang) }}
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $bill->period }}</span>
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
                            </tbody>
                          </table>
                        @endif
