<html>
<head>
<title>{{ $campaignType }} - {{ $campaignName }}</title>
</head>
<body>
    <p>On {{ $date }}, {{ $pmpUser }} has {{ $eventType }} {{ $campaignName }}.</p>
    <table border=1 width="50%">
      <tr align="left">
        <th>Field</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      @foreach($campaign_before->getAttributes() as $key => $value)
        @if(! in_array($key, ['news_name', 'description', 'image', 'promotion_name',
                              'promotion_id', 'news_id', 'merchant_id', 'promotion_type',
                              'long_description', 'location_id', 'location_type', 'is_all_retailer',
                              'is_all_retailer_redeem', 'is_all_employee_redeem', 'is_all_employee',
                              'is_redeemed_at_cs', 'coupon_notification', 'created_by', 'modified_by',
                              'created_at', 'updated_at', 'is_all_gender', 'is_all_age', 'is_permanent',
                              'is_coupon', 'mall_id', 'object_type', 'sticky_order', 'coupon_validity_in_days',
                              'link_object_type', 'maximum_issued_coupon_type', 'status', 'campaign_status_id',
                              'begin_date'
          ]))
          <tr>
            @if($key == 'is_popup')
              <td>{{ 'pop up in mobile' }}</td>
            @else
              <td>{{ $key }}</td>
            @endif
              <td>{{ $value }}</td>
              <td>{{ $campaign_after[$key] }}</td>
          </tr>
        @endif
      @endforeach

      @if($campaignType === 'Coupon')
      <tr>
        <td>{{ 'rule end date' }}</td>
        <td>{{ $campaign_before->couponRule->rule_end_date }}</td>
        <td>{{ $campaign_after->couponRule->rule_end_date }}</td>
      </tr>
      @endif
    </table>

    <h4>Campaign status:</h4>
    <table border=1 width="50%">
      <tr align="left">
        <th>Field</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      <tr>
        <td>{{ 'campaign status' }}</td>
        <td>
            {{ $campaign_before->campaign_status->campaign_status_name }}
        </td>
        <td>
            {{ $campaign_after->campaign_status->campaign_status_name }}
        </td>
      </tr>
    </table>

    <h4>Translations:</h4>
    <table border=1 width="50%">
      <tr align="left">
        <th>Field</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      @foreach($campaign_after->translations as $key1 => $translation_after)
      <tr><td colspan="3"><h5>{{$translation_after->language->name_long}}</h5></td></tr>
        @foreach($translation_after->getAttributes() as $key2 => $value)
          @if(in_array($key2, ['news_name', 'description', 'promotion_name']))
            <tr>
              @if($key2 == 'promotion_name')
                <td>{{ 'coupon name' }}</td>
              @elseif($key2 == 'news_name')
                  @if($campaignType == 'News')
                    <td>{{ 'news name' }}</td>
                  @else
                    <td>{{ 'promotion name' }}</td>
                  @endif
              @else
                <td>{{ $key2 }}</td>
              @endif
              <td>
              @foreach($campaign_before->translations as $key4 => $translation_before)
                @if($translation_after->language->name == $translation_before->language->name)
                  {{ $campaign_before->translations[$key4]->{$key2} }}
                @endif
              @endforeach
              </td>
              <td>{{ $value }}</td>
            </tr>
          @endif
        @endforeach

        @if($campaignType === 'News' || $campaignType === 'Promotion')
          <tr>
            <td>{{ 'image' }}</td>
            <td>
              @foreach($campaign_before->translations as $key4 => $translation_before)
                @if($translation_after->language->name == $translation_before->language->name)
                  @foreach($campaign_before->translations[$key4]->media as $key3 => $media)
                    @if($media->media_name_long === 'news_translation_image_orig')
                      {{ $media->path }}
                    @endif
                  @endforeach
                @endif
              @endforeach
            </td>
            <td>
            @foreach($campaign_after->translations[$key1]->media as $key3 => $media)
              @if($media->media_name_long === 'news_translation_image_orig')
                {{ $media->path }}
              @endif
            @endforeach
            </td>
          </tr>
        @else
          <tr>
            <td>{{ 'image' }}</td>
            <td>
              @foreach($campaign_before->translations as $key4 => $translation_before)
                @if($translation_after->language->name == $translation_before->language->name)
                  @foreach($campaign_before->translations[$key4]->media as $key3 => $media)
                    @if($media->media_name_long === 'coupon_translation_image_orig')
                      {{ $media->path }}
                    @endif
                  @endforeach
                @endif
              @endforeach
            </td>
            <td>
            @foreach($campaign_after->translations[$key1]->media as $key3 => $media)
              @if($media->media_name_long === 'coupon_translation_image_orig')
                {{ $media->path }}
              @endif
            @endforeach
            </td>
          </tr>
        @endif
      @endforeach
    </table>

    <h4>Keywords:</h4>
    <table border=1 width="50%">
      <tr align="left">
        <th>Field</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      <tr>
        <td>{{ 'keyword' }}</td>
        <?php
          $campaignBeforeNonFilteredKeyword = array();
          foreach($campaign_before->keywords as $key1 => $keyword1) {
              $campaignBeforeNonFilteredKeyword[] = $keyword1->keyword;
          }
          $campaignAfterNonFilteredKeyword = array();
          foreach($campaign_after->keywords as $key1 => $keyword2) {
              $campaignAfterNonFilteredKeyword[] = $keyword2->keyword;
          }
          // eliminate duplicate keywords
          $campaignBeforeFilteredKeyword = array();
          foreach($campaignBeforeNonFilteredKeyword as $keyword3) {
              if (! in_array($keyword3, $campaignBeforeFilteredKeyword)) {
                  $campaignBeforeFilteredKeyword[] = $keyword3;
              }
          }
          $campaignAfterFilteredKeyword = array();
          foreach($campaignAfterNonFilteredKeyword as $keyword4) {
              if (! in_array($keyword4, $campaignAfterFilteredKeyword)) {
                  $campaignAfterFilteredKeyword[] = $keyword4;
              }
          }
        ?>
        <td>
          {{ implode(', ', $campaignBeforeFilteredKeyword) }}
        </td>
        <td>
          {{ implode(', ', $campaignAfterFilteredKeyword) }}
        </td>
      </tr>
    </table>

    <h4>Campaign Profiling:</h4>
    <table border=1 width="50%">
      <tr align="left">
        <th>Field</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      <tr>
        <td>{{ 'age_range' }}</td>
        <td>
          @if(count($campaign_before->ages) > 0)
            @foreach($campaign_before->ages as $key1 => $campaignAge)
              {{ $campaignAge->range_name . (($key1 < count($campaign_before->ages) - 1) ? ', ' : '') }}
            @endforeach
          @else
            {{ 'All'}}
          @endif
        </td>
        <td>
          @if(count($campaign_after->ages) > 0)
            @foreach($campaign_after->ages as $key1 => $campaignAge)
              {{ $campaignAge->range_name . (($key1 < count($campaign_after->ages) - 1) ? ', ' : '') }}
            @endforeach
          @else
            {{ 'All'}}
          @endif
        </td>
      </tr>
    </table>
    <table border=1 width="50%">
      <tr align="left">
        <th>Field</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      <tr>
        <td>{{ 'gender' }}</td>
        <td>
          @if(count($campaign_before->genders) > 0)
            @foreach($campaign_before->genders as $key1 => $gender)
              {{ $gender->gender_value . (($key1 < count($campaign_before->genders) - 1) ? ', ' : '') }}
            @endforeach
          @else
            {{ 'All'}}
          @endif
        </td>
        <td>
          @if(count($campaign_after->genders) > 0)
            @foreach($campaign_after->genders as $key1 => $gender)
              {{ $gender->gender_value . (($key1 < count($campaign_after->genders) - 1) ? ', ' : '') }}
            @endforeach
          @else
            {{ 'All'}}
          @endif
        </td>
      </tr>
    </table>


    @if($campaignType === 'Coupon')
      <h4>Redemption Place:</h4>
      <table border=1 width="50%">
      <tr align="left">
        <th>Field</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      <tr>
        <td>{{ 'Redeem to tenants' }}</td>
        <td>
          @foreach($campaign_before->tenants as $key1 => $tenant)
            {{ $tenant->name . (($key1 < count($campaign_before->tenants) - 1) ? ', ' : '') }}
          @endforeach
        </td>
        <td>
          @foreach($campaign_after->tenants as $key1 => $tenant)
            {{ $tenant->name . (($key1 < count($campaign_after->tenants) - 1) ? ', ' : '') }}
          @endforeach
        </td>
      </tr>
    </table>
    <table border=1 width="50%">
      <tr align="left">
        <th>Field</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      <tr>
        <td>{{ 'Redeem to customer service' }}</td>
        <td>
          @foreach($campaign_before->employee as $key1 => $employees)
            {{ $employees->username . (($key1 < count($campaign_before->employee) - 1) ? ', ' : '') }}
          @endforeach
        </td>
        <td>
          @foreach($campaign_after->employee as $key1 => $employees)
            {{ $employees->username . (($key1 < count($campaign_after->employee) - 1) ? ', ' : '') }}
          @endforeach
        </td>
      </tr>
    </table>
    @endif

</body>
</html>