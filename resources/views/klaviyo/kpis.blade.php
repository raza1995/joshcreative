@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto p-6">
  <h1 class="text-2xl font-bold mb-4">Klaviyo KPIs</h1>

  <!-- Controls -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div>
      <label class="block text-sm font-medium">Mode</label>
      <select id="mode" class="w-full border rounded p-2">
        <option value="campaigns">Campaigns</option>
        <option value="segments">Segments</option>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium">Start Date</label>
      <input id="start_date" type="date" class="w-full border rounded p-2" />
    </div>

    <div>
      <label class="block text-sm font-medium">End Date</label>
      <input id="end_date" type="date" class="w-full border rounded p-2" />
    </div>

    <div class="flex items-end gap-2">
      <button id="btn-preset-7" class="border rounded px-3 py-2">Last 7d</button>
      <button id="btn-preset-30" class="border rounded px-3 py-2">Last 30d</button>
      <button id="btn-fetch" class="bg-black text-white rounded px-4 py-2">Fetch</button>
    </div>
  </div>

  <!-- Selection -->
  <div id="select-row" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div>
      <label class="block text-sm font-medium">Campaigns</label>
      <select id="campaign_ids" class="w-full border rounded p-2" multiple size="8"></select>
    </div>
    <div>
      <label class="block text-sm font-medium">Segments</label>
      <select id="segment_ids" class="w-full border rounded p-2" multiple size="8"></select>
    </div>
  </div>

  <!-- Results -->
  <div id="results" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
</div>

