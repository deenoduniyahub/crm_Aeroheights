/**
 * Ticket Reader — pulls PNR, passengers and flight segments out of any airline / portal ticket.
 *
 * 1. PDFs with real text are read with pdf.js and rebuilt into visual lines (columns kept apart).
 * 2. Scanned PDFs and images (JPG/PNG/WEBP screenshots) are read with Tesseract OCR in the browser.
 * 3. The lines go through format-specific readers (AirSial) or the generic reader, which walks the
 *    page top-to-bottom and assembles flights from the tokens it meets (flight no., airports, dates, times).
 *
 * Everything runs in the browser; the server only receives the reviewed result. The parser part has no
 * DOM dependency so it can also be exercised from Node (module.exports).
 */
(function (root) {
    'use strict';

    // ------------------------------------------------------------------
    // Reference data
    // ------------------------------------------------------------------
    const AIRLINES = {
        SV: 'Saudia', PK: 'PIA', PA: 'Airblue', PF: 'AirSial', FZ: 'flydubai', '9P': 'Fly Jinnah',
        ER: 'Serene Air', XY: 'flynas', F3: 'flyadeal', OV: 'SalamAir', G9: 'Air Arabia', '3L': 'Air Arabia Abu Dhabi',
        E5: 'Air Arabia Egypt', EK: 'Emirates', QR: 'Qatar Airways', EY: 'Etihad Airways', GF: 'Gulf Air',
        WY: 'Oman Air', KU: 'Kuwait Airways', J9: 'Jazeera Airways', TK: 'Turkish Airlines', PC: 'Pegasus Airlines',
        MS: 'EgyptAir', RJ: 'Royal Jordanian', ME: 'Middle East Airlines', IA: 'Iraqi Airways', UL: 'SriLankan Airlines',
        BG: 'Biman Bangladesh', TG: 'Thai Airways', MH: 'Malaysia Airlines', SQ: 'Singapore Airlines', BA: 'British Airways',
        VS: 'Virgin Atlantic', LH: 'Lufthansa', AF: 'Air France', KL: 'KLM', LX: 'Swiss', OS: 'Austrian Airlines',
        AZ: 'ITA Airways', ET: 'Ethiopian Airlines', KQ: 'Kenya Airways', AI: 'Air India', '6E': 'IndiGo',
        MU: 'China Eastern', CZ: 'China Southern', CA: 'Air China', NE: 'Nesma Airlines', W5: 'Mahan Air',
        RQ: 'Kam Air', FG: 'Ariana Afghan', HY: 'Uzbekistan Airways', KC: 'Air Astana', AT: 'Royal Air Maroc', TU: 'Tunisair'
    };

    // Text that names an airline even when no flight number is readable (e.g. a logo-only screenshot).
    const AIRLINE_NAMES = [
        [/saudi\s*arabian\s*airlines|\bsaudia\b/i, 'SV'], [/pakistan\s*international|\bPIA\b/, 'PK'], [/air\s?blue/i, 'PA'],
        [/air\s?sial/i, 'PF'], [/fly\s?dubai/i, 'FZ'], [/fly\s?jinnah/i, '9P'], [/serene\s*air/i, 'ER'], [/fly\s?nas/i, 'XY'],
        [/fly\s?adeal/i, 'F3'], [/salam\s?air/i, 'OV'], [/air\s*arabia/i, 'G9'], [/emirates/i, 'EK'], [/qatar\s*airways/i, 'QR'],
        [/etihad/i, 'EY'], [/gulf\s*air/i, 'GF'], [/oman\s*air/i, 'WY'], [/turkish\s*airlines/i, 'TK'], [/kuwait\s*airways/i, 'KU'],
        [/jazeera/i, 'J9'], [/egypt\s?air/i, 'MS'], [/royal\s*jordanian/i, 'RJ']
    ];

    // IATA code => [City, Airport, Country]
    const AIRPORTS = {
        LHE: ['Lahore', 'Allama Iqbal International', 'Pakistan'], KHI: ['Karachi', 'Jinnah International', 'Pakistan'],
        ISB: ['Islamabad', 'Islamabad International', 'Pakistan'], PEW: ['Peshawar', 'Bacha Khan International', 'Pakistan'],
        MUX: ['Multan', 'Multan International', 'Pakistan'], SKT: ['Sialkot', 'Sialkot International', 'Pakistan'],
        LYP: ['Faisalabad', 'Faisalabad International', 'Pakistan'], UET: ['Quetta', 'Quetta International', 'Pakistan'],
        GWD: ['Gwadar', 'New Gwadar International', 'Pakistan'], RYK: ['Rahim Yar Khan', 'Shaikh Zayed International', 'Pakistan'],
        SKZ: ['Sukkur', 'Sukkur Airport', 'Pakistan'], DEA: ['Dera Ghazi Khan', 'D.G. Khan International', 'Pakistan'],
        BHV: ['Bahawalpur', 'Bahawalpur Airport', 'Pakistan'],
        JED: ['Jeddah', 'King Abdulaziz International', 'Saudi Arabia'], MED: ['Madinah', 'Prince Mohammad bin Abdulaziz Intl', 'Saudi Arabia'],
        RUH: ['Riyadh', 'King Khalid International', 'Saudi Arabia'], DMM: ['Dammam', 'King Fahd International', 'Saudi Arabia'],
        TIF: ['Taif', 'Taif International', 'Saudi Arabia'], TUU: ['Tabuk', 'Prince Sultan bin Abdulaziz', 'Saudi Arabia'],
        AHB: ['Abha', 'Abha International', 'Saudi Arabia'], ELQ: ['Qassim', 'Prince Naif bin Abdulaziz', 'Saudi Arabia'],
        GIZ: ['Jazan', 'King Abdullah bin Abdulaziz', 'Saudi Arabia'], YNB: ['Yanbu', 'Prince Abdul Mohsin bin Abdulaziz', 'Saudi Arabia'],
        HAS: ['Hail', 'Hail International', 'Saudi Arabia'], ULH: ['AlUla', 'AlUla International', 'Saudi Arabia'],
        DXB: ['Dubai', 'Dubai International', 'UAE'], DWC: ['Dubai', 'Al Maktoum International', 'UAE'],
        SHJ: ['Sharjah', 'Sharjah International', 'UAE'], AUH: ['Abu Dhabi', 'Zayed International', 'UAE'],
        RKT: ['Ras Al Khaimah', 'Ras Al Khaimah International', 'UAE'], MCT: ['Muscat', 'Muscat International', 'Oman'],
        SLL: ['Salalah', 'Salalah International', 'Oman'], DOH: ['Doha', 'Hamad International', 'Qatar'],
        BAH: ['Bahrain', 'Bahrain International', 'Bahrain'], KWI: ['Kuwait', 'Kuwait International', 'Kuwait'],
        IST: ['Istanbul', 'Istanbul Airport', 'Turkey'], SAW: ['Istanbul', 'Sabiha Gokcen', 'Turkey'],
        CAI: ['Cairo', 'Cairo International', 'Egypt'], AMM: ['Amman', 'Queen Alia International', 'Jordan'],
        BEY: ['Beirut', 'Rafic Hariri International', 'Lebanon'], BGW: ['Baghdad', 'Baghdad International', 'Iraq'],
        NJF: ['Najaf', 'Al Najaf International', 'Iraq'], KBL: ['Kabul', 'Kabul International', 'Afghanistan'],
        DEL: ['Delhi', 'Indira Gandhi International', 'India'], BOM: ['Mumbai', 'Chhatrapati Shivaji Maharaj Intl', 'India'],
        DAC: ['Dhaka', 'Hazrat Shahjalal International', 'Bangladesh'], CMB: ['Colombo', 'Bandaranaike International', 'Sri Lanka'],
        KUL: ['Kuala Lumpur', 'Kuala Lumpur International', 'Malaysia'], BKK: ['Bangkok', 'Suvarnabhumi', 'Thailand'],
        SIN: ['Singapore', 'Changi', 'Singapore'], CGK: ['Jakarta', 'Soekarno-Hatta International', 'Indonesia'],
        TAS: ['Tashkent', 'Islam Karimov Tashkent Intl', 'Uzbekistan'], ALA: ['Almaty', 'Almaty International', 'Kazakhstan'],
        LHR: ['London', 'Heathrow', 'United Kingdom'], LGW: ['London', 'Gatwick', 'United Kingdom'],
        MAN: ['Manchester', 'Manchester Airport', 'United Kingdom'], BHX: ['Birmingham', 'Birmingham Airport', 'United Kingdom'],
        GLA: ['Glasgow', 'Glasgow Airport', 'United Kingdom'], MXP: ['Milan', 'Malpensa', 'Italy'], FCO: ['Rome', 'Fiumicino', 'Italy'],
        BCN: ['Barcelona', 'El Prat', 'Spain'], CDG: ['Paris', 'Charles de Gaulle', 'France'], FRA: ['Frankfurt', 'Frankfurt Airport', 'Germany'],
        MUC: ['Munich', 'Munich Airport', 'Germany'], OSL: ['Oslo', 'Gardermoen', 'Norway'], CPH: ['Copenhagen', 'Kastrup', 'Denmark'],
        ARN: ['Stockholm', 'Arlanda', 'Sweden'], BRU: ['Brussels', 'Brussels Airport', 'Belgium'], ZRH: ['Zurich', 'Zurich Airport', 'Switzerland'],
        VIE: ['Vienna', 'Vienna International', 'Austria'], ATH: ['Athens', 'Athens International', 'Greece'],
        JFK: ['New York', 'John F. Kennedy International', 'USA'], IAD: ['Washington', 'Dulles International', 'USA'],
        ORD: ['Chicago', "O'Hare International", 'USA'], YYZ: ['Toronto', 'Pearson International', 'Canada']
    };

    // City spellings found on tickets => IATA code
    const CITY_CODES = {
        'lahore': 'LHE', 'karachi': 'KHI', 'islamabad': 'ISB', 'peshawar': 'PEW', 'multan': 'MUX', 'sialkot': 'SKT',
        'faisalabad': 'LYP', 'quetta': 'UET', 'gwadar': 'GWD', 'rahim yar khan': 'RYK', 'sukkur': 'SKZ', 'bahawalpur': 'BHV',
        'jeddah': 'JED', 'jedda': 'JED', 'jiddah': 'JED', 'madinah': 'MED', 'madina': 'MED', 'medina': 'MED', 'al madinah': 'MED',
        'riyadh': 'RUH', 'dammam': 'DMM', 'taif': 'TIF', 'tabuk': 'TUU', 'abha': 'AHB', 'qassim': 'ELQ', 'jazan': 'GIZ', 'yanbu': 'YNB',
        'dubai': 'DXB', 'sharjah': 'SHJ', 'abu dhabi': 'AUH', 'muscat': 'MCT', 'doha': 'DOH', 'bahrain': 'BAH', 'kuwait': 'KWI',
        'istanbul': 'IST', 'cairo': 'CAI', 'amman': 'AMM', 'baghdad': 'BGW', 'najaf': 'NJF', 'kabul': 'KBL', 'milan': 'MXP',
        'london': 'LHR', 'manchester': 'MAN', 'birmingham': 'BHX', 'glasgow': 'GLA', 'paris': 'CDG', 'rome': 'FCO',
        'barcelona': 'BCN', 'frankfurt': 'FRA', 'kuala lumpur': 'KUL', 'bangkok': 'BKK', 'tashkent': 'TAS'
    };

    const MONTHS = { jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12 };
    const MON = '(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)';

    // Codes that collide with ordinary words / equipment names are left out on purpose (A3 = "A330", WN, MAD...).
    const FLIGHT_CODES = Object.keys(AIRLINES).map(c => c.replace(/[^A-Z0-9]/g, '')).join('|');
    const RX_FLIGHT = new RegExp('(?<![A-Z0-9])(' + FLIGHT_CODES + ')\\s?[-:]?\\s?(\\d{2,4})(?!\\d)', 'g');
    const RX_FLIGHT_PAREN = /\(([A-Z0-9]{2})\)\s*(\d{1,4})(?!\d)/g;
    const RX_CITY = new RegExp('\\b(' + Object.keys(CITY_CODES).sort((a, b) => b.length - a.length).map(c => c.replace(/ /g, '\\s+')).join('|') + ')\\b', 'gi');
    const RX_CODE = new RegExp('(?<![A-Za-z])(' + Object.keys(AIRPORTS).join('|') + ')(?![A-Za-z])', 'g');
    const RX_TIME = /(?<![\d:.])([01]?\d|2[0-3]):([0-5]\d)(?![\d:])(?:\s?([AaPp])\.?\s?[Mm]\.?(?![a-z]))?/g;
    const RX_DATE_DMY = new RegExp('(?<![\\d])(\\d{1,2})(?:st|nd|rd|th)?[\\s\\-\\/\\.]*' + MON + '(?![a-z])\\.?(?:[\\s\\-\\/\\.,]*(\\d{4}|\\d{2})(?![\\d:]))?', 'gi');
    const RX_DATE_MDY = new RegExp('(?<![a-z])' + MON + '\\.?\\s+(\\d{1,2})(?:st|nd|rd|th)?,?\\s+(\\d{4})(?!\\d)', 'gi');
    const RX_DATE_ISO = /(?<!\d)(20\d{2})-(\d{2})-(\d{2})(?!\d)/g;
    const RX_DATE_NUM = /(?<![\d\/])(\d{1,2})[\/\-.](\d{1,2})[\/\-.](20\d{2})(?!\d)/g;
    const RX_BAGGAGE = /(?<![\d.,])(\d{1,2}(?:\s*\+\s*\d{1,2})?)\s*(?:KGS?|Kgs?|kgs?)\b|(\d)\s*(?:PCS?|Pieces?|Piece\(s\))\b/g;
    const RX_DURATION = /(?:duration\s*:?\s*(\d{1,2}):(\d{2}))|(?<![\d:])(\d{1,2})\s*h(?:rs?|ours?)?\s*:?\s*(\d{1,2})\s*m(?:in|ins)?\b/i;

    // Lines whose dates/times describe the booking itself, not a flight.
    const RX_META = /(booking\s*date|date\s*of\s*(booking|issue)|issue[ds]?\b|issuance|printed|print\s*date|payment|held\s+until|valid\s+(until|till)|expir|time\s*limit|^\s*date\s*[:\-]|viewtrip|^\s*\d{1,2}\/\d{1,2}\/\d{2,4},\s*\d|generated|created\s*on)/i;
    const RX_LAYOVER = /(layover|transit|connection\s*time|stop\s*over)/i;
    const RX_ADDRESS = /(address|tel\s*:|phone|e-?mail|booked\s*by|call\s*cent|office|branch|^\s*name\s*:|contact\s*(no|:))/i;

    const NAME_STOP = new Set(['ON', 'REQUEST', 'CONFIRM', 'CONFIRMED', 'STATUS', 'OK', 'HK', 'ADULT', 'CHILD', 'INFANT', 'PASSPORT', 'ETICKET',
        'TICKET', 'NUMBER', 'CHD', 'INF', 'ADT', 'PAX', 'NIL', 'ECONOMY', 'BUSINESS', 'YES', 'NO', 'PNR', 'CNIC', 'FARE', 'MEAL', 'SEAT']);

    // ------------------------------------------------------------------
    // Small helpers
    // ------------------------------------------------------------------
    const pad = n => String(n).padStart(2, '0');
    const isoDate = (y, m, d) => (y && m && d && m <= 12 && d <= 31) ? `${y}-${pad(m)}-${pad(d)}` : '';
    const addDays = (iso, n) => { const t = new Date(iso + 'T00:00:00Z'); t.setUTCDate(t.getUTCDate() + n); return t.toISOString().slice(0, 10); };
    const daysBetween = (a, b) => Math.round((Date.parse(b + 'T00:00:00Z') - Date.parse(a + 'T00:00:00Z')) / 86400000);
    const titleCase = s => s.toLowerCase().replace(/\b[a-z]/g, c => c.toUpperCase());
    const squash = s => s.replace(/\s+/g, ' ').trim();
    const isProse = line => (line.match(/\b[a-z]{3,}\b/g) || []).length >= 6;

    function monthNo(word) { return MONTHS[String(word).slice(0, 3).toLowerCase()] || 0; }
    function fullYear(y) { if (!y) return 0; y = parseInt(y, 10); return y < 100 ? 2000 + y : y; }

    // ------------------------------------------------------------------
    // pdf.js text items => visual lines
    // ------------------------------------------------------------------
    function linesFromTextItems(items) {
        const parts = items.filter(i => typeof i.str === 'string' && i.str.trim() !== '').map(i => ({
            s: i.str, x: i.transform[4], y: i.transform[5], w: i.width || 0,
            h: Math.abs(i.transform[3]) || Math.abs(i.height) || 8
        }));
        parts.sort((a, b) => b.y - a.y || a.x - b.x);
        const rows = [];
        for (const p of parts) {
            let row = null;
            for (let r = rows.length - 1; r >= 0 && r >= rows.length - 4; r--) {
                if (Math.abs(rows[r].y - p.y) <= Math.max(1.5, Math.min(rows[r].h, p.h) * 0.4)) { row = rows[r]; break; }
            }
            if (!row) { row = { y: p.y, h: p.h, parts: [] }; rows.push(row); }
            row.parts.push(p);
        }
        return rows.map(row => {
            row.parts.sort((a, b) => a.x - b.x);
            let out = '', end = null;
            for (const p of row.parts) {
                if (end !== null) {
                    const gap = p.x - end;
                    if (gap > p.h * 1.2) out += '    ';
                    else if (gap > p.h * 0.12 && !out.endsWith(' ') && !p.s.startsWith(' ')) out += ' ';
                }
                out += p.s;
                end = p.x + p.w;
            }
            return out.replace(/\s+$/, '');
        }).filter(l => l.trim() !== '');
    }

    // ------------------------------------------------------------------
    // Token extraction for one line
    // ------------------------------------------------------------------
    function findDates(line, refYear) {
        const out = [], taken = [];
        const push = (idx, len, y, m, d, explicitYear) => {
            if (taken.some(([a, b]) => idx < b && idx + len > a)) return;
            const iso = isoDate(y, m, d);
            if (!iso) return;
            taken.push([idx, idx + len]);
            out.push({ type: 'date', idx, value: iso, explicitYear });
        };
        let m;
        RX_DATE_ISO.lastIndex = 0;
        while ((m = RX_DATE_ISO.exec(line))) push(m.index, m[0].length, +m[1], +m[2], +m[3], true);
        RX_DATE_MDY.lastIndex = 0;
        while ((m = RX_DATE_MDY.exec(line))) push(m.index, m[0].length, +m[3], monthNo(m[1]), +m[2], true);
        RX_DATE_DMY.lastIndex = 0;
        while ((m = RX_DATE_DMY.exec(line))) push(m.index, m[0].length, fullYear(m[3]) || refYear, monthNo(m[2]), +m[1], !!m[3]);
        RX_DATE_NUM.lastIndex = 0;
        while ((m = RX_DATE_NUM.exec(line))) push(m.index, m[0].length, +m[3], +m[2], +m[1], true);
        return out;
    }

    function findTimes(line) {
        const out = [];
        let m;
        RX_TIME.lastIndex = 0;
        while ((m = RX_TIME.exec(line))) {
            const before = line.slice(Math.max(0, m.index - 14), m.index);
            const after = line.slice(m.index + m[0].length, m.index + m[0].length + 8);
            if (/duration\s*:?\s*$/i.test(before) || /^\s*(h\b|hrs?\b|hours?\b|mins?\b|m\b)/i.test(after)) continue;
            let h = +m[1];
            const ap = m[3] ? m[3].toUpperCase() : '';
            if (ap === 'P' && h < 12) h += 12;
            if (ap === 'A' && h === 12) h = 0;
            out.push({ type: 'time', idx: m.index, value: `${pad(h)}:${m[2]}`, hasMeridiem: !!ap, rawHour: +m[1] });
        }
        return out;
    }

    function findAirports(line) {
        const hits = [];
        let m;
        RX_CODE.lastIndex = 0;
        while ((m = RX_CODE.exec(line))) hits.push({ type: 'airport', idx: m.index, value: m[1] });
        RX_CITY.lastIndex = 0;
        while ((m = RX_CITY.exec(line))) hits.push({ type: 'airport', idx: m.index, value: CITY_CODES[m[1].toLowerCase().replace(/\s+/g, ' ')] });
        hits.sort((a, b) => a.idx - b.idx);
        // "Milan (MXP)" / "Lahore [LHE]" name the same airport twice in a row — keep one.
        return hits.filter((h, i) => i === 0 || h.value !== hits[i - 1].value);
    }

    function findFlights(line) {
        const out = [];
        let m;
        RX_FLIGHT.lastIndex = 0;
        while ((m = RX_FLIGHT.exec(line))) out.push({ type: 'flight', idx: m.index, value: m[1] + m[2] });
        RX_FLIGHT_PAREN.lastIndex = 0;
        while ((m = RX_FLIGHT_PAREN.exec(line))) {
            if (AIRLINES[m[1]] && !out.some(f => f.value === m[1] + m[2])) out.push({ type: 'flight', idx: m.index, value: m[1] + m[2] });
        }
        return out.sort((a, b) => a.idx - b.idx);
    }

    function findBaggage(line) {
        if (/emission|co2/i.test(line) || (/hand|cabin|carry|personal/i.test(line) && !/check/i.test(line))) return [];
        const out = [];
        let m;
        RX_BAGGAGE.lastIndex = 0;
        while ((m = RX_BAGGAGE.exec(line))) {
            const v = m[1] ? m[1].replace(/\s+/g, '') + ' KG' : m[2] + ' PC';
            out.push({ type: 'baggage', idx: m.index, value: v });
        }
        return out;
    }

    // ------------------------------------------------------------------
    // Field readers (whole document)
    // ------------------------------------------------------------------
    const PNR_STOP = new Set(['STATUS', 'NUMBER', 'BOOKING', 'CONFIRMED', 'ECONOMY', 'REFERENCE', 'DETAILS', 'NUMBER', 'TICKET', 'FLIGHT', 'PASSENGER']);
    function readPnr(text) {
        const rx = [
            /\bPNR\s*(?:No\.?|Number|Code)?\s*[:#\-]?\s*([A-Z0-9]{5,8})\b/i,
            /Airline\s*(?:Ref(?:erence)?|PNR|Booking\s*Ref)\.?\s*[:#\-]?\s*([A-Z0-9]{5,8})\b/i,
            /Booking\s*Ref(?:erence)?\s*(?:No\.?)?\s*[:#\-]?\s*(?:[A-Z0-9]{2}\/)?([A-Z0-9]{5,8})\b/i,
            /Confirmation\s*(?:Number|No\.?|Code)\s*[:#\-]?\s*([A-Z0-9]{5,8})\b/i,
            /\b([A-Z0-9]{6})\s*\/\s*[A-Z0-9]{5,8}\s*[\r\n]+[^\r\n]*booking\s*reference/i,
            /(?:Record\s*Locator|Reservation\s*(?:Code|Number|No\.?)|CRS\s*Ref)\s*[:#\-]?\s*([A-Z0-9]{5,8})\b/i
        ];
        for (const r of rx) {
            const all = text.match(new RegExp(r.source, r.flags + 'g')) || [];
            for (const hit of all) {
                const v = (hit.match(r) || [])[1] || '';
                if (v && v === v.toUpperCase() && /[A-Z]/.test(v) && !PNR_STOP.has(v)) return v;
            }
        }
        return '';
    }

    function readStatus(text) {
        if (/on\s*request/i.test(text)) return 'On Request';
        if (/partial(ly)?\s*confirm/i.test(text)) return 'Partially Confirmed';
        if (/not\s+valid\s+for\s+travel|held\s+until\s+payment|on\s*hold/i.test(text)) return 'On Hold';
        if (/cancel+ed/i.test(text) && !/confirm/i.test(text)) return 'Cancelled';
        if (/confirm/i.test(text)) return 'Confirmed';
        return 'Confirmed';
    }

    function readCabin(text) {
        const m = text.match(/\b(premium\s+economy|economy|business|first\s+class)\b(?:\s+(lite|value|flex|standard|saver|classic|plus|light|basic))?/i);
        return m ? titleCase(squash(m[0])) : 'Economy';
    }

    function cleanName(raw) {
        let words = squash(String(raw).replace(/[^A-Za-z' \-]/g, ' ')).toUpperCase().split(' ').filter(Boolean);
        while (words.length && NAME_STOP.has(words[words.length - 1])) words.pop();
        if (words[0] === 'FNU' && words.length > 1) words.shift();
        return words.join(' ');
    }

    function paxType(title, context) {
        if (/\(?\b(infant|INF)\b\)?/i.test(context)) return 'Infant';
        if (/\(?\b(child|CHD|CHLD)\b\)?/i.test(context) || /^(MSTR|MASTER)$/.test(title)) return 'Child';
        return 'Adult';
    }

    function normTitle(t) {
        t = String(t || '').toUpperCase().replace(/\./g, '');
        if (t === 'MASTER' || t === 'MST') return 'MSTR';
        return t;
    }

    function readPassengers(lines) {
        const list = [];
        const seen = new Set();
        const add = (title, name, line) => {
            name = cleanName(name);
            if (name.length < 3 || seen.has(name)) return null;
            seen.add(name);
            title = normTitle(title);
            const passport = (line.match(/\b([A-Z]{1,2}\d{6,8})\b/) || [])[1] || '';
            const ticket = ((line.match(/\b(\d{3})[\s\-]?(\d{10})\b/) || []).slice(1, 3).join(''));
            const p = { title, name, type: paxType(title, line), passport, ticket_no: ticket };
            list.push(p);
            return p;
        };

        const rxNumbered = /Passenger\s*#?\s*\d+\s*[:\-]\s*((?:Mr|MR|Mrs|MRS|Ms|MS|Miss|MISS|Mstr|MSTR)\.?\s+)?([A-Z][A-Za-z'\-]*(?:\s[A-Z][A-Za-z'\-]*){0,5})/;
        const rxSlash = /^\s*([A-Z][A-Z'\- ]{1,40})\/([A-Z][A-Z'\- ]{1,40}?)\s+(MR|MRS|MS|MISS|MSTR|MASTER|INF|CHD)\b/;
        const rxComma = /^\s*([A-Z][A-Z'\- ]{1,40}),\s*([A-Z][A-Z'\- ]{1,40}?)\s+(MR|MRS|MS|MISS|MSTR|MASTER)\b/;
        const rxTitle = /(?:^|[\s#:.\d|])(MRS|Mrs|MR|Mr|MS|Ms|MISS|Miss|MSTR|Mstr|MASTER|Master)\.?\s+([A-Z][A-Z'\-]+(?:\s[A-Z][A-Z'\-]+){0,5})/g;

        for (const line of lines) {
            let m;
            if ((m = line.match(rxNumbered))) { add(m[1] ? m[1].trim() : '', m[2], line); continue; }
            if ((m = line.match(rxSlash))) { add(m[3], m[2] + ' ' + m[1], line); continue; }
            if ((m = line.match(rxComma))) { add(m[3], m[2] + ' ' + m[1], line); continue; }
            if (isProse(line)) continue;
            rxTitle.lastIndex = 0;
            while ((m = rxTitle.exec(line))) add(m[1], m[2], line);
        }

        // Ticket numbers printed in their own column (AirSial): hand them out in order.
        const missing = list.filter(p => !p.ticket_no);
        if (missing.length) {
            const used = new Set(list.map(p => p.ticket_no).filter(Boolean));
            const loose = [];
            for (const line of lines) {
                const r = /\b(\d{3})[\s\-]?(\d{10})\b/g;
                let m;
                while ((m = r.exec(line))) { const t = m[1] + m[2]; if (!used.has(t) && !loose.includes(t)) loose.push(t); }
            }
            if (loose.length === missing.length) missing.forEach((p, i) => { p.ticket_no = loose[i]; });
        }
        return list;
    }

    function referenceYear(text) {
        const m = text.match(/\b(20[2-4]\d)\b/);
        return m ? +m[1] : new Date().getFullYear();
    }

    // ------------------------------------------------------------------
    // 12-hour clocks printed without AM/PM next to the time (Travelport Viewtrip puts them a row above/below).
    // ------------------------------------------------------------------
    function meridiemsNear(lines, i, want) {
        const found = [];
        for (const off of [0, -1, 1, -2, 2]) {
            const l = lines[i + off];
            if (l === undefined) continue;
            const m = l.match(/(AM|PM)(?![A-Za-z])/g) || [];
            found.push(...m);
            if (found.length >= want) break;
        }
        return found.slice(0, want);
    }

    // ------------------------------------------------------------------
    // Generic reader: walk lines, build segments from tokens.
    // ------------------------------------------------------------------
    function genericSegments(lines, refYear) {
        const segs = [];
        const usedFlights = new Set();
        const pending = [];
        let cur = null;
        const twelveHour = (lines.join('\n').match(/(?<![A-Za-z])(AM|PM)(?![A-Za-z])/g) || []).length >= 2;

        const newSeg = () => {
            cur = { flight: '', from: '', to: '', dep_date: '', arr_date: '', dep_time: '', arr_time: '', baggage: '', duration: '', _years: {} };
            if (pending.length) cur.flight = pending.shift();
            segs.push(cur);
            return cur;
        };
        const ensure = () => cur || newSeg();

        lines.forEach((line, i) => {
            const meta = RX_META.test(line);
            const prose = isProse(line);
            const layover = RX_LAYOVER.test(line);
            const address = RX_ADDRESS.test(line);
            const tokens = [];

            findFlights(line).forEach(t => tokens.push(t));
            if (!prose && !address && !layover) {
                const ap = findAirports(line);
                if (ap.length === 2 && ap[0].value !== ap[1].value) tokens.push({ type: 'pair', idx: ap[0].idx, value: [ap[0].value, ap[1].value] });
                else ap.forEach(t => tokens.push(t));
            }
            if (!meta && !prose) {
                findDates(line, refYear).forEach(t => tokens.push(t));
                if (!layover) {
                    const times = findTimes(line);
                    if (twelveHour) {
                        const bare = times.filter(t => !t.hasMeridiem && t.rawHour >= 1 && t.rawHour <= 12);
                        if (bare.length) {
                            const mer = meridiemsNear(lines, i, bare.length);
                            if (mer.length === bare.length) bare.forEach((t, k) => {
                                let h = t.rawHour;
                                if (mer[k] === 'PM' && h < 12) h += 12;
                                if (mer[k] === 'AM' && h === 12) h = 0;
                                t.value = pad(h) + t.value.slice(2);
                            });
                        }
                    }
                    times.forEach(t => tokens.push(t));
                }
            }
            if (!prose) findBaggage(line).forEach(t => tokens.push(t));
            tokens.sort((a, b) => a.idx - b.idx);

            let flightsInLine = 0;
            for (const t of tokens) {
                if (t.type === 'flight') {
                    if (usedFlights.has(t.value)) continue;
                    usedFlights.add(t.value);
                    if (flightsInLine++ > 0) { pending.push(t.value); continue; }
                    if (cur && !cur.flight) cur.flight = t.value;
                    else newSeg().flight = t.value;
                } else if (t.type === 'pair') {
                    const [a, b] = t.value;
                    ensure();
                    if (cur.from === a && cur.to === b) continue;
                    if (!cur.from && !cur.to) { cur.from = a; cur.to = b; }
                    else if (cur.from === a && !cur.to) cur.to = b;
                    else { newSeg(); cur.from = a; cur.to = b; }
                } else if (t.type === 'airport') {
                    ensure();
                    if (t.value === cur.from || t.value === cur.to) continue;
                    if (!cur.from) cur.from = t.value;
                    else if (!cur.to) cur.to = t.value;
                    else { newSeg(); cur.from = t.value; }
                } else if (t.type === 'date') {
                    ensure();
                    if (!cur.dep_date) { cur.dep_date = t.value; cur._years.dep = t.explicitYear; }
                    else if (t.value === cur.dep_date || t.value === cur.arr_date) continue;
                    else if (!cur.arr_date && cur.dep_time && daysBetween(cur.dep_date, t.value) >= 1 && daysBetween(cur.dep_date, t.value) <= 2) cur.arr_date = t.value;
                    else { newSeg(); cur.dep_date = t.value; cur._years.dep = t.explicitYear; }
                } else if (t.type === 'time') {
                    ensure();
                    if (!cur.dep_time) cur.dep_time = t.value;
                    else if (!cur.arr_time) cur.arr_time = t.value;
                    else { newSeg(); cur.dep_time = t.value; }
                } else if (t.type === 'baggage') {
                    if (cur && !cur.baggage) cur.baggage = t.value;
                }
            }
            if (cur && !cur.duration && !layover) {
                const d = line.match(RX_DURATION);
                if (d) cur.duration = d[1] ? `${+d[1]}h ${d[2]}m` : `${+d[3]}h ${pad(+d[4])}m`;
            }
        });
        return segs;
    }

    // ------------------------------------------------------------------
    // AirSial e-ticket / booking (two sectors side by side in one table).
    // ------------------------------------------------------------------
    function airsialSegments(lines, refYear) {
        const text = lines.join('\n');
        const sectors = [];
        const rxSector = new RegExp('\\b(' + Object.keys(CITY_CODES).join('|') + ')\\s*-\\s*(' + Object.keys(CITY_CODES).join('|') + ')\\b', 'gi');
        let m;
        while ((m = rxSector.exec(text))) {
            const pair = [CITY_CODES[m[1].toLowerCase()], CITY_CODES[m[2].toLowerCase()]];
            if (!sectors.some(s => s[0] === pair[0] && s[1] === pair[1])) sectors.push(pair);
        }
        const flights = [];
        const rxFd = /(\d{1,2})-([A-Za-z]{3})-(\d{2,4})\s+(PF)\s?(\d{3,4})/g;
        while ((m = rxFd.exec(text))) flights.push({ date: isoDate(fullYear(m[3]), monthNo(m[2]), +m[1]), flight: 'PF' + m[5] });
        if (!flights.length) return null;
        flights.sort((a, b) => a.date.localeCompare(b.date));

        const times = [];
        lines.forEach(line => { if (!RX_META.test(line)) findTimes(line).filter(t => t.hasMeridiem).forEach(t => times.push(t.value)); });

        const baggage = {};
        lines.forEach(line => {
            const b = line.match(new RegExp('(' + Object.keys(CITY_CODES).join('|') + ')\\s*-\\s*(' + Object.keys(CITY_CODES).join('|') + ')\\s*:\\s*(\\d)\\s*Piece\\(?s?\\)?.*?(\\d{1,2})\\s*KG', 'i'));
            if (b) baggage[CITY_CODES[b[1].toLowerCase()] + CITY_CODES[b[2].toLowerCase()]] = `${b[3]} PC (${b[4]} KG)`;
        });

        return flights.map((f, i) => {
            const sec = sectors[i] || [];
            return {
                flight: f.flight, from: sec[0] || '', to: sec[1] || '', dep_date: f.date, arr_date: '',
                dep_time: times[i * 2] || '', arr_time: times[i * 2 + 1] || '', baggage: baggage[(sec[0] || '') + (sec[1] || '')] || '',
                duration: '', _years: { dep: true }
            };
        });
    }

    // ------------------------------------------------------------------
    // Clean-up shared by every reader
    // ------------------------------------------------------------------
    function finishSegments(segs) {
        let out = segs.filter(s => s.flight || (s.from && s.to && s.dep_date));
        out.forEach((s, i) => {
            if (!s.from && i > 0) s.from = out[i - 1].to;
            // Connection printed under the previous flight's date block (flydubai via DXB).
            if (!s.dep_date && i > 0) { s.dep_date = out[i - 1].arr_date || out[i - 1].dep_date; s._years.dep = true; }
            if (s.from && s.to && s.from === s.to) s.to = '';
        });
        // Dates printed without a year: roll into next year when they fall before the previous flight (Dec => Jan).
        for (let i = 1; i < out.length; i++) {
            const s = out[i], p = out[i - 1];
            if (s.dep_date && p.dep_date && !s._years.dep && s.dep_date < p.dep_date && daysBetween(s.dep_date, p.dep_date) > 60) {
                s.dep_date = (+s.dep_date.slice(0, 4) + 1) + s.dep_date.slice(4);
            }
        }
        out.forEach(s => {
            if (s.dep_date && !s.arr_date) {
                s.arr_date = (s.dep_time && s.arr_time && s.arr_time < s.dep_time) ? addDays(s.dep_date, 1) : s.dep_date;
            }
            delete s._years;
        });
        if (out.every(s => s.dep_date)) {
            out = out.map((s, i) => [s, i]).sort((a, b) => (a[0].dep_date + (a[0].dep_time || '')).localeCompare(b[0].dep_date + (b[0].dep_time || '')) || a[1] - b[1]).map(x => x[0]);
        }
        return out;
    }

    function airlineFrom(segs, text) {
        const counts = {};
        segs.forEach(s => { const c = (s.flight.match(/^([A-Z0-9]{2})/) || [])[1]; if (c) counts[c] = (counts[c] || 0) + 1; });
        let code = Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0] || '';
        if (!code) {
            for (const [rx, c] of AIRLINE_NAMES) if (rx.test(text)) { code = c; break; }
        }
        return { code, name: AIRLINES[code] || '' };
    }

    function parse(input) {
        const lines = (Array.isArray(input) ? input : String(input).split(/\r?\n/)).map(l => String(l).replace(/ /g, ' ')).filter(l => l.trim() !== '');
        const text = lines.join('\n');
        const refYear = referenceYear(text);

        let segs = null, format = 'generic';
        if (/air\s?sial/i.test(text) || /\bPF\s?\d{3}\b/.test(text) && /travel\s*information/i.test(text)) {
            segs = airsialSegments(lines, refYear);
            if (segs) format = 'airsial';
        }
        if (!segs) segs = genericSegments(lines, refYear);
        segs = finishSegments(segs);

        const airline = airlineFrom(segs, text);
        const passengers = readPassengers(lines);
        const cabin = readCabin(text);
        const checked = segs.map(s => s.baggage).find(Boolean) || '';

        return {
            format,
            pnr: readPnr(text),
            airline_code: airline.code,
            airline_name: airline.name,
            status: readStatus(text),
            cabin,
            passengers,
            segments: segs.map(s => ({ ...s, baggage: s.baggage || checked })),
        };
    }

    // ------------------------------------------------------------------
    // Browser: read a File (PDF or image) => { method, lines, result }
    // ------------------------------------------------------------------
    const PDFJS_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
    const PDFJS_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    const TESSERACT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${src}"]`)) { resolve(); return; }
            const s = document.createElement('script');
            s.src = src; s.onload = resolve; s.onerror = () => reject(new Error('Could not load ' + src));
            document.head.appendChild(s);
        });
    }

    // OCR reads small screenshots far better when they are enlarged and turned greyscale first.
    function ocrCanvas(drawable, width, height) {
        const scale = Math.max(1, Math.min(3, 2600 / width));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(width * scale); canvas.height = Math.round(height * scale);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(drawable, 0, 0, canvas.width, canvas.height);
        const img = ctx.getImageData(0, 0, canvas.width, canvas.height), d = img.data;
        for (let i = 0; i < d.length; i += 4) { const g = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]; d[i] = d[i + 1] = d[i + 2] = g; }
        ctx.putImageData(img, 0, 0);
        return canvas;
    }

    function loadImage(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file), img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('This image could not be opened.')); };
            img.src = url;
        });
    }

    async function ocrSource(source, onProgress, label) {
        await loadScript(TESSERACT_URL);
        const { data } = await root.Tesseract.recognize(source, 'eng', {
            logger: m => { if (m.status === 'recognizing text' && onProgress) onProgress(`${label} ${Math.round(m.progress * 100)}%`); }
        });
        return String(data.text || '').split(/\r?\n/);
    }

    async function readFile(file, onProgress) {
        const progress = msg => onProgress && onProgress(msg);
        const isPdf = /pdf$/i.test(file.type) || /\.pdf$/i.test(file.name);
        let lines = [], method = 'text';

        if (isPdf) {
            progress('Opening PDF…');
            await loadScript(PDFJS_URL);
            root.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER;
            const pdf = await root.pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
            for (let p = 1; p <= pdf.numPages; p++) {
                const page = await pdf.getPage(p);
                lines.push(...linesFromTextItems((await page.getTextContent()).items));
            }
            if (lines.join('').replace(/[^A-Za-z0-9]/g, '').length < 40) {
                // Scanned / image-only PDF — render each page and OCR it.
                method = 'ocr';
                lines = [];
                for (let p = 1; p <= Math.min(pdf.numPages, 4); p++) {
                    progress(`Scanning page ${p} of ${pdf.numPages}…`);
                    const page = await pdf.getPage(p);
                    const base = page.getViewport({ scale: 1 });
                    const viewport = page.getViewport({ scale: Math.max(2, Math.min(4, 2600 / base.width)) });
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width; canvas.height = viewport.height;
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                    lines.push(...await ocrSource(ocrCanvas(canvas, canvas.width, canvas.height), progress, `Reading scanned page ${p} —`));
                }
            }
        } else {
            method = 'ocr';
            const img = await loadImage(file);
            lines = await ocrSource(ocrCanvas(img, img.naturalWidth, img.naturalHeight), progress, 'Reading image —');
        }
        progress('Understanding ticket…');
        return { method, lines, result: parse(lines) };
    }

    const api = { parse, linesFromTextItems, readFile, AIRLINES, AIRPORTS };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.TicketReader = api;
})(typeof window !== 'undefined' ? window : globalThis);
