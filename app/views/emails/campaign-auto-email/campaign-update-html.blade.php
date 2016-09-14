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
        @if(! in_array($key, ['news_name', 'description', 'image', 'promotion_name']))
      <tr>
        <td>{{ $key }}</td>
        <td>{{ $value }}</td>
        <td>{{ $campaign_after[$key] }}</td>
      </tr>
        @endif
      @endforeach
    </table>

    <h4>Translations:</h4>
    <table border=1 width="50%">
      <tr align="left">
        <th>Field</th>
        <th>Before</th>
        <th>After</th>
      </tr>
      @foreach($campaign_before->translations as $key1 => $translation)
      <tr><td colspan="3"><h5>{{$translation->language->name_native}}</h5></td></tr>
        @foreach($translation->getAttributes() as $key2 => $value)
          @if(in_array($key2, ['news_name', 'description', 'promotion_name']))
      <tr>
        <td>{{ $key2 }}</td>
        <td>{{ $value }}</td>
        <td>{{ $campaign_after->translations[$key1]->{$key2} }}</td>
      </tr>
          @endif
        @endforeach
        <tr><td colspan="3"><h6>Image</h6></td></tr>
        @foreach($translation->media as $key3 => $media)

            @if($campaignType === 'News' || $campaignType === 'Promotion')
              @if($media->media_name_long === 'news_translation_image_orig')
                <tr>
                  <td>{{ 'path' }}</td>
                  <td>{{ $media->path }}</td>
                  <td>{{ $campaign_after->translations[$key1]->media[$key3]->path }}</td>
                </tr>
              @endif
            @else
              @if($media->media_name_long === 'coupon_translation_image_orig')
                <tr>
                  <td>{{ 'path' }}</td>
                  <td>{{ $media->path }}</td>
                  <td>{{ $campaign_after->translations[$key1]->media[$key3]->path }}</td>
                </tr>
              @endif
            @endif

        @endforeach

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
        <td>
          @foreach($campaign_before->keywords as $key1 => $keyword)
            {{ $keyword->keyword . (($key1 < count($campaign_before->keywords) - 1) ? ', ' : '') }}
          @endforeach
        </td>
        <td>
          @foreach($campaign_after->keywords as $key1 => $keyword)
            {{ $keyword->keyword . (($key1 < count($campaign_after->keywords) - 1) ? ', ' : '') }}
          @endforeach
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
          @foreach($campaign_before->ages as $key1 => $campaignAge)
            {{ $campaignAge->range_name . (($key1 < count($campaign_before->ages) - 1) ? ', ' : '') }}
          @endforeach
        </td>
        <td>
          @foreach($campaign_after->ages as $key1 => $campaignAge)
            {{ $campaignAge->range_name . (($key1 < count($campaign_after->ages) - 1) ? ', ' : '') }}
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
        <td>{{ 'gender' }}</td>
        <td>
          @foreach($campaign_before->genders as $key1 => $gender)
            {{ $gender->gender_value . (($key1 < count($campaign_before->genders) - 1) ? ', ' : '') }}
          @endforeach
        </td>
        <td>
          @foreach($campaign_after->genders as $key1 => $gender)
            {{ $gender->gender_value . (($key1 < count($campaign_after->genders) - 1) ? ', ' : '') }}
          @endforeach
        </td>
      </tr>
    </table>
</body>
</html>