<script>
(function(){
  const csrf = '{{ csrf_token() }}';
  const modeEl = document.getElementById('mode');
  const startEl = document.getElementById('start_date');
  const endEl = document.getElementById('end_date');
  const resEl = document.getElementById('results');
  const segEl = document.getElementById('segment_ids');
  const campEl = document.getElementById('campaign_ids');

  function fmt(n){ return Number(n ?? 0).toLocaleString(); }
  function pct(n){ if(n === null || n === undefined) return '—'; return (Number(n)).toFixed(2) + '%'; }

  // date presets
  function setPreset(days){
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - (days - 1));
    startEl.value = start.toISOString().slice(0,10);
    endEl.value = end.toISOString().slice(0,10);
  }
  document.getElementById('btn-preset-7').onclick = ()=> setPreset(7);
  document.getElementById('btn-preset-30').onclick = ()=> setPreset(30);

  // options load
  async function loadOptions(){
    const r = await fetch('{{ route('klaviyo.options') }}');
    const j = await r.json();

    // campaigns
    campEl.innerHTML = '';
    (j.campaigns || []).forEach(c=>{
      const opt = document.createElement('option');
      opt.value = c.id; opt.textContent = `${c.name || c.id}`;
      campEl.appendChild(opt);
    });

    // segments
    segEl.innerHTML = '';
    (j.segments || []).forEach(s=>{
      const opt = document.createElement('option');
      const countText = (s.profile_count != null) ? ` · ${s.profile_count} members` : '';
      opt.value = s.id; opt.textContent = `${s.name || s.id}${countText}`;
      segEl.appendChild(opt);
    });

    // defaults
    setPreset(7);
    toggleMode(); // to hide irrelevant selector
  }

  function selectedValues(sel){
    return Array.from(sel.selectedOptions).map(o => o.value);
  }

  function toggleMode(){
    const mode = modeEl.value;
    if(mode === 'campaigns'){
      campEl.closest('div').style.display = 'block';
      segEl.closest('div').style.display = 'none';
    } else {
      campEl.closest('div').style.display = 'none';
      segEl.closest('div').style.display = 'block';
    }
    resEl.innerHTML = '';
  }
  modeEl.addEventListener('change', toggleMode);

  // renderers
  function renderCampaignCards(rows){
    resEl.innerHTML = '';
    if(!rows || !rows.length){
      resEl.innerHTML = '<div class="text-gray-500">No data.</div>'; return;
    }
    rows.forEach(r=>{
      const html = `
      <div class="border rounded-lg p-4 shadow-sm">
        <div class="font-semibold text-lg mb-2">${r.name || r.campaign_id}</div>
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div class="p-3 rounded bg-gray-50">
            <div class="text-gray-500">Recipients</div><div class="font-semibold">${fmt(r.recipients)}</div>
          </div>
          <div class="p-3 rounded bg-gray-50">
            <div class="text-gray-500">Delivered</div><div class="font-semibold">${fmt(r.delivered)}</div>
          </div>
          <div class="p-3 rounded bg-gray-50">
            <div class="text-gray-500">Opens</div><div class="font-semibold">${fmt(r.opens)}</div>
          </div>
          <div class="p-3 rounded bg-gray-50">
            <div class="text-gray-500">Open Rate</div><div class="font-semibold">${pct(r.open_rate)}</div>
          </div>
          <div class="p-3 rounded bg-gray-50">
            <div class="text-gray-500">Clicks</div><div class="font-semibold">${fmt(r.clicks)}</div>
          </div>
          <div class="p-3 rounded bg-gray-50">
            <div class="text-gray-500">Click Rate</div><div class="font-semibold">${pct(r.click_rate)}</div>
          </div>
          <div class="p-3 rounded bg-gray-50">
            <div class="text-gray-500">Conversions</div><div class="font-semibold">${fmt(r.conversions)}</div>
          </div>
          <div class="p-3 rounded bg-gray-50">
            <div class="text-gray-500">Conversion Rate</div><div class="font-semibold">${pct(r.conversion_rate)}</div>
          </div>
        </div>
      </div>`;
      const card = document.createElement('div');
      card.innerHTML = html; resEl.appendChild(card.firstElementChild);
    });
  }

  function renderSegmentCards(rows){
    resEl.innerHTML = '';
    if(!rows || !rows.length){
      resEl.innerHTML = '<div class="text-gray-500">No data.</div>'; return;
    }
    rows.forEach(r=>{
      const seriesMini = (r.series || []).slice(-7); // last 7 points preview
      const mini = seriesMini.map(x=>`${x.date}: ${x.total_members}`).join('<br>');
      const html = `
      <div class="border rounded-lg p-4 shadow-sm">
        <div class="font-semibold text-lg mb-2">${r.name || r.segment_id}</div>
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div class="p-3 rounded bg-gray-50">
            <div class="text-gray-500">Members (Now)</div><div class="font-semibold">${fmt(r.members_now)}</div>
          </div>
          <div class="p-3 rounded bg-gray-50 col-span-1 md:col-span-1">
            <div class="text-gray-500">Recent trend</div>
            <div class="font-mono text-xs leading-4 max-h-28 overflow-auto">${mini || '—'}</div>
          </div>
        </div>
      </div>`;
      const card = document.createElement('div');
      card.innerHTML = html; resEl.appendChild(card.firstElementChild);
    });
  }

  async function fetchKpis(){
    const mode = modeEl.value;
    const start = startEl.value;
    const end = endEl.value;

    if(!start || !end){
      alert('Select start and end dates'); return;
    }

    const body = {_token: csrf, start_date: start, end_date: end};

    let url = '';
    if(mode === 'campaigns'){
      body.campaign_ids = selectedValues(campEl);
      // do NOT send conversion metric from FE; controller supplies it
      url = '{{ route('klaviyo.kpis.campaigns') }}';
    } else {
      body.segment_ids = selectedValues(segEl);
      url = '{{ route('klaviyo.kpis.segments') }}';
    }

    resEl.innerHTML = '<div class="text-gray-500">Loading…</div>';

    try {
      const r = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': csrf},
        body: JSON.stringify(body),
      });
      const j = await r.json();
      if(!r.ok){ throw new Error(j.error || 'Request failed'); }

      // both endpoints respond as { rows: [...] }
      if(mode === 'campaigns') renderCampaignCards(j.rows);
      else renderSegmentCards(j.rows);
    } catch (e){
      resEl.innerHTML = `<div class="text-red-600">Error: ${e.message}</div>`;
    }
  }

  document.getElementById('btn-fetch').onclick = fetchKpis;

  loadOptions();
})();
</script>
@endsection
