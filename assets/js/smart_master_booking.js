/**
 * Master Bookings -> Smart Auto Booking.
 * 1 Upload tickets (arrival / return, one or more files) and passports (PDFs or photos, any order)
 * 2 Every file is read by Gemini in its own request (index.php?api=read_booking_file), 3 at a time
 * 3 The server builds one draft per traveller (smart_master_prepare); the user reviews and edits them
 * 4 Each draft is saved with save_booking and its files are attached: Ticket 1 (arrival), Ticket 2 (return)
 *   and the traveller's own passport page (photos become PDFs, multi-passport PDFs are split per page).
 * Needs AST_AGENTS, AST_VENDORS and AST_CSRF from views/bookings/index.php.
 */
(function () {
    const MAX = 10485760;
    const PDF_LIB = 'https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js';
    const S = { step: 1, tickets: [], passports: [], reads: new Map(), rows: [], defaults: {}, created: [] };

    const esc = v => { const d = document.createElement('div'); d.textContent = v ?? ''; return d.innerHTML; };
    const today = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
    const kb = n => n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
    const initials = n => (n || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
    const csrf = () => (typeof AST_CSRF !== 'undefined' && AST_CSRF) || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const toast = (t, m) => (typeof showToast === 'function' ? showToast(t, m) : alert(m));

    // ------------------------------------------------------------------ shell
    function shell(body, footer = '') {
        const steps = ['Upload', 'AI Reading', 'Review', 'Create'];
        document.getElementById('dynamicModalContainer').innerHTML = `
        <div class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-sm flex items-start justify-center p-2 sm:p-4 overflow-y-auto">
          <div class="w-full max-w-6xl my-2 sm:my-6 bg-white rounded-3xl shadow-2xl overflow-hidden sm-pop">
            <div class="relative px-5 sm:px-7 pt-5 pb-4 text-white bg-gradient-to-br from-indigo-700 via-violet-700 to-fuchsia-600 overflow-hidden">
              <div class="absolute -right-10 -top-16 w-56 h-56 rounded-full bg-white/10 blur-2xl"></div>
              <div class="absolute right-24 -bottom-20 w-44 h-44 rounded-full bg-fuchsia-300/20 blur-2xl"></div>
              <div class="relative flex items-start justify-between gap-3">
                <div>
                  <div class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/70">Master Bookings</div>
                  <h3 class="text-lg sm:text-xl font-extrabold flex items-center gap-2"><i class="fa-solid fa-wand-magic-sparkles"></i> Smart Auto Booking</h3>
                  <p class="text-[11px] text-white/80 mt-0.5">Drop the tickets and passports — Gemini AI builds every traveller's booking for you.</p>
                </div>
                <button onclick="closeActiveModal()" class="h-9 w-9 rounded-full bg-white/15 hover:bg-white/25 flex items-center justify-center" title="Close"><i class="fa-solid fa-xmark"></i></button>
              </div>
              <div class="relative mt-4 grid grid-cols-4 gap-2">
                ${steps.map((s, i) => {
                    const n = i + 1, done = n < S.step, on = n === S.step;
                    return `<div class="flex items-center gap-2">
                      <span class="h-7 w-7 shrink-0 rounded-full flex items-center justify-center text-[11px] font-black ${done ? 'bg-emerald-400 text-emerald-950' : on ? 'bg-white text-violet-700 ring-4 ring-white/30' : 'bg-white/15 text-white/70'}">${done ? '<i class="fa-solid fa-check"></i>' : n}</span>
                      <span class="hidden sm:block text-[11px] font-bold ${on ? 'text-white' : 'text-white/60'}">${s}</span>
                      ${n < 4 ? `<span class="hidden sm:block flex-1 h-0.5 rounded ${done ? 'bg-emerald-300' : 'bg-white/20'}"></span>` : ''}
                    </div>`;
                }).join('')}
              </div>
            </div>
            <div class="p-4 sm:p-6 bg-gradient-to-b from-slate-50 to-white" id="smBody">${body}</div>
            ${footer ? `<div class="px-4 sm:px-6 py-3 border-t border-slate-200 bg-white flex flex-wrap items-center justify-between gap-2">${footer}</div>` : ''}
          </div>
        </div>
        <style>
          .sm-pop{animation:smPop .25s ease-out}@keyframes smPop{from{transform:translateY(12px) scale(.98);opacity:0}to{transform:none;opacity:1}}
          .sm-shimmer{background:linear-gradient(90deg,#26206F,#26206F,#26206F,#26206F);background-size:300% 100%;animation:smShim 2s linear infinite}@keyframes smShim{to{background-position:-300% 0}}
          .sm-in{border:1px solid #DCE5ED;border-radius:.6rem;padding:.35rem .5rem;font-size:12px;width:100%;background:#fff}
          .sm-in:focus{outline:none;box-shadow:0 0 0 2px #8F89CA;border-color:#8F89CA}
          .sm-card{transition:all .15s}.sm-card.sm-off{opacity:.45;filter:grayscale(.6)}
          .sm-burst{animation:smBurst .6s ease-out}@keyframes smBurst{0%{transform:scale(.4);opacity:0}70%{transform:scale(1.12)}100%{transform:scale(1);opacity:1}}
        </style>`;
    }

    // ------------------------------------------------------------------ step 1: upload
    function zone(kind, icon, title, hint, color) {
        const list = S[kind];
        return `<div class="rounded-2xl border-2 border-dashed ${list.length ? 'border-' + color + '-300 bg-' + color + '-50/40' : 'border-slate-300 bg-white'} p-4 transition"
                     ondragover="event.preventDefault();this.classList.add('ring-4','ring-${color}-200')" ondragleave="this.classList.remove('ring-4','ring-${color}-200')"
                     ondrop="event.preventDefault();SmartMB.add('${kind}', event.dataTransfer.files)">
            <label class="flex items-center gap-3 cursor-pointer">
              <span class="h-12 w-12 rounded-2xl bg-gradient-to-br from-${color}-500 to-${color}-600 text-white flex items-center justify-center text-xl shadow-lg shadow-${color}-500/30"><i class="fa-solid ${icon}"></i></span>
              <span class="flex-1"><span class="block text-sm font-extrabold text-slate-800">${title}</span><span class="block text-[11px] text-slate-500">${hint}</span></span>
              <span class="text-[11px] font-bold text-${color}-700 bg-white border border-${color}-200 rounded-full px-3 py-1.5"><i class="fa-solid fa-plus mr-1"></i>Add files</span>
              <input type="file" class="hidden" id="sm_${kind}" multiple accept="application/pdf,image/jpeg,image/png,image/webp" onchange="SmartMB.add('${kind}', this.files);this.value=''">
            </label>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
              ${list.map((f, i) => `<div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-2.5 py-1.5 text-[11px]">
                  <i class="fa-solid ${/pdf/i.test(f.type) || /\.pdf$/i.test(f.name) ? 'fa-file-pdf text-rose-500' : 'fa-file-image text-sky-500'}"></i>
                  <span class="truncate flex-1 font-semibold text-slate-700" title="${esc(f.name)}">${esc(f.name)}</span>
                  <span class="text-slate-400">${kb(f.size)}</span>
                  <button type="button" onclick="SmartMB.remove('${kind}',${i})" class="text-slate-400 hover:text-rose-600"><i class="fa-solid fa-xmark"></i></button>
                </div>`).join('') || `<div class="text-[11px] text-slate-400 sm:col-span-2 text-center py-2">Drag & drop here, or click <b>Add files</b></div>`}
            </div>
          </div>`;
    }

    function defaultsHtml() {
        const d = S.defaults;
        const opt = (list, sel, empty) => `<option value="">${empty}</option>` + list.map(x => `<option value="${x.id}" ${String(sel) === String(x.id) ? 'selected' : ''}>${esc(x.name)}</option>`).join('');
        const lbl = t => `<label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">${t}</label>`;
        return `<details class="group rounded-2xl border border-slate-200 bg-white" ${S.defaultsOpen ? 'open' : ''} ontoggle="SmartMB.state.defaultsOpen=this.open">
          <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between">
            <span class="text-xs font-extrabold text-slate-800"><i class="fa-solid fa-sliders text-violet-500 mr-1.5"></i> Booking defaults <span class="font-medium text-slate-400">— applied to every traveller (you can still edit each one)</span></span>
            <i class="fa-solid fa-chevron-down text-slate-400 group-open:rotate-180 transition"></i>
          </summary>
          <div class="px-4 pb-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>${lbl('Booking Date')}<input type="date" id="smd_date" class="sm-in font-mono" value="${esc(d.booking_date || today())}"></div>
            <div>${lbl('Agent (Client)')}<select id="smd_agent" class="sm-in">${opt(typeof AST_AGENTS !== 'undefined' ? AST_AGENTS : [], d.agent_id, 'Select agent')}</select></div>
            <div>${lbl('Supplier / Vendor')}<select id="smd_vendor" class="sm-in">${opt(typeof AST_VENDORS !== 'undefined' ? AST_VENDORS : [], d.vendor_id, 'Select supplier')}</select></div>
            <div>${lbl('Status')}<select id="smd_status" class="sm-in">${['draft', 'confirmed', 'issued', 'completed', 'cancelled'].map(s => `<option ${(d.status || 'draft') === s ? 'selected' : ''}>${s}</option>`).join('')}</select></div>
            <div>${lbl('Visa Buy (SAR)')}<input type="number" min="0" step="0.01" id="smd_vbuy" class="sm-in font-mono text-rose-700" value="${esc(d.visa_buy || '')}" placeholder="0"></div>
            <div>${lbl('Visa Sell (SAR)')}<input type="number" min="0" step="0.01" id="smd_vsell" class="sm-in font-mono text-indigo-700" value="${esc(d.visa_sell || '')}" placeholder="0"></div>
            <div>${lbl('Ticket Buy (SAR)')}<input type="number" min="0" step="0.01" id="smd_tbuy" class="sm-in font-mono text-rose-700" value="${esc(d.ticket_buy || '')}" placeholder="0"></div>
            <div>${lbl('Ticket Sell (SAR)')}<input type="number" min="0" step="0.01" id="smd_tsell" class="sm-in font-mono text-sky-700" value="${esc(d.ticket_sell || '')}" placeholder="0"></div>
            <div class="col-span-2 md:col-span-4">${lbl('Notes')}<input id="smd_notes" class="sm-in" maxlength="200" value="${esc(d.notes || '')}" placeholder="Optional — the PNR is added automatically"></div>
          </div>
        </details>`;
    }

    function saveDefaults() {
        const v = id => document.getElementById(id)?.value ?? '';
        if (!document.getElementById('smd_date')) return;
        S.defaults = { booking_date: v('smd_date'), agent_id: v('smd_agent'), vendor_id: v('smd_vendor'), status: v('smd_status'),
            visa_buy: v('smd_vbuy'), visa_sell: v('smd_vsell'), ticket_buy: v('smd_tbuy'), ticket_sell: v('smd_tsell'), notes: v('smd_notes') };
    }

    function renderUpload() {
        S.step = 1;
        const n = S.tickets.length + S.passports.length;
        shell(`
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            ${zone('tickets', 'fa-plane-departure', 'Tickets', 'Arrival & return — one combined ticket or separate files', 'sky')}
            ${zone('passports', 'fa-passport', 'Passports', 'PDFs or photos, one or many — any order', 'violet')}
          </div>
          <div class="mt-4">${defaultsHtml()}</div>
          <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2"><i class="fa-solid fa-plane-arrival text-sky-500 mr-1"></i> Flight &amp; arrival = the flight that lands in KSA</div>
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2"><i class="fa-solid fa-user-check text-violet-500 mr-1"></i> Each passport is matched to its traveller by name</div>
            <div class="rounded-xl bg-white border border-slate-200 px-3 py-2"><i class="fa-solid fa-paperclip text-fuchsia-500 mr-1"></i> Ticket 1, Ticket 2 &amp; passport PDFs attached automatically</div>
          </div>`,
          `<span class="text-[11px] text-slate-500"><i class="fa-solid fa-key text-amber-500 mr-1"></i> 6 Gemini keys share the work · ${n} file(s) ready</span>
           <div class="flex gap-2">
             <button onclick="closeActiveModal()" class="px-4 py-2 rounded-xl bg-slate-100 text-xs font-semibold">Cancel</button>
             <button onclick="SmartMB.read()" ${n ? '' : 'disabled'} class="px-5 py-2.5 rounded-xl text-white text-xs font-extrabold bg-gradient-to-r from-violet-600 to-fuchsia-600 shadow-lg shadow-violet-600/30 disabled:opacity-40 disabled:shadow-none"><i class="fa-solid fa-bolt mr-1"></i> Read with AI</button>
           </div>`);
    }

    function add(kind, files) {
        saveDefaults();
        for (const f of Array.from(files || [])) {
            if (f.size > MAX) { toast('error', f.name + ' is larger than 10 MB.'); continue; }
            if (!/^(application\/pdf|image\/(jpeg|png|webp))$/.test(f.type) && !/\.(pdf|jpe?g|png|webp)$/i.test(f.name)) { toast('error', f.name + ': only PDF, JPG, PNG or WEBP.'); continue; }
            if (S[kind].some(x => x.name === f.name && x.size === f.size)) continue;
            S[kind].push(f);
        }
        if (S.tickets.length > 6) S.tickets = S.tickets.slice(0, 6);
        if (S.passports.length > 40) S.passports = S.passports.slice(0, 40);
        renderUpload();
    }
    function remove(kind, i) { saveDefaults(); S[kind].splice(i, 1); renderUpload(); }

    // ------------------------------------------------------------------ step 2: AI reading
    function jobs() {
        return [...S.tickets.map((f, i) => ({ kind: 'ticket', file: f, index: i })), ...S.passports.map((f, i) => ({ kind: 'passport', file: f, index: i }))];
    }

    function readSummary(job, r) {
        if (!r) return '';
        if (!r.success) return `<span class="text-rose-600">${esc(r.message || 'Could not read')}</span>`;
        if (job.kind === 'ticket') {
            const t = r.ticket || {};
            const segs = (t.segments || []).map(s => s.from + '→' + s.to).join(', ');
            return `PNR <b class="font-mono">${esc(t.pnr || '—')}</b> · ${(t.passengers || []).length} traveller(s) · ${esc(segs)}`;
        }
        const p = r.passports || [];
        return p.length ? p.map(x => `<b>${esc((x.given_names + ' ' + x.surname).trim())}</b> <span class="font-mono text-slate-400">${esc(x.passport_no)}</span>`).join(', ') : '<span class="text-amber-600">No passport page found</span>';
    }

    function renderReading() {
        S.step = 2;
        const all = jobs();
        const done = all.filter(j => S.reads.get(j.file)?.done).length;
        const pct = Math.round(done / Math.max(1, all.length) * 100);
        const failed = all.filter(j => S.reads.get(j.file)?.result && !S.reads.get(j.file).result.success);
        const finished = done === all.length;
        shell(`
          <div class="max-w-3xl mx-auto">
            <div class="flex items-center gap-4">
              <div class="relative h-16 w-16 shrink-0">
                <div class="absolute inset-0 rounded-2xl sm-shimmer ${finished ? 'opacity-0' : ''}"></div>
                <div class="absolute inset-[3px] rounded-[14px] bg-white flex items-center justify-center text-2xl ${finished ? 'text-emerald-500' : 'text-violet-600'}"><i class="fa-solid ${finished ? 'fa-circle-check' : 'fa-robot fa-bounce'}"></i></div>
              </div>
              <div class="flex-1">
                <div class="text-sm font-extrabold text-slate-800">${finished ? 'All files read' : 'Gemini AI is reading your files…'}</div>
                <div class="text-[11px] text-slate-500">${done} of ${all.length} done · several keys work in parallel so nothing waits on a rate limit</div>
                <div class="mt-2 h-2.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full sm-shimmer transition-all duration-500" style="width:${pct}%"></div></div>
              </div>
              <div class="text-2xl font-black text-violet-700 tabular-nums">${pct}%</div>
            </div>
            <div class="mt-5 space-y-2">
              ${all.map(j => {
                  const st = S.reads.get(j.file) || {};
                  const icon = !st.started ? '<i class="fa-regular fa-clock text-slate-300"></i>'
                      : !st.done ? '<i class="fa-solid fa-circle-notch fa-spin text-violet-500"></i>'
                      : st.result.success ? '<i class="fa-solid fa-circle-check text-emerald-500"></i>' : '<i class="fa-solid fa-circle-xmark text-rose-500"></i>';
                  return `<div class="flex items-center gap-3 rounded-2xl bg-white border ${st.done && !st.result.success ? 'border-rose-200' : 'border-slate-200'} px-3 py-2.5">
                      <span class="h-9 w-9 shrink-0 rounded-xl flex items-center justify-center ${j.kind === 'ticket' ? 'bg-sky-50 text-sky-600' : 'bg-violet-50 text-violet-600'}"><i class="fa-solid ${j.kind === 'ticket' ? 'fa-plane' : 'fa-passport'}"></i></span>
                      <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold text-slate-800 truncate">${esc(j.file.name)}</div>
                        <div class="text-[11px] text-slate-500 truncate">${st.done ? readSummary(j, st.result) : st.started ? (st.attempt > 1 ? 'Busy — retrying (try ' + st.attempt + ')…' : 'Reading…') : 'Waiting…'}</div>
                      </div>
                      ${st.done && st.result.success && st.result.model ? `<span class="hidden sm:inline text-[10px] font-mono text-slate-400">${esc(st.result.model)} · key ${st.result.key}</span>` : ''}
                      <span class="text-lg">${icon}</span>
                  </div>`;
              }).join('')}
            </div>
          </div>`,
          `<button onclick="SmartMB.back()" class="px-4 py-2 rounded-xl bg-slate-100 text-xs font-semibold"><i class="fa-solid fa-arrow-left mr-1"></i> Back</button>
           <div class="flex gap-2">
             ${finished && failed.length ? `<button onclick="SmartMB.read(true)" class="px-4 py-2 rounded-xl bg-amber-100 text-amber-800 text-xs font-bold"><i class="fa-solid fa-rotate-right mr-1"></i> Retry ${failed.length} failed</button>` : ''}
             ${finished ? `<button onclick="SmartMB.prepare()" class="px-5 py-2.5 rounded-xl text-white text-xs font-extrabold bg-gradient-to-r from-violet-600 to-fuchsia-600 shadow-lg shadow-violet-600/30">Build bookings <i class="fa-solid fa-arrow-right ml-1"></i></button>` : ''}
           </div>`);
    }

    async function readOne(job) {
        const st = { started: true, done: false, attempt: 0 };
        S.reads.set(job.file, st);
        let data = { success: false, message: 'not read' };
        for (let attempt = 1; attempt <= 3; attempt++) {
            st.attempt = attempt;
            renderReading();
            if (attempt > 1) await new Promise(r => setTimeout(r, (attempt - 1) * 6000));
            const fd = new FormData();
            fd.append('kind', job.kind);
            fd.append('file', job.file);
            try {
                const res = await fetch('index.php?api=read_booking_file', { method: 'POST', headers: { 'X-CSRF-Token': csrf() }, body: fd });
                data = await res.json().catch(() => ({ success: false, message: 'Unexpected server response (HTTP ' + res.status + ').' }));
            } catch (e) {
                data = { success: false, message: 'Network error: ' + e.message };
            }
            if (data.success || /supported|10 MB|No file/i.test(data.message || '')) break;
        }
        st.done = true;
        st.result = data;
        renderReading();
    }

    async function read(onlyFailed = false) {
        saveDefaults();
        const todo = jobs().filter(j => {
            const st = S.reads.get(j.file);
            return onlyFailed ? (st?.done && !st.result.success) : !(st?.done && st.result.success);
        });
        todo.forEach(j => S.reads.delete(j.file));
        renderReading();
        let cursor = 0;
        const worker = async () => { while (cursor < todo.length) await readOne(todo[cursor++]); };
        await Promise.all([worker(), worker(), worker()]);
        renderReading();
    }

    // ------------------------------------------------------------------ step 3: review
    async function prepare() {
        const body = {
            tickets: S.tickets.map((f, i) => ({ file: i, name: f.name, ticket: S.reads.get(f)?.result?.success ? S.reads.get(f).result.ticket : null })).filter(t => t.ticket),
            passports: S.passports.map((f, i) => ({ file: i, name: f.name, passports: S.reads.get(f)?.result?.success ? S.reads.get(f).result.passports : [] })),
        };
        document.getElementById('smBody').insertAdjacentHTML('beforeend', '<div class="mt-4 text-center text-xs text-violet-700"><i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Matching passports and building bookings…</div>');
        const res = await apiRequest('index.php?api=smart_master_prepare', body);
        if (!res.success) { toast('error', res.message || 'Could not build the bookings.'); return; }
        S.summary = res.summary;
        S.rows = res.rows.map(r => ({ ...r, include: !r.duplicate && !!r.passport_number }));
        renderReview();
    }

    function badge(cls, icon, text, title = '') {
        return `<span class="inline-flex items-center gap-1 text-[10px] font-bold rounded-full px-2 py-0.5 ${cls}" title="${esc(title)}"><i class="fa-solid ${icon}"></i>${text}</span>`;
    }

    function card(r, i) {
        const male = r.gender === 'M', female = r.gender === 'F';
        const avatar = male ? 'from-sky-500 to-indigo-600' : female ? 'from-pink-500 to-fuchsia-600' : 'from-slate-400 to-slate-500';
        const files = [
            r.ticket_file !== null ? badge('bg-sky-50 text-sky-700', 'fa-plane-arrival', 'Ticket 1', S.tickets[r.ticket_file]?.name) : '',
            r.return_file !== null ? badge('bg-sky-50 text-sky-700', 'fa-plane-departure', 'Ticket 2', S.tickets[r.return_file]?.name) : '',
            r.passport_file !== null ? badge('bg-violet-50 text-violet-700', 'fa-passport', 'Passport' + (r.passport_page > 1 ? ' p.' + r.passport_page : ''), S.passports[r.passport_file]?.name) : '',
        ].join(' ');
        const status = r.duplicate
            ? badge('bg-rose-100 text-rose-700', 'fa-copy', 'Already booked: ' + esc(r.duplicate.code), 'Same passport and arrival date already in Master Bookings')
            : r.issues.length ? badge('bg-amber-100 text-amber-800', 'fa-triangle-exclamation', r.issues.length + ' to check') : badge('bg-emerald-100 text-emerald-700', 'fa-circle-check', 'Ready');
        const field = (key, label, extra = '', type = 'text') => `<label class="block"><span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">${label}</span>
            <input type="${type}" class="sm-in ${extra}" value="${esc(r[key] || '')}" oninput="SmartMB.edit(${i},'${key}',this.value)" ${key === 'passport_number' ? 'required' : ''}></label>`;
        return `<div class="sm-card ${r.include ? '' : 'sm-off'} rounded-2xl bg-white border ${r.duplicate ? 'border-rose-200' : r.issues.length ? 'border-amber-200' : 'border-slate-200'} p-3 shadow-sm" id="smCard${i}">
          <div class="flex items-start gap-3">
            <label class="pt-2 cursor-pointer"><input type="checkbox" class="h-4 w-4 accent-violet-600" ${r.include ? 'checked' : ''} onchange="SmartMB.toggle(${i}, this.checked)"></label>
            <div class="h-11 w-11 shrink-0 rounded-2xl bg-gradient-to-br ${avatar} text-white flex items-center justify-center text-sm font-black shadow-md">${esc(initials(r.passenger_name))}</div>
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-sm font-extrabold text-slate-800">${esc(r.passenger_name || 'Unnamed')}</span>
                ${r.type !== 'Adult' ? badge('bg-slate-100 text-slate-600', 'fa-child', esc(r.type)) : ''}
                ${male ? badge('bg-sky-50 text-sky-600', 'fa-mars', 'Male') : female ? badge('bg-pink-50 text-pink-600', 'fa-venus', 'Female') : ''}
                ${status}
                ${r.earlier.length ? badge('bg-slate-100 text-slate-600', 'fa-clock-rotate-left', r.earlier.length + ' earlier booking(s)', r.earlier.map(e => e.code + ' (' + e.arrival_date + ')').join(', ')) : ''}
              </div>
              <div class="mt-0.5 text-[10px] text-slate-400">${r.ticket_name && r.ticket_name.toUpperCase() !== (r.passenger_name || '').toUpperCase() ? 'On ticket: ' + esc(r.ticket_name) + ' · ' : ''}${r.pnr ? 'PNR <b class="font-mono text-slate-500">' + esc(r.pnr) + '</b> · ' : ''}${r.arrival_route ? esc(r.arrival_route) + ' · ' : ''}${r.expiry ? 'Passport valid to ' + fmtDate(r.expiry) : ''}</div>
              <div class="mt-2 grid grid-cols-2 md:grid-cols-7 gap-2">
                <div class="col-span-2">${field('passenger_name', 'Passenger name', 'font-semibold')}</div>
                ${field('passport_number', 'Passport no.', 'font-mono uppercase' + (r.passport_number ? '' : ' border-rose-300 bg-rose-50'))}
                ${field('flight_number', 'Flight (PAK→KSA)', 'font-mono uppercase')}
                ${field('arrival_date', 'Arrival (KSA)', 'font-mono', 'date')}
                ${field('departure_date', 'Departure (exit)', 'font-mono', 'date')}
                ${field('stay_days', 'Stay', 'font-semibold uppercase')}
              </div>
              ${r.issues.length ? `<div class="mt-2 flex flex-wrap gap-1">${r.issues.map(t => badge('bg-amber-50 text-amber-800 border border-amber-200', 'fa-circle-info', esc(t))).join('')}</div>` : ''}
              <div class="mt-2 flex flex-wrap items-center gap-1">${files}</div>
            </div>
          </div>
        </div>`;
    }

    function renderReview() {
        S.step = 3;
        const sel = S.rows.filter(r => r.include);
        const missing = sel.filter(r => !String(r.passport_number || '').trim());
        const sum = S.summary || {};
        const chip = (icon, n, label, cls) => `<div class="rounded-2xl ${cls} px-4 py-3 flex items-center gap-3"><i class="fa-solid ${icon} text-xl opacity-80"></i><div><div class="text-xl font-black leading-none">${n}</div><div class="text-[10px] font-bold uppercase tracking-wider opacity-70 mt-1">${label}</div></div></div>`;
        shell(`
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5">
            ${chip('fa-users', S.rows.length, 'Travellers', 'bg-indigo-50 text-indigo-800')}
            ${chip('fa-passport', sum.with_passport ?? 0, 'With passport no.', 'bg-violet-50 text-violet-800')}
            ${chip('fa-ticket', (sum.pnrs || []).join(', ') || '—', 'PNR', 'bg-sky-50 text-sky-800')}
            ${chip('fa-copy', sum.duplicates ?? 0, 'Already booked', (sum.duplicates ? 'bg-rose-50 text-rose-800' : 'bg-emerald-50 text-emerald-800'))}
          </div>
          <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <div class="text-[11px] text-slate-500"><i class="fa-solid fa-pen text-violet-400 mr-1"></i> Everything is editable. Untick anyone you don't want to book.</div>
            <div class="flex gap-1.5">
              <button onclick="SmartMB.all(true)" class="text-[11px] font-bold px-3 py-1.5 rounded-full bg-white border border-slate-200 hover:border-violet-300">Select all</button>
              <button onclick="SmartMB.all(false)" class="text-[11px] font-bold px-3 py-1.5 rounded-full bg-white border border-slate-200 hover:border-violet-300">Select none</button>
            </div>
          </div>
          <div class="mt-3 space-y-2.5">${S.rows.map(card).join('') || '<div class="text-center text-sm text-slate-400 py-10">Nobody was found in these files.</div>'}</div>`,
          `<button onclick="SmartMB.toReading()" class="px-4 py-2 rounded-xl bg-slate-100 text-xs font-semibold"><i class="fa-solid fa-arrow-left mr-1"></i> Back</button>
           <div class="flex items-center gap-3">
             ${missing.length ? `<span class="text-[11px] font-semibold text-rose-600"><i class="fa-solid fa-circle-exclamation mr-1"></i>${missing.length} selected without passport number</span>` : ''}
             <button onclick="SmartMB.create()" ${sel.length && !missing.length ? '' : 'disabled'} class="px-6 py-2.5 rounded-xl text-white text-xs font-extrabold bg-gradient-to-r from-emerald-500 to-teal-600 shadow-lg shadow-emerald-600/30 disabled:opacity-40 disabled:shadow-none"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Create ${sel.length} booking${sel.length === 1 ? '' : 's'}</button>
           </div>`);
    }

    // Same rule as SmartMasterBookingController::stayLabel().
    function stayLabel(a, b) {
        if (!a || !b || b <= a) return '';
        const days = Math.round((new Date(b + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000);
        return days < 15 ? 'SHORT STAY' : days > 29 ? 'LONG STAY' : days + ' Days';
    }

    function edit(i, key, value) {
        const r = S.rows[i];
        r[key] = key === 'passport_number' || key === 'flight_number' ? value.toUpperCase().trim() : value;
        if (key === 'stay_days') r._stayManual = value.trim() !== '';
        if ((key === 'arrival_date' || key === 'departure_date') && !r._stayManual) {
            r.stay_days = stayLabel(r.arrival_date, r.departure_date);
            const el = document.querySelector(`#smCard${i} input[oninput*="'stay_days'"]`);
            if (el) el.value = r.stay_days;
        }
        if (key === 'passport_number' && !!value.trim() !== !!S.rows[i]._hadPassport) { S.rows[i]._hadPassport = !!value.trim(); renderReviewKeepFocus(i, key); }
    }
    function renderReviewKeepFocus(i, key) {
        const pos = document.activeElement?.selectionStart;
        renderReview();
        const el = document.querySelector(`#smCard${i} input[oninput*="'${key}'"]`);
        if (el) { el.focus(); try { el.setSelectionRange(pos, pos); } catch (e) {} }
    }
    function toggle(i, on) { S.rows[i].include = on; renderReview(); }
    function all(on) { S.rows.forEach(r => { r.include = on; }); renderReview(); }

    // ------------------------------------------------------------------ step 4: create + attach
    let pdfLibPromise = null;
    function pdfLib() {
        if (window.PDFLib) return Promise.resolve(window.PDFLib);
        pdfLibPromise ??= new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = PDF_LIB;
            s.onload = () => resolve(window.PDFLib);
            s.onerror = () => reject(new Error('Could not load the PDF tool.'));
            document.head.appendChild(s);
        });
        return pdfLibPromise;
    }

    async function imageToJpegBytes(file) {
        const url = URL.createObjectURL(file);
        try {
            const img = await new Promise((res, rej) => { const im = new Image(); im.onload = () => res(im); im.onerror = rej; im.src = url; });
            const c = document.createElement('canvas');
            c.width = img.naturalWidth; c.height = img.naturalHeight;
            c.getContext('2d').drawImage(img, 0, 0);
            const blob = await new Promise(res => c.toBlob(res, 'image/jpeg', 0.9));
            return new Uint8Array(await blob.arrayBuffer());
        } finally { URL.revokeObjectURL(url); }
    }

    const pdfCache = new Map();
    /** PDF bytes for a file (photos become an A4 PDF); with page > 0, only that page of a multi-page PDF. */
    function pdfFor(file, page = 0) {
        const key = file.name + '|' + file.size + '|' + page;
        if (!pdfCache.has(key)) pdfCache.set(key, (async () => {
            const isPdf = /pdf/i.test(file.type) || /\.pdf$/i.test(file.name);
            if (isPdf && !page) return new Uint8Array(await file.arrayBuffer());
            const { PDFDocument } = await pdfLib();
            if (isPdf) {
                const src = await PDFDocument.load(await file.arrayBuffer(), { ignoreEncryption: true });
                if (src.getPageCount() <= 1 || page > src.getPageCount()) return new Uint8Array(await file.arrayBuffer());
                const out = await PDFDocument.create();
                const [p] = await out.copyPages(src, [page - 1]);
                out.addPage(p);
                return await out.save();
            }
            const doc = await PDFDocument.create();
            const img = /png/i.test(file.type) ? await doc.embedPng(new Uint8Array(await file.arrayBuffer())) : await doc.embedJpg(/jpe?g/i.test(file.type) ? new Uint8Array(await file.arrayBuffer()) : await imageToJpegBytes(file));
            const A4 = [595.28, 841.89], m = 24;
            const scale = Math.min((A4[0] - m * 2) / img.width, (A4[1] - m * 2) / img.height, 1);
            const w = img.width * scale, h = img.height * scale;
            const pg = doc.addPage(A4);
            pg.drawImage(img, { x: (A4[0] - w) / 2, y: A4[1] - m - h, width: w, height: h });
            return await doc.save();
        })());
        return pdfCache.get(key);
    }

    async function attach(bookingId, type, bytes, name) {
        const fd = new FormData();
        fd.append('booking_id', bookingId);
        fd.append('type', type);
        fd.append('file', new File([bytes], name.replace(/[\\/:*?"<>|]+/g, ' ').slice(0, 120) + '.pdf', { type: 'application/pdf' }));
        const res = await fetch('index.php?api=upload_booking_file', { method: 'POST', headers: { 'X-CSRF-Token': csrf() }, body: fd });
        const data = await res.json().catch(() => ({ success: false, message: 'HTTP ' + res.status }));
        if (!data.success) throw new Error(type.replace('_', ' ') + ': ' + (data.message || 'upload failed'));
    }

    function renderCreating() {
        S.step = 4;
        const sel = S.rows.filter(r => r.include);
        const done = sel.filter(r => r._state === 'done' || r._state === 'error').length;
        const ok = sel.filter(r => r._state === 'done');
        const finished = done === sel.length;
        shell(`
          <div class="max-w-3xl mx-auto">
            ${finished ? `<div class="text-center py-3">
                <div class="sm-burst mx-auto h-20 w-20 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 text-white flex items-center justify-center text-4xl shadow-xl shadow-emerald-500/40"><i class="fa-solid fa-check"></i></div>
                <div class="mt-3 text-lg font-black text-slate-800">${ok.length} booking${ok.length === 1 ? '' : 's'} created!</div>
                <div class="text-[11px] text-slate-500">Tickets and passports are attached to each booking.</div>
              </div>` : `<div class="flex items-center gap-3"><i class="fa-solid fa-circle-notch fa-spin text-violet-600 text-2xl"></i><div class="flex-1"><div class="text-sm font-extrabold text-slate-800">Creating bookings & attaching files…</div>
                <div class="mt-2 h-2.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full sm-shimmer transition-all" style="width:${Math.round(done / Math.max(1, sel.length) * 100)}%"></div></div></div><div class="font-black text-violet-700">${done}/${sel.length}</div></div>`}
            <div class="mt-4 space-y-2">
              ${sel.map(r => `<div class="flex items-center gap-3 rounded-2xl bg-white border ${r._state === 'error' ? 'border-rose-200' : 'border-slate-200'} px-3 py-2.5">
                  <span class="text-lg">${r._state === 'done' ? '<i class="fa-solid fa-circle-check text-emerald-500"></i>' : r._state === 'error' ? '<i class="fa-solid fa-circle-xmark text-rose-500"></i>' : r._state ? '<i class="fa-solid fa-circle-notch fa-spin text-violet-500"></i>' : '<i class="fa-regular fa-clock text-slate-300"></i>'}</span>
                  <div class="flex-1 min-w-0"><div class="text-xs font-bold text-slate-800">${esc(r.passenger_name)} <span class="font-mono text-slate-400 font-normal">${esc(r.passport_number)}</span></div>
                    <div class="text-[11px] ${r._state === 'error' ? 'text-rose-600' : 'text-slate-500'}">${esc(r._msg || 'Waiting…')}</div></div>
                  ${r._code ? `<span class="font-mono text-[11px] font-bold text-indigo-700 bg-indigo-50 rounded-lg px-2 py-1">${esc(r._code)}</span>` : ''}
              </div>`).join('')}
            </div>
          </div>`,
          finished ? `<span class="text-[11px] text-slate-500">${sel.length - ok.length ? (sel.length - ok.length) + ' could not be created — see the messages above.' : 'All done.'}</span>
             <button onclick="location.reload()" class="px-6 py-2.5 rounded-xl text-white text-xs font-extrabold bg-gradient-to-r from-indigo-600 to-violet-600"><i class="fa-solid fa-list-check mr-1"></i> View Master Bookings</button>` : '');
    }

    async function createOne(r) {
        const d = S.defaults;
        r._state = 'saving'; r._msg = 'Saving booking…'; renderCreating();
        const payload = {
            booking_date: d.booking_date || today(), agent_id: d.agent_id, vendor_id: d.vendor_id, status: d.status || 'draft',
            passenger_name: r.passenger_name.trim(), passport_number: r.passport_number.trim(), flight_number: r.flight_number,
            arrival_date: r.arrival_date, departure_date: r.departure_date, stay_days: r.stay_days,
            buy_rate_sar: d.visa_buy || 0, sell_rate_sar: d.visa_sell || 0, ticket_buy_rate_sar: d.ticket_buy || 0, ticket_sell_rate_sar: d.ticket_sell || 0,
            remarks: [d.notes, r.pnr ? 'PNR ' + r.pnr : ''].filter(Boolean).join(' · '),
            custom_field1: '', custom_field2: '', include_hotel: false, include_transport: false, hotel_stays: [], transport_transfers: [],
        };
        const res = await apiRequest('index.php?api=save_booking', payload);
        if (!res.success) { r._state = 'error'; r._msg = res.message || 'Could not save.'; renderCreating(); return; }
        r._code = res.booking_code;
        const problems = [];
        const files = [];
        if (r.ticket_file !== null && S.tickets[r.ticket_file]) files.push(['ticket_1', S.tickets[r.ticket_file], 0, 'Ticket ' + (r.pnr || '') + ' ' + r.passenger_name]);
        if (r.return_file !== null && S.tickets[r.return_file]) files.push(['ticket_2', S.tickets[r.return_file], 0, 'Return Ticket ' + r.passenger_name]);
        if (r.passport_file !== null && S.passports[r.passport_file]) files.push(['passport', S.passports[r.passport_file], r.passport_page || 1, 'Passport ' + r.passenger_name]);
        for (const [type, file, page, name] of files) {
            r._msg = 'Attaching ' + type.replace('_', ' ') + '…'; renderCreating();
            try { await attach(res.booking_id, type, await pdfFor(file, type === 'passport' ? page : 0), name); }
            catch (e) { problems.push(e.message); }
        }
        r._state = 'done';
        r._msg = problems.length ? 'Created — but ' + problems.join('; ') : 'Created with ' + files.length + ' attachment' + (files.length === 1 ? '' : 's');
        renderCreating();
    }

    async function create() {
        const sel = S.rows.filter(r => r.include);
        sel.forEach(r => { r._state = ''; r._msg = ''; r._code = ''; });
        renderCreating();
        let cursor = 0;
        const worker = async () => { while (cursor < sel.length) await createOne(sel[cursor++]); };
        await Promise.all([worker(), worker()]);
        renderCreating();
    }

    // ------------------------------------------------------------------ public
    window.SmartMB = {
        state: S,
        open() { S.tickets = []; S.passports = []; S.reads = new Map(); S.rows = []; S.defaults = {}; S.defaultsOpen = false; renderUpload(); },
        add, remove, read, prepare, edit, toggle, all, create, pdfFor,
        back() { renderUpload(); },
        toReading() { renderReading(); },
    };
})();
