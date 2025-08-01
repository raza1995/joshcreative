<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, sans-serif; font-size: 14px; border-collapse: collapse;">
    <tbody>
    @foreach ($ads as $ad)
        {{-- ───── AD HEADER ROW ───── --}}
        <tr>
            <td colspan="6" style="padding: 15px; border-top: 2px solid #ccc;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        {{-- Meta Info --}}
                        <td valign="top" style="width: 70%;">
                            <strong style="font-size: 16px;">{{ $ad->ad_name ?: 'Unnamed Ad' }}</strong><br>
                            <span style="color: #555;">Ad ID: {{ $ad->ad_id }}</span><br>
                            <span>Campaign: {{ $ad->campaign_name }}</span>
                        </td>
                        {{-- Thumbnail --}}
                        <td align="right" valign="top" style="width: 30%;">
                            @if (!empty($ad->thumbnail_url))
                                <a href="{{ $ad->ad_link }}" target="_blank">
                                    <img src="{{ $ad->thumbnail_url }}"
                                         alt="Ad Image"
                                         style="max-width: 120px; max-height: 120px; object-fit: contain; border: 1px solid #ccc;">
                                </a>
                            @else
                                <span style="color: #aaa;">No Image</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- ───── AGGREGATE METRICS ROW ───── --}}
        <tr>
            <td colspan="6" style="padding: 0 15px 15px 15px;">
                <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 13px; width: 100%;">
                    <thead style="background-color: #f3f3f3;">
                        <tr>
                            <th align="left">Metric</th>
                            <th>Now ({{ $windowDays }}d)</th>
                            <th>Prev ({{ $windowDays }}d)</th>
                            <th>Δ %</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Spend --}}
                        <tr>
                            <td>Spend</td>
                            <td>${{ number_format($ad->spend_current, 2) }}</td>
                            <td>${{ number_format($ad->spend_previous, 2) }}</td>
                            <td>{!! $ad->spend_diff !== null ? '<span style="color:' . ($ad->spend_diff >= 0 ? 'green' : 'red') . ';">' . ($ad->spend_diff >= 0 ? '↑' : '↓') . abs($ad->spend_diff) . '%</span>' : '–' !!}</td>
                        </tr>

                        {{-- ROAS --}}
                        <tr>
                            <td>ROAS</td>
                            <td>{{ number_format($ad->roas_current, 2) }}</td>
                            <td>{{ number_format($ad->roas_previous, 2) }}</td>
                            <td>{!! $ad->roas_diff !== null ? '<span style="color:' . ($ad->roas_diff >= 0 ? 'green' : 'red') . ';">' . ($ad->roas_diff >= 0 ? '↑' : '↓') . abs($ad->roas_diff) . '%</span>' : '–' !!}</td>
                        </tr>

                        {{-- CPA --}}
                        <tr>
                            <td>CPA</td>
                            <td>{{ number_format($ad->cpa_current, 2) }}</td>
                            <td>{{ number_format($ad->cpa_previous, 2) }}</td>
                            <td>{!! $ad->cpa_diff !== null ? '<span style="color:' . ($ad->cpa_diff <= 0 ? 'green' : 'red') . ';">' . ($ad->cpa_diff <= 0 ? '↓' : '↑') . abs($ad->cpa_diff) . '%</span>' : '–' !!}</td>
                        </tr>

                        {{-- CTR --}}
                        <tr>
                            <td>CTR</td>
                            <td>{{ number_format($ad->ctr_current, 2) }}%</td>
                            <td>{{ number_format($ad->ctr_previous, 2) }}%</td>
                            <td>{!! $ad->ctr_diff !== null ? '<span style="color:' . ($ad->ctr_diff >= 0 ? 'green' : 'red') . ';">' . ($ad->ctr_diff >= 0 ? '↑' : '↓') . abs($ad->ctr_diff) . '%</span>' : '–' !!}</td>
                        </tr>
                        {{-- Reach --}}
                        <tr>
                            <td>Reach</td>
                            <td>{{ number_format($ad->reach_current) }}</td>
                            <td>{{ number_format($ad->reach_previous) }}</td>
                            <td>{!! $ad->reach_diff !== null ? '<span style="color:' . ($ad->reach_diff >= 0 ? 'green' : 'red') . ';">' . ($ad->reach_diff >= 0 ? '↑' : '↓') . abs($ad->reach_diff) . '%</span>' : '–' !!}</td>
                        </tr>
                        {{-- Frequency --}}
                        <tr>
                            <td>Frequency</td>
                            <td>{{ number_format($ad->frequency_current, 2) }}</td>
                            <td>{{ number_format($ad->frequency_previous, 2) }}</td>
                            <td>{!! $ad->frequency_diff !== null ? '<span style="color:' . ($ad->frequency_diff >= 0 ? 'red' : 'green') . ';">' . ($ad->frequency_diff >= 0 ? '↑' : '↓') . abs($ad->frequency_diff) . '%</span>' : '–' !!}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>

        {{-- ───── DAILY DRILLDOWN ───── --}}
        <tr>
            <td colspan="6" style="padding: 0 15px 30px 15px;">
                <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 12px;">
                    <thead style="background-color: #eee;">
                        <tr>
                            <th>Date</th>
                            <th>Spend Prev</th>
                            <th>ROAS Prev</th>
                            <th>CPA Prev</th>
                            <th>CTR Prev</th>
                            <th>Reach Prev</th>
                            <th>Freq Prev</th>
                            <th>Spend Now</th>
                            <th>ROAS Now</th>
                            <th>CPA Now</th>
                            <th>CTR Now</th>
                            <th>Reach Now</th>
                            <th>Freq Now</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $dates = collect($ad->previous_rows)->pluck('date')->merge(collect($ad->current_rows)->pluck('date'))->unique()->sort();
                        @endphp

                        @foreach ($dates as $date)
                            @php
                                $prev = collect($ad->previous_rows)->firstWhere('date', $date);
                                $curr = collect($ad->current_rows)->firstWhere('date', $date);
                            @endphp
                           <tr>
                                <td>{{ \Carbon\Carbon::parse($date)->format('M j') }}</td>
                                <td>{{ $prev ? '$' . number_format($prev['spend'], 2) : '–' }}</td>
                                <td>{{ $prev ? number_format($prev['roas'], 2) : '–' }}</td>
                                <td>{{ $prev ? number_format($prev['cpa'], 2) : '–' }}</td>
                                <td>{{ $prev ? number_format($prev['ctr'], 2) . '%' : '–' }}</td>
                                <td>{{ $prev ? number_format($prev['reach']) : '–' }}</td>
                                <td>{{ $prev ? number_format($prev['frequency'], 2) : '–' }}</td>

                                <td>{{ $curr ? '$' . number_format($curr['spend'], 2) : '–' }}</td>
                                <td>{{ $curr ? number_format($curr['roas'], 2) : '–' }}</td>
                                <td>{{ $curr ? number_format($curr['cpa'], 2) : '–' }}</td>
                                <td>{{ $curr ? number_format($curr['ctr'], 2) . '%' : '–' }}</td>
                                <td>{{ $curr ? number_format($curr['reach']) : '–' }}</td>
                                <td>{{ $curr ? number_format($curr['frequency'], 2) : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
