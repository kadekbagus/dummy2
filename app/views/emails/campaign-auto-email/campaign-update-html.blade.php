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

      @if(count($updates) > 0)
      @foreach ($updates as $update)
      <tr>
        <td>{{ $update['column'] }}</td>
        <td>{{ $update['before'] }}</td>
        <td>{{ $update['after'] }}</td>
      </tr>
      @endforeach
      @endif
    </table>

</body>
</html>