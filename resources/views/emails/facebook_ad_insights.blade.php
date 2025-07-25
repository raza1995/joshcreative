<!DOCTYPE html>
<html>
<head>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        td, th {
            padding: 8px;
            border: 1px solid #ccc;
        }
        .up { color: green; }
        .down { color: red; }
    </style>
</head>
<body>
    <h2>Top 10 Facebook Ads: {{ $startDate->format('M j') }} – {{ $endDate->format('M j, Y') }}</h2>

    <table>
        <thead>
            <tr>
                <th>Ad Name</th>
                <th>Campaign</th>
                <th>Spend (Now)</th>
                <th>Spend Δ</th>
                <th>ROAS (Now)</th>
                <th>ROAS Δ</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($ads as $ad)
            <tr>
                <td>{{ $ad->ad_name ?? $ad->ad_id }}</td>
                <td>{{ $ad->campaign_name }}</td>
                <td>${{ number_format($ad->spend_current, 2) }}</td>
                <td>
                    @if ($ad->spend_diff !== null)
                        <span class="{{ $ad->spend_diff >= 0 ? 'up' : 'down' }}">
                            {{ $ad->spend_diff >= 0 ? '↑' : '↓' }}{{ abs($ad->spend_diff) }}%
                        </span>
                    @else
                        -
                    @endif
                </td>
                <td>{{ $ad->roas_current }}</td>
                <td>
                    @if ($ad->roas_diff !== null)
                        <span class="{{ $ad->roas_diff >= 0 ? 'up' : 'down' }}">
                            {{ $ad->roas_diff >= 0 ? '↑' : '↓' }}{{ abs($ad->roas_diff) }}%
                        </span>
                    @else
                        -
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
