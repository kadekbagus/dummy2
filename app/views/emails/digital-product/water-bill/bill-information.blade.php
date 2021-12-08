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
                                    {{ trans('email-bill.labels.water_bill.meter_start', [], '', $lang) }}
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $bill->meter_start }}</span>
                                </td>
                              </tr>

                              <tr>
                                <td class="mobile w-35 bold reservation-table-item-label">
                                  <span class="p-8 block">
                                    {{ trans('email-bill.labels.water_bill.meter_end', [], '', $lang) }}
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $bill->meter_end }}</span>
                                </td>
                              </tr>

                              <tr>
                                <td class="mobile w-35 bold reservation-table-item-label">
                                  <span class="p-8 block">
                                    {{ trans('email-bill.labels.water_bill.usage', [], '', $lang) }}
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $bill->usage }}</span>
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
                              {{-- <tr>
                                <td class="mobile w-35 bold reservation-table-item-label">
                                  <span class="p-8 block">
                                    {{ trans('email-bill.labels.convenience_fee', [], '', $lang) }}
                                  </span>
                                </td>
                                <td class="mobile reservation-table-item-value">
                                  <span class="p-8 block">{{ $bill->convenience_fee }}</span>
                                </td>
                              </tr> --}}
                            </tbody>

                            {{-- <tfoot>
                              <tr>
                                <td
                                  class="text-left"
                                  style="border:1px solid #ddd;border-right:0;">
                                  <span class="p-8 bold block">
                                    {{ trans('email-bill.labels.total_amount', [], '', $lang) }}
                                  </span>
                                </td>
                                <td class="text-left"
                                  style="border:1px solid #ddd;border-left: 0;">
                                  <span class="p-8 bold block">
                                    {{ $transaction['total'] }}
                                  </span>
                                </td>
                              </tr>
                            </tfoot> --}}
                          </table>
                        @endif
