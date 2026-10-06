/**
 * Master Bookings -> Smart Visa / Passport Upload.
 * 1 Drop every visa and passport (separate PDFs/images, or PDFs holding many documents, mixed in any order)
 * 2 Gemini reads each file (index.php?api=read_booking_file, kind=travel_doc) and sorts each page into visa / passport
 * 3 smart_visa_match finds each document's Master Booking (passport no. -> near passport no. -> name); the user can
 *   change the booking, search another one, or skip
 * 4 Each document (its own page for multi-document PDFs, photos become PDFs) is attached as the booking's Visa or Passport file.
 * Uses SmartMB.pdfFor() from assets/js/smart_master_booking.js.
 */
(function () {
    const MAX = 10485760;
    const S = { step: 1, files: [], reads: new Map(), rows: [] };
    const esc = v => { const d = document.createElement('div'); d.textContent = v ?? ''; return d.innerHTML; };
    const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
    const kb = n => n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
    const csrf = () => (typeof AST_CSRF !== 'undefined' && AST_CSRF) || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const toast = (t, m) => (typeof showToast === 'function' ? showToast(t, m) : alert(m));
    const typeTag = t => t === 'passport'
        ? '<span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wider rounded-md px-1.5 py-0.5 bg-violet-100 text-violet-700"><i class="fa-solid fa-passport"></i>Passport</span>'
        : '<span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wider rounded-md px-1.5 py-0.5 bg-teal-100 text-teal-700"><i class="fa-solid fa-stamp"></i>Visa</span>';
    const pill = (cls, icon, text, title = '') => `<span class="inline-flex items-center gap-1 text-[10px] font-bold rounded-full px-2 py-0.5 ${cls}" title="${esc(title)}"><i class="fa-solid ${icon}"></i>${text}</span>`;

    function shell(body, footer = '') {
        const steps = ['Upload', 'AI Reading', 'Match', 'Attach'];
        document.getElementById('dynamicModalContainer').innerHTML = `
        <div class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-sm flex items-start justify-center p-2 sm:p-4 overflow-y-auto">
          <div class="w-full max-w-6xl my-2 sm:my-6 bg-white rounded-3xl shadow-2xl overflow-hidden sv-pop">
            <div class="relative px-5 sm:px-7 pt-5 pb-4 text-white bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-600 overflow-hidden">
              <div class="absolute -right-10 -top-16 w-56 h-56 rounded-full bg-white/10 blur-2xl"></div>
              <div class="relative flex items-start justify-between gap-3">
                <div>
                  <div class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/70">Master Bookings</div>
                  <h3 class="text-lg sm:text-xl font-extrabold flex items-center gap-2"><i class="fa-solid fa-stamp"></i> Smart Visa / Passport Upload</h3>
                  <p class="text-[11px] text-white/85 mt-0.5">Drop visas and passports together — each one is recognised and finds its booking by passport number or name.</p>
                </div>
                <button onclick="closeActiveModal()" class="h-9 w-9 rounded-full bg-white/15 hover:bg-white/25 flex items-center justify-center" title="Close"><i class="fa-solid fa-xmark"></i></button>
              </div>
              <div class="relative mt-4 grid grid-cols-4 gap-2">
                ${steps.map((s, i) => {
                    const n = i + 1, done = n < S.step, on = n === S.step;
                    return `<div class="flex items-center gap-2">
                      <span class="h-7 w-7 shrink-0 rounded-full flex items-center justify-center text-[11px] font-black ${done ? 'bg-lime-300 text-emerald-950' : on ? 'bg-white text-teal-700 ring-4 ring-white/30' : 'bg-white/15 text-white/70'}">${done ? '<i class="fa-solid fa-check"></i>' : n}</span>
                      <span class="hidden sm:block text-[11px] font-bold ${on ? 'text-white' : 'text-white/60'}">${s}</span>
                      ${n < 4 ? `<span class="hidden sm:block flex-1 h-0.5 rounded ${done ? 'bg-lime-300' : 'bg-white/20'}"></span>` : ''}
                    </div>`;
                }).join('')}
              </div>
            </div>
            <div class="p-4 sm:p-6 bg-gradient-to-b from-slate-50 to-white" id="svBody">${body}</div>
            ${footer ? `<div class="px-4 sm:px-6 py-3 border-t border-slate-200 bg-white flex flex-wrap items-center justify-between gap-2">${footer}</div>` : ''}
          </div>
        </div>
        <style>
          .sv-pop{animation:svPop .25s ease-out}@keyframes svPop{from{transform:translateY(12px) scale(.98);opacity:0}to{transform:none;opacity:1}}
          .sv-shimmer{background:linear-gradient(90deg,#4C9AAF,#37D4D9,#4C9AAF,#4C9AAF);background-size:300% 100%;animation:svShim 2s linear infinite}@keyframes svShim{to{background-position:-300% 0}}
          .sv-burst{animation:svBurst .6s ease-out}@keyframes svBurst{0%{transform:scale(.4);opacity:0}70%{transform:scale(1.12)}100%{transform:scale(1);opacity:1}}
          .sv-row{transition:all .15s}.sv-row.sv-off{opacity:.5;filter:grayscale(.5)}
        </style>`;
    }

    // ------------------------------------------------------------------ 1 upload
    function renderUpload() {
        S.step = 1;
        shell(`
          <div class="rounded-3xl border-2 border-dashed ${S.files.length ? 'border-teal-300 bg-teal-50/40' : 'border-slate-300 bg-white'} p-6 transition"
               ondragover="event.preventDefault();this.classList.add('ring-4','ring-teal-200')" ondragleave="this.classList.remove('ring-4','ring-teal-200')"
               ondrop="event.preventDefault();SmartVisa.add(event.dataTransfer.files)">
            <label class="flex flex-col sm:flex-row items-center gap-4 cursor-pointer text-center sm:text-left">
              <span class="h-16 w-16 rounded-3xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-3xl shadow-lg shadow-teal-500/30"><i class="fa-solid fa-stamp"></i></span>
              <span class="flex-1"><span class="block text-base font-extrabold text-slate-800">Drop visas &amp; passports here</span>
                <span class="block text-[11px] text-slate-500">Visas and passports mixed — separate PDFs or photos, or one big PDF with many — up to 40 files, 10 MB each</span></span>
              <span class="text-xs font-bold text-teal-700 bg-white border border-teal-200 rounded-full px-4 py-2"><i class="fa-solid fa-plus mr-1"></i>Add files</span>
              <input type="file" class="hidden" multiple accept="application/pdf,image/jpeg,image/png,image/webp" onchange="SmartVisa.add(this.files);this.value=''">
            </label>
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
              ${S.files.map((f, i) => `<div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-2.5 py-1.5 text-[11px]">
                  <i class="fa-solid ${/pdf/i.test(f.type) || /\.pdf$/i.test(f.name) ? 'fa-file-pdf text-rose-500' : 'fa-file-image text-sky-500'}"></i>
                  <span class="truncate flex-1 font-semibold text-slate-700" title="${esc(f.name)}">${esc(f.name)}</span>
                  <span class="text-slate-400">${kb(f.size)}</span>
                  <button type="button" onclick="SmartVisa.remove(${i})" class="text-slate-400 hover:text-rose-600"><i class="fa-solid fa-xmark"></i></button>
                </div>`).join('')}
            </div>
          </div>
          <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2"><i class="fa-solid fa-passport text-emerald-500 mr-1"></i> Matched by passport number first</div>
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2"><i class="fa-solid fa-user-check text-teal-500 mr-1"></i> Then by name — you confirm before anything is saved</div>
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2"><i class="fa-solid fa-scissors text-cyan-500 mr-1"></i> Visa or passport detected per page — multi-document PDFs are split</div>
          </div>`,
          `<span class="text-[11px] text-slate-500"><i class="fa-solid fa-key text-amber-500 mr-1"></i> Gemini keys share the work · ${S.files.length} file(s)</span>
           <div class="flex gap-2">
             <button onclick="closeActiveModal()" class="px-4 py-2 rounded-xl bg-slate-100 text-xs font-semibold">Cancel</button>
             <button onclick="SmartVisa.read()" ${S.files.length ? '' : 'disabled'} class="px-5 py-2.5 rounded-xl text-white text-xs font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 shadow-lg shadow-teal-600/30 disabled:opacity-40 disabled:shadow-none"><i class="fa-solid fa-bolt mr-1"></i> Read visas with AI</button>
           </div>`);
    }

    function add(files) {
        for (const f of Array.from(files || [])) {
            if (f.size > MAX) { toast('error', f.name + ' is larger than 10 MB.'); continue; }
            if (!/^(application\/pdf|image\/(jpeg|png|webp))$/.test(f.type) && !/\.(pdf|jpe?g|png|webp)$/i.test(f.name)) { toast('error', f.name + ': only PDF, JPG, PNG or WEBP.'); continue; }
            if (!S.files.some(x => x.name === f.name && x.size === f.size)) S.files.push(f);
        }
        S.files = S.files.slice(0, 40);
        renderUpload();
    }

    // ------------------------------------------------------------------ 2 AI reading
    function renderReading() {
        S.step = 2;
        const done = S.files.filter(f => S.reads.get(f)?.done).length;
        const pct = Math.round(done / Math.max(1, S.files.length) * 100);
        const finished = done === S.files.length;
        const failed = S.files.filter(f => S.reads.get(f)?.done && !S.reads.get(f).result.success).length;
        const docsOf = f => S.reads.get(f)?.result?.documents || [];
        const visas = S.files.reduce((n, f) => n + docsOf(f).length, 0);
        const nVisa = S.files.reduce((n, f) => n + docsOf(f).filter(d => d.doc_type === 'visa').length, 0);
        shell(`
          <div class="max-w-3xl mx-auto">
            <div class="flex items-center gap-4">
              <div class="relative h-16 w-16 shrink-0"><div class="absolute inset-0 rounded-2xl sv-shimmer ${finished ? 'opacity-0' : ''}"></div>
                <div class="absolute inset-[3px] rounded-[14px] bg-white flex items-center justify-center text-2xl ${finished ? 'text-emerald-500' : 'text-teal-600'}"><i class="fa-solid ${finished ? 'fa-circle-check' : 'fa-robot fa-bounce'}"></i></div></div>
              <div class="flex-1">
                <div class="text-sm font-extrabold text-slate-800">${finished ? nVisa + ' visa(s) and ' + (visas - nVisa) + ' passport(s) found' : 'Gemini AI is reading the documents…'}</div>
                <div class="text-[11px] text-slate-500">${done} of ${S.files.length} files done</div>
                <div class="mt-2 h-2.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full sv-shimmer transition-all duration-500" style="width:${pct}%"></div></div>
              </div>
              <div class="text-2xl font-black text-teal-700 tabular-nums">${pct}%</div>
            </div>
            <div class="mt-5 space-y-2">
              ${S.files.map(f => {
                  const st = S.reads.get(f) || {};
                  const r = st.result;
                  const icon = !st.started ? '<i class="fa-regular fa-clock text-slate-300"></i>' : !st.done ? '<i class="fa-solid fa-circle-notch fa-spin text-teal-500"></i>'
                      : r.success ? '<i class="fa-solid fa-circle-check text-emerald-500"></i>' : '<i class="fa-solid fa-circle-xmark text-rose-500"></i>';
                  const info = !st.started ? 'Waiting…' : !st.done ? (st.attempt > 1 ? 'Busy — retrying (try ' + st.attempt + ')…' : 'Reading…')
                      : !r.success ? `<span class="text-rose-600">${esc(r.message)}</span>`
                      : (r.documents || []).length ? r.documents.map(v => `${typeTag(v.doc_type)} <b>${esc(v.name)}</b> <span class="font-mono text-slate-400">${esc(v.passport_no)}</span>`).join(', ') : '<span class="text-amber-600">No visa or passport found in this file</span>';
                  return `<div class="flex items-center gap-3 rounded-2xl bg-white border ${st.done && !r.success ? 'border-rose-200' : 'border-slate-200'} px-3 py-2.5">
                      <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center bg-teal-50 text-teal-600"><i class="fa-solid fa-stamp"></i></span>
                      <div class="flex-1 min-w-0"><div class="text-xs font-bold text-slate-800 truncate">${esc(f.name)}</div><div class="text-[11px] text-slate-500 truncate">${info}</div></div>
                      <span class="text-lg">${icon}</span></div>`;
              }).join('')}
            </div>
          </div>`,
          `<button onclick="SmartVisa.back()" class="px-4 py-2 rounded-xl bg-slate-100 text-xs font-semibold"><i class="fa-solid fa-arrow-left mr-1"></i> Back</button>
           <div class="flex gap-2">
             ${finished && failed ? `<button onclick="SmartVisa.read(true)" class="px-4 py-2 rounded-xl bg-amber-100 text-amber-800 text-xs font-bold"><i class="fa-solid fa-rotate-right mr-1"></i> Retry ${failed} failed</button>` : ''}
             ${finished && visas ? `<button onclick="SmartVisa.match()" class="px-5 py-2.5 rounded-xl text-white text-xs font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 shadow-lg shadow-teal-600/30">Find bookings <i class="fa-solid fa-arrow-right ml-1"></i></button>` : ''}
           </div>`);
    }

    async function readOne(f) {
        const st = { started: true, done: false, attempt: 0 };
        S.reads.set(f, st);
        let data = { success: false, message: 'not read' };
        for (let attempt = 1; attempt <= 3; attempt++) {
            st.attempt = attempt;
            renderReading();
            if (attempt > 1) await new Promise(r => setTimeout(r, (attempt - 1) * 6000));
            const fd = new FormData();
            fd.append('kind', 'travel_doc');
            fd.append('file', f);
            try {
                const res = await fetch('index.php?api=read_booking_file', { method: 'POST', headers: { 'X-CSRF-Token': csrf() }, body: fd });
                data = await res.json().catch(() => ({ success: false, message: 'Unexpected server response (HTTP ' + res.status + ').' }));
            } catch (e) { data = { success: false, message: 'Network error: ' + e.message }; }
            if (data.success || /supported|10 MB|No file/i.test(data.message || '')) break;
        }
        st.done = true;
        st.result = data;
        renderReading();
    }

    async function read(onlyFailed = false) {
        const todo = S.files.filter(f => { const st = S.reads.get(f); return onlyFailed ? (st?.done && !st.result.success) : !(st?.done && st.result.success); });
        todo.forEach(f => S.reads.delete(f));
        renderReading();
        let cursor = 0;
        const worker = async () => { while (cursor < todo.length) await readOne(todo[cursor++]); };
        await Promise.all([worker(), worker(), worker()]);
        renderReading();
    }

    // ------------------------------------------------------------------ 3 match / review
    async function match() {
        const visas = [];
        S.files.forEach((f, i) => (S.reads.get(f)?.result?.documents || []).forEach(v => visas.push({ file: i, doc: v })));
        document.getElementById('svBody').insertAdjacentHTML('beforeend', '<div class="mt-4 text-center text-xs text-teal-700"><i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Looking for the bookings…</div>');
        const res = await apiRequest('index.php?api=smart_visa_match', { visas });
        if (!res.success) { toast('error', res.message || 'Matching failed.'); return; }
        // A booking that already has this document is only replaced when the user ticks it.
        S.rows = res.rows.map(r => ({ ...r, include: !!r.booking_id && !r.candidates.find(c => c.id === r.booking_id)?.has_file, search: '', results: [] }));
        renderReview();
    }

    function chosen(r) { return r.candidates.find(c => c.id === r.booking_id) || r.results.find(c => c.id === r.booking_id) || null; }

    function rowHtml(r, i) {
        const v = r.visa, b = chosen(r);
        const how = b?.how === 'passport' ? pill('bg-emerald-100 text-emerald-700', 'fa-passport', 'Passport match')
            : b?.how === 'passport_close' ? pill('bg-amber-100 text-amber-800', 'fa-triangle-exclamation', 'Passport differs by 1 character')
            : b?.how === 'name' ? pill('bg-amber-100 text-amber-800', 'fa-user', 'Name match only')
            : b ? pill('bg-sky-100 text-sky-700', 'fa-hand-pointer', 'Chosen by you') : '';
        const isPass = v.doc_type === 'passport', label = isPass ? 'passport' : 'visa';
        const expiryWarn = b && v.expiry_date && b.arrival_date && v.expiry_date < b.arrival_date
            ? pill('bg-rose-100 text-rose-700', 'fa-calendar-xmark', (isPass ? 'Passport' : 'Visa') + ' expires before arrival') : '';
        const hasFile = b && (isPass ? b.has_passport : b.has_visa);
        const options = [...r.candidates, ...r.results.filter(x => !r.candidates.some(c => c.id === x.id))];
        return `<div class="sv-row ${r.include ? '' : 'sv-off'} rounded-2xl bg-white border ${!b ? 'border-rose-200' : b.how === 'passport' || b.how === undefined ? 'border-slate-200' : 'border-amber-200'} p-3 shadow-sm">
          <div class="grid grid-cols-1 lg:grid-cols-[auto_1fr_auto_1.2fr] items-start gap-3">
            <label class="pt-1 cursor-pointer"><input type="checkbox" class="h-4 w-4 accent-teal-600" ${r.include ? 'checked' : ''} ${b ? '' : 'disabled'} onchange="SmartVisa.toggle(${i}, this.checked)"></label>
            <div class="min-w-0">
              <div class="mb-0.5">${typeTag(v.doc_type)}</div>
              <div class="text-sm font-extrabold text-slate-800">${esc(v.name || 'Name not read')}</div>
              <div class="text-[11px] text-slate-500"><span class="font-mono font-bold text-slate-700">${esc(v.passport_no || '—')}</span>${v.visa_no ? ' · Visa ' + esc(v.visa_no) : ''}${v.visa_type ? ' · ' + esc(v.visa_type) : ''}</div>
              <div class="text-[10px] text-slate-400 truncate">${v.expiry_date ? 'Valid to ' + fmtDate(v.expiry_date) + ' · ' : ''}${esc(S.files[v.file]?.name || '')}${v.page > 1 ? ' · page ' + v.page : ''}</div>
            </div>
            <div class="hidden lg:flex h-full items-center text-teal-400 text-xl px-1"><i class="fa-solid fa-arrow-right-long"></i></div>
            <div class="min-w-0">
              <div class="text-[9px] font-bold uppercase tracking-wider text-indigo-600">Master Booking</div>
              ${b ? `<div class="flex flex-wrap items-center gap-1.5"><span class="text-sm font-extrabold text-slate-800">${esc(b.name || b.passenger_name)}</span>
                  <span class="font-mono text-[11px] font-bold text-indigo-700 bg-indigo-50 rounded-md px-1.5">${esc(b.code || b.booking_code)}</span></div>
                <div class="text-[11px] text-slate-500"><span class="font-mono">${esc(b.passport || b.passport_number || '—')}</span>${(b.agent || b.agent_name) ? ' · ' + esc(b.agent || b.agent_name) : ''}${b.arrival_date ? ' · Arrives ' + fmtDate(b.arrival_date) : ''}</div>
                <div class="mt-1 flex flex-wrap gap-1">${how}${hasFile ? pill('bg-orange-100 text-orange-700', 'fa-rotate', r.include ? 'Already has a ' + label + ' — will be replaced' : 'Already has a ' + label + ' — tick to replace') : ''}${expiryWarn}</div>`
              : `<div class="text-xs font-bold text-rose-600"><i class="fa-solid fa-circle-question mr-1"></i>${r.clash ? 'Another ' + label + ' matched the same booking better' : 'No booking found'} — search below</div>`}
              <div class="mt-2 flex flex-wrap gap-1.5">
                ${options.length ? `<select class="text-[11px] border border-slate-200 rounded-lg px-2 py-1 max-w-[16rem]" onchange="SmartVisa.pick(${i}, this.value)">
                    <option value="">${b ? 'Change booking…' : 'Choose booking…'}</option>
                    ${options.map(c => `<option value="${c.id}" ${c.id === r.booking_id ? 'selected' : ''}>${esc((c.code || c.booking_code) + ' — ' + (c.name || c.passenger_name) + ' (' + (c.passport || c.passport_number || '') + ')')}</option>`).join('')}
                  </select>` : ''}
                <input type="search" placeholder="Search name / passport / booking ID" value="${esc(r.search)}" class="text-[11px] border border-slate-200 rounded-lg px-2 py-1 flex-1 min-w-[10rem]"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();SmartVisa.search(${i}, this.value)}" onchange="SmartVisa.search(${i}, this.value)">
              </div>
            </div>
          </div>
        </div>`;
    }

    function renderReview() {
        S.step = 3;
        const sel = S.rows.filter(r => r.include && r.booking_id);
        const exact = S.rows.filter(r => chosen(r)?.how === 'passport').length;
        const none = S.rows.filter(r => !r.booking_id).length;
        const chip = (icon, n, label, cls) => `<div class="rounded-2xl ${cls} px-4 py-3 flex items-center gap-3"><i class="fa-solid ${icon} text-xl opacity-80"></i><div><div class="text-xl font-black leading-none">${n}</div><div class="text-[10px] font-bold uppercase tracking-wider opacity-70 mt-1">${label}</div></div></div>`;
        shell(`
          <div class="grid grid-cols-2 md:grid-cols-5 gap-2.5">
            ${chip('fa-stamp', S.rows.filter(r => r.visa.doc_type !== 'passport').length, 'Visas', 'bg-teal-50 text-teal-800')}
            ${chip('fa-passport', S.rows.filter(r => r.visa.doc_type === 'passport').length, 'Passports', 'bg-violet-50 text-violet-800')}
            ${chip('fa-circle-check', exact, 'Passport no. matches', 'bg-emerald-50 text-emerald-800')}
            ${chip('fa-user', S.rows.length - exact - none, 'Need a look', 'bg-amber-50 text-amber-800')}
            ${chip('fa-circle-question', none, 'No booking', none ? 'bg-rose-50 text-rose-800' : 'bg-slate-50 text-slate-600')}
          </div>
          <div class="mt-3 text-[11px] text-slate-500"><i class="fa-solid fa-circle-info text-teal-500 mr-1"></i> Check the amber ones. Use the dropdown or search to pick a different booking; untick to skip.</div>
          <div class="mt-3 space-y-2.5">${S.rows.map(rowHtml).join('')}</div>`,
          `<button onclick="SmartVisa.toReading()" class="px-4 py-2 rounded-xl bg-slate-100 text-xs font-semibold"><i class="fa-solid fa-arrow-left mr-1"></i> Back</button>
           <button onclick="SmartVisa.attach()" ${sel.length ? '' : 'disabled'} class="px-6 py-2.5 rounded-xl text-white text-xs font-extrabold bg-gradient-to-r from-emerald-500 to-teal-600 shadow-lg shadow-emerald-600/30 disabled:opacity-40 disabled:shadow-none"><i class="fa-solid fa-paperclip mr-1"></i> Attach ${sel.length} document${sel.length === 1 ? '' : 's'}</button>`);
    }

    function pick(i, id) {
        const r = S.rows[i];
        if (!id) return;
        r.booking_id = Number(id);
        r.include = true;
        r.clash = false;
        const c = chosen(r);
        if (c && !r.candidates.some(x => x.id === c.id)) c.how = undefined; // picked by hand from a search
        renderReview();
    }

    async function search(i, q) {
        const r = S.rows[i];
        r.search = q;
        if (q.trim().length < 2) return;
        const res = await (await fetch('index.php?api=search_master_bookings&q=' + encodeURIComponent(q.trim()))).json().catch(() => ({ results: [] }));
        r.results = (res.results || []).map(x => ({ id: Number(x.id), code: x.booking_code, name: x.passenger_name, passport: x.passport_number, arrival_date: x.arrival_date, agent: x.agent_name }));
        if (!r.results.length) toast('error', 'No booking matches "' + q + '".');
        renderReview();
    }

    function toggle(i, on) { S.rows[i].include = on; renderReview(); }

    // ------------------------------------------------------------------ 4 attach
    function renderAttaching() {
        S.step = 4;
        const sel = S.rows.filter(r => r.include && r.booking_id);
        const done = sel.filter(r => r._state === 'done' || r._state === 'error').length;
        const ok = sel.filter(r => r._state === 'done').length;
        const finished = done === sel.length;
        shell(`
          <div class="max-w-3xl mx-auto">
            ${finished ? `<div class="text-center py-3">
                <div class="sv-burst mx-auto h-20 w-20 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 text-white flex items-center justify-center text-4xl shadow-xl shadow-emerald-500/40"><i class="fa-solid fa-stamp"></i></div>
                <div class="mt-3 text-lg font-black text-slate-800">${ok} document${ok === 1 ? '' : 's'} attached!</div>
                <div class="text-[11px] text-slate-500">Each booking's Visa / Passport column now has its PDF.</div></div>`
              : `<div class="flex items-center gap-3"><i class="fa-solid fa-circle-notch fa-spin text-teal-600 text-2xl"></i><div class="flex-1"><div class="text-sm font-extrabold text-slate-800">Attaching visas…</div>
                <div class="mt-2 h-2.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full sv-shimmer transition-all" style="width:${Math.round(done / Math.max(1, sel.length) * 100)}%"></div></div></div><div class="font-black text-teal-700">${done}/${sel.length}</div></div>`}
            <div class="mt-4 space-y-2">
              ${sel.map(r => { const b = chosen(r) || {}; return `<div class="flex items-center gap-3 rounded-2xl bg-white border ${r._state === 'error' ? 'border-rose-200' : 'border-slate-200'} px-3 py-2.5">
                  <span class="text-lg">${r._state === 'done' ? '<i class="fa-solid fa-circle-check text-emerald-500"></i>' : r._state === 'error' ? '<i class="fa-solid fa-circle-xmark text-rose-500"></i>' : r._state ? '<i class="fa-solid fa-circle-notch fa-spin text-teal-500"></i>' : '<i class="fa-regular fa-clock text-slate-300"></i>'}</span>
                  <div class="flex-1 min-w-0"><div class="text-xs font-bold text-slate-800">${esc(b.name || '')} <span class="font-mono text-slate-400 font-normal">${esc(r.visa.passport_no)}</span></div>
                    <div class="text-[11px] ${r._state === 'error' ? 'text-rose-600' : 'text-slate-500'}">${esc(r._msg || 'Waiting…')}</div></div>
                  <span class="font-mono text-[11px] font-bold text-indigo-700 bg-indigo-50 rounded-lg px-2 py-1">${esc(b.code || '')}</span></div>`; }).join('')}
            </div>
          </div>`,
          finished ? `<span class="text-[11px] text-slate-500">${sel.length - ok ? (sel.length - ok) + ' could not be attached — see above.' : 'All done.'}</span>
             <button onclick="location.reload()" class="px-6 py-2.5 rounded-xl text-white text-xs font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600"><i class="fa-solid fa-list-check mr-1"></i> View Master Bookings</button>` : '');
    }

    async function attachOne(r) {
        const b = chosen(r) || {};
        r._state = 'busy'; r._msg = 'Preparing PDF…'; renderAttaching();
        try {
            const file = S.files[r.visa.file];
            // Several documents in one PDF: only this document's page goes to the booking.
            const many = (S.reads.get(file)?.result?.documents || []).length > 1;
            const isPass = r.visa.doc_type === 'passport';
            const bytes = await SmartMB.pdfFor(file, many ? r.visa.page : 0);
            r._msg = 'Uploading…'; renderAttaching();
            const fd = new FormData();
            fd.append('booking_id', r.booking_id);
            fd.append('type', isPass ? 'passport' : 'visa');
            fd.append('file', new File([bytes], ((isPass ? 'Passport ' : 'Visa ') + (b.name || r.visa.name)).replace(/[\\/:*?"<>|]+/g, ' ').slice(0, 120) + '.pdf', { type: 'application/pdf' }));
            const res = await fetch('index.php?api=upload_booking_file', { method: 'POST', headers: { 'X-CSRF-Token': csrf() }, body: fd });
            const data = await res.json().catch(() => ({ success: false, message: 'HTTP ' + res.status }));
            if (!data.success) throw new Error(data.message || 'Upload failed');
            const had = isPass ? b.has_passport : b.has_visa;
            r._state = 'done'; r._msg = (isPass ? 'Passport ' : 'Visa ') + (had ? 'replaced' : 'attached');
        } catch (e) {
            r._state = 'error'; r._msg = e.message;
        }
        renderAttaching();
    }

    async function attach() {
        const sel = S.rows.filter(r => r.include && r.booking_id);
        sel.forEach(r => { r._state = ''; r._msg = ''; });
        renderAttaching();
        let cursor = 0;
        const worker = async () => { while (cursor < sel.length) await attachOne(sel[cursor++]); };
        await Promise.all([worker(), worker(), worker()]);
        renderAttaching();
    }

    window.SmartVisa = {
        state: S,
        open() { S.files = []; S.reads = new Map(); S.rows = []; renderUpload(); },
        add, remove(i) { S.files.splice(i, 1); renderUpload(); }, read, match, pick, search, toggle, attach,
        back() { renderUpload(); }, toReading() { renderReading(); },
    };
})();
