<tbody>
@foreach ($ads as $ad)
    <tr>
        <td colspan="1" style="font-weight:bold; padding: 10px; border-top: 2px solid #ccc;">
            {{ $ad->ad_name ?? $ad->ad_id }}<br>
            <small>{{ $ad->campaign_name }}</small>
        </td>
        <td colspan="5" style="padding: 10px; border-top: 2px solid #ccc;">
            <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 14px;">
                <thead style="background-color: #f3f3f3;">
                    <tr>
                        <th>Metric</th>
                        <th>Now (3d)</th>
                        <th>Prev (3d)</th>
                        <th>Δ %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Spend</td>
                        <td>${{ number_format($ad->spend_current, 2) }}</td>
                        <td>${{ number_format($ad->spend_previous, 2) }}</td>
                        <td>
                            @if ($ad->spend_diff !== null)
                                <span style="color: {{ $ad->spend_diff >= 0 ? 'green' : 'red' }};">
                                    {{ $ad->spend_diff >= 0 ? '↑' : '↓' }}{{ abs($ad->spend_diff) }}%
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>ROAS</td>
                        <td>{{ number_format($ad->roas_current, 2) }}</td>
                        <td>{{ number_format($ad->roas_previous, 2) }}</td>
                        <td>
                            @if ($ad->roas_diff !== null)
                                <span style="color: {{ $ad->roas_diff >= 0 ? 'green' : 'red' }};">
                                    {{ $ad->roas_diff >= 0 ? '↑' : '↓' }}{{ abs($ad->roas_diff) }}%
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>CPA</td>
                        <td>{{ number_format($ad->cpa_current, 2) }}</td>
                        <td>{{ number_format($ad->cpa_previous, 2) }}</td>
                        <td>
                            @if ($ad->cpa_diff !== null)
                                <span style="color: {{ $ad->cpa_diff <= 0 ? 'green' : 'red' }};">
                                    {{ $ad->cpa_diff <= 0 ? '↓' : '↑' }}{{ abs($ad->cpa_diff) }}%
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>CTR</td>
                        <td>{{ number_format($ad->ctr_current, 2) }}%</td>
                        <td>{{ number_format($ad->ctr_previous, 2) }}%</td>
                        <td>
                            @if ($ad->ctr_diff !== null)
                                <span style="color: {{ $ad->ctr_diff >= 0 ? 'green' : 'red' }};">
                                    {{ $ad->ctr_diff >= 0 ? '↑' : '↓' }}{{ abs($ad->ctr_diff) }}%
                                </span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>

    <!-- Daily Breakdown Table -->
    <tr>
        <td colspan="6" style="padding: 10px;">
            <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 13px;">
                <thead style="background-color: #eee;">
                    <tr>
                        <th>Date</th>
                        <th>Spend (Prev)</th>
                        <th>ROAS (Prev)</th>
                        <th>CPA (Prev)</th>
                        <th>CTR (Prev)</th>
                        <th>Spend (Now)</th>
                        <th>ROAS (Now)</th>
                        <th>CPA (Now)</th>
                        <th>CTR (Now)</th>
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
                            <td>{{ isset($ad->previous[$i]) ? '$' . number_format($ad->previous[$i]['spend'], 2) : '-' }}</td>
                            <td>{{ isset($ad->previous[$i]) ? number_format($ad->previous[$i]['roas'], 2) : '-' }}</td>
                            <td>{{ isset($ad->previous[$i]) ? number_format($ad->previous[$i]['cpa'], 2) : '-' }}</td>
                            <td>{{ isset($ad->previous[$i]) ? number_format($ad->previous[$i]['ctr'], 2) . '%' : '-' }}</td>
                            <td>{{ isset($ad->current[$i]) ? '$' . number_format($ad->current[$i]['spend'], 2) : '-' }}</td>
                            <td>{{ isset($ad->current[$i]) ? number_format($ad->current[$i]['roas'], 2) : '-' }}</td>
                            <td>{{ isset($ad->current[$i]) ? number_format($ad->current[$i]['cpa'], 2) : '-' }}</td>
                            <td>{{ isset($ad->current[$i]) ? number_format($ad->current[$i]['ctr'], 2) . '%' : '-' }}</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </td>
    </tr>
@endforeach
</tbody>
