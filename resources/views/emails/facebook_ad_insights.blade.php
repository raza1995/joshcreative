<tbody>
@foreach ($ads as $ad)
    {{--  ───────────  HEADER ROW (AD META + 4‑METRIC SUMMARY)  ───────────  --}}
    <tr>
        <td colspan="1" style="font-weight:bold;padding:10px;border-top:2px solid #ccc;">
            {{ $ad->ad_name ?: 'Unnamed Ad' }}<br>
            <small style="color:#555;">ID {{ $ad->ad_id }}</small><br>
            <small>Campaign: {{ $ad->campaign_name }}</small>
        </td>

        <td colspan="5" style="padding:10px;border-top:2px solid #ccc;">
            <table width="100%" cellpadding="6" cellspacing="0" border="1"
                   style="border-collapse:collapse;font-size:14px;">
                <thead style="background:#f3f3f3;">
                    <tr>
                        <th>Metric</th>
                        <th>Now (3 d)</th>
                        <th>Prev (3 d)</th>
                        <th>Δ %</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Spend --}}
                    <tr>
                        <td>Spend</td>
                        <td>${{ number_format($ad->spend_current, 2) }}</td>
                        <td>${{ number_format($ad->spend_previous, 2) }}</td>
                        <td>
                            @if(!is_null($ad->spend_diff))
                                <span style="color:{{ $ad->spend_diff >= 0 ? 'green' : 'red' }};">
                                    {{ $ad->spend_diff >= 0 ? '↑' : '↓' }}{{ abs($ad->spend_diff) }}%
                                </span>
                            @else – @endif
                        </td>
                    </tr>

                    {{-- ROAS --}}
                    <tr>
                        <td>ROAS</td>
                        <td>{{ number_format($ad->roas_current, 2) }}</td>
                        <td>{{ number_format($ad->roas_previous, 2) }}</td>
                        <td>
                            @if(!is_null($ad->roas_diff))
                                <span style="color:{{ $ad->roas_diff >= 0 ? 'green' : 'red' }};">
                                    {{ $ad->roas_diff >= 0 ? '↑' : '↓' }}{{ abs($ad->roas_diff) }}%
                                </span>
                            @else – @endif
                        </td>
                    </tr>

                    {{-- CPA (lower is better) --}}
                    <tr>
                        <td>CPA</td>
                        <td>{{ number_format($ad->cpa_current, 2) }}</td>
                        <td>{{ number_format($ad->cpa_previous, 2) }}</td>
                        <td>
                            @if(!is_null($ad->cpa_diff))
                                <span style="color:{{ $ad->cpa_diff <= 0 ? 'green' : 'red' }};">
                                    {{ $ad->cpa_diff <= 0 ? '↓' : '↑' }}{{ abs($ad->cpa_diff) }}%
                                </span>
                            @else – @endif
                        </td>
                    </tr>

                    {{-- CTR --}}
                    <tr>
                        <td>CTR</td>
                        <td>{{ number_format($ad->ctr_current, 2) }}%</td>
                        <td>{{ number_format($ad->ctr_previous, 2) }}%</td>
                        <td>
                            @if(!is_null($ad->ctr_diff))
                                <span style="color:{{ $ad->ctr_diff >= 0 ? 'green' : 'red' }};">
                                    {{ $ad->ctr_diff >= 0 ? '↑' : '↓' }}{{ abs($ad->ctr_diff) }}%
                                </span>
                            @else – @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>

    {{--  ───────────  DAILY BREAKDOWN ROW  ───────────  --}}
    <tr>
        <td colspan="6" style="padding:10px;">
            <table width="100%" cellpadding="6" cellspacing="0" border="1"
                   style="border-collapse:collapse;font-size:13px;">
                <thead style="background:#eee;">
                    <tr>
                        <th>Date</th>
                        <th>Spend Prev</th>
                        <th>ROAS Prev</th>
                        <th>CPA Prev</th>
                        <th>CTR Prev</th>

                        <th>Spend Now</th>
                        <th>ROAS Now</th>
                        <th>CPA Now</th>
                        <th>CTR Now</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // line up both 3‑day blocks by date key so the rows stay in order
                        $rows = collect($ad->previous_rows)
                                ->keyBy('date')
                                ->merge(
                                    collect($ad->current_rows)->keyBy('date')
                                )
                                ->sortKeys();   // Asc order (older → newer)
                    @endphp

                    @foreach ($rows as $date => $null)
                        @php
                            $prev = collect($ad->previous_rows)->firstWhere('date',$date);
                            $curr = collect($ad->current_rows )->firstWhere('date',$date);
                        @endphp
                        <tr>
                            {{-- Date Col --}}
                            <td>{{ date('M j', strtotime($date)) }}</td>

                            {{-- Previous window --}}
                            <td>{{ $prev ? '$'.number_format($prev['spend'],2) : '–' }}</td>
                            <td>{{ $prev ? number_format($prev['roas'],2) : '–' }}</td>
                            <td>{{ $prev ? number_format($prev['cpa'],2)  : '–' }}</td>
                            <td>{{ $prev ? number_format($prev['ctr'],2).'%' : '–' }}</td>

                            {{-- Current window --}}
                            <td>{{ $curr ? '$'.number_format($curr['spend'],2) : '–' }}</td>
                            <td>{{ $curr ? number_format($curr['roas'],2) : '–' }}</td>
                            <td>{{ $curr ? number_format($curr['cpa'],2)  : '–' }}</td>
                            <td>{{ $curr ? number_format($curr['ctr'],2).'%' : '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>
@endforeach
</tbody>
