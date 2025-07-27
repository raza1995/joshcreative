<tbody>
@foreach ($ads as $ad)
    <tr>
        <td>{{ $ad->ad_name ?? $ad->ad_id }}</td>
        <td>{{ $ad->campaign_name }}</td>
        <td>${{ number_format($ad->spend_current_total, 2) }}</td>
        <td>
            @if ($ad->spend_diff !== null)
                <span class="{{ $ad->spend_diff >= 0 ? 'up' : 'down' }}">
                    {{ $ad->spend_diff >= 0 ? '↑' : '↓' }}{{ abs($ad->spend_diff) }}%
                </span>
            @else
                -
            @endif
        </td>
        <td>{{ $ad->roas_current_avg }}</td>
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

    <!-- Daily breakdown row -->
    <tr>
        <td colspan="6">
            <table width="100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Spend (Prev)</th>
                        <th>ROAS (Prev)</th>
                        <th>Spend (Now)</th>
                        <th>ROAS (Now)</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < max(count($ad->previous), count($ad->current)); $i++)
                        <tr>
                            <td>
                                @php
                                    $prevDate = $ad->previous[$i]['date'] ?? null;
                                    $currDate = $ad->current[$i]['date'] ?? null;
                                    echo $prevDate ? date('M j', strtotime($prevDate)) : ($currDate ? date('M j', strtotime($currDate)) : '-');
                                @endphp
                            </td>
                            <td>
                                {{ isset($ad->previous[$i]) ? '$' . number_format($ad->previous[$i]['spend'], 2) : '-' }}
                            </td>
                            <td>
                                {{ isset($ad->previous[$i]) ? number_format($ad->previous[$i]['roas'], 2) : '-' }}
                            </td>
                            <td>
                                {{ isset($ad->current[$i]) ? '$' . number_format($ad->current[$i]['spend'], 2) : '-' }}
                            </td>
                            <td>
                                {{ isset($ad->current[$i]) ? number_format($ad->current[$i]['roas'], 2) : '-' }}
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </td>
    </tr>
@endforeach
</tbody>
