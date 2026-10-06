/*
 * Aeroheights brand theme for the Tailwind CDN (load right after cdn.tailwindcss.com).
 *
 * Brand:      Royal Navy #26206F · Midnight Navy #161A35 · Ocean Blue #285A9B · Sky Blue #2183DF
 *             Teal #4C9AAF · Aqua #37D4D9 · Golden #FEC624 · Cloud White #F6F8FC · Mist #DCE5ED · White
 * Supporting: Light Gold #FFE9A8 · Body Text #4A5170 · Muted Grey #8A90A8 · Error Red #E0475B
 *             WhatsApp #25D366 -> #1DA851 (WhatsApp buttons only)
 * Gradients:  Navy 125deg #161A35 -> #26206F -> #285A9B · Gold button #FEC624 -> #FFD95E
 *             Gold text #FEC624 -> #FFE9A8 -> #FEC624
 * Font:       Jost everywhere.
 *
 * Every Tailwind colour family the views use is mapped onto this palette, so no screen can show an
 * off-brand colour: neutrals -> Mist/Cloud/Body/Muted/Midnight, blue/sky -> Sky Blue, indigo/violet/purple
 * -> Royal Navy, cyan/fuchsia/pink -> Ocean Blue, green/emerald/teal/lime -> Teal & Aqua, amber/orange/yellow -> Golden,
 * red/rose -> Error Red.
 */
(function () {
    const neutral = { 50: '#F6F8FC', 100: '#EEF2F8', 200: '#DCE5ED', 300: '#C5CFDE', 400: '#8A90A8', 500: '#6B7290', 600: '#4A5170', 700: '#363C5E', 800: '#252A4C', 900: '#161A35', 950: '#0E1124' };
    const navy    = { 50: '#EEEDF7', 100: '#DDDBF0', 200: '#BAB6E0', 300: '#8F89CA', 400: '#615AAE', 500: '#3B348A', 600: '#26206F', 700: '#1F1A5C', 800: '#1A1649', 900: '#161A35', 950: '#0E1124' };
    const sky     = { 50: '#EEF6FD', 100: '#D9EAFB', 200: '#B5D6F6', 300: '#84BBEF', 400: '#4F9DE7', 500: '#2183DF', 600: '#2183DF', 700: '#1A6BB8', 800: '#285A9B', 900: '#1E4577', 950: '#142E50' };
    const ocean   = { 50: '#EEF3FA', 100: '#D9E4F2', 200: '#B3C8E4', 300: '#84A5D1', 400: '#5582BD', 500: '#3A6DAD', 600: '#285A9B', 700: '#214B82', 800: '#1C3D69', 900: '#182F51', 950: '#0F1E35' };
    const teal    = { 50: '#EDF8FA', 100: '#D3EFF3', 200: '#A9E3EA', 300: '#6FD8DE', 400: '#37D4D9', 500: '#4C9AAF', 600: '#3B8296', 700: '#2F6A7B', 800: '#275563', 900: '#1F4450', 950: '#132B33' };
    const gold    = { 50: '#FFFAEB', 100: '#FFF3CC', 200: '#FFE9A8', 300: '#FFD95E', 400: '#FEC624', 500: '#FEC624', 600: '#D9A300', 700: '#A67C00', 800: '#7D5D00', 900: '#5C4500', 950: '#3A2B00' };
    const red     = { 50: '#FDF0F2', 100: '#FBDDE1', 200: '#F6BAC2', 300: '#EF8D9A', 400: '#E86576', 500: '#E0475B', 600: '#E0475B', 700: '#B8364A', 800: '#922B3B', 900: '#6E2230', 950: '#45141D' };

    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    brand: navy, navy, ocean, aqua: teal, gold, midnight: '#161A35', cloud: '#F6F8FC', mist: '#DCE5ED',
                    slate: neutral, gray: neutral, zinc: neutral, neutral: neutral, stone: neutral,
                    blue: sky, sky: sky,
                    indigo: navy, violet: navy, purple: navy,
                    cyan: ocean,
                    teal: teal, emerald: teal, green: teal, lime: teal,
                    amber: gold, yellow: gold, orange: gold,
                    red: red, rose: red,
                    // fuchsia / pink are decorative accents (mostly gradients): Ocean Blue gives the navy -> ocean signature blend.
                    pink: ocean, fuchsia: ocean,
                },
                fontFamily: {
                    sans: ['Jost', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    // Numbers stay in Jost too; tabular figures keep amounts lined up in tables.
                    mono: ['Jost', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    // Jost has no Urdu glyphs, so Urdu text keeps its own script font.
                    urdu: ['Amiri', 'serif'],
                }
            }
        }
    };

    // Role overrides that a colour scale alone can't express (bg-blue-600 is a button, text-blue-600 a link).
    // ":root" raises specificity above Tailwind's generated single-class rules.
    const css = `
        html, body { font-family: 'Jost', ui-sans-serif, system-ui, sans-serif; }
        body { color: #4A5170; background-color: #F6F8FC; }
        :root .font-mono { font-family: 'Jost', ui-sans-serif, system-ui, sans-serif !important; font-variant-numeric: tabular-nums; letter-spacing: 0; }
        h1, h2, h3, h4, h5, h6 { color: inherit; }

        /* Primary buttons / active tabs / selected chips: Royal Navy (links & icons keep Sky Blue). */
        :root .bg-blue-600, :root .bg-blue-500, :root .bg-sky-600, :root .bg-sky-500, :root .bg-indigo-600, :root .bg-violet-600 { background-color: #26206F; }
        :root .bg-blue-700, :root .bg-sky-700, :root .bg-indigo-700, :root .bg-violet-700,
        :root .hover\\:bg-blue-700:hover, :root .hover\\:bg-blue-600:hover, :root .hover\\:bg-sky-700:hover, :root .hover\\:bg-indigo-700:hover, :root .hover\\:bg-violet-700:hover { background-color: #285A9B; }

        /* Main call-to-action: Gold gradient with Midnight text. */
        :root .bg-amber-500, :root .bg-amber-600, :root .bg-orange-500, :root .bg-orange-600, :root .bg-yellow-500, :root .btn-gold {
            background-image: linear-gradient(90deg, #FEC624 0%, #FFD95E 100%); background-color: #FEC624; color: #161A35 !important;
        }
        :root .hover\\:bg-amber-700:hover, :root .hover\\:bg-orange-600:hover, :root .btn-gold:hover { background-image: linear-gradient(90deg, #FFD95E 0%, #FEC624 100%); background-color: #FFD95E; color: #161A35 !important; }

        /* Signature navy gradient for hero / dark sections. */
        .bg-navy-gradient, .brand-header { background-image: linear-gradient(125deg, #161A35 0%, #26206F 55%, #285A9B 100%); }
        :root .bg-gradient-to-br.from-slate-900, :root .bg-gradient-to-r.from-slate-900 { background-image: linear-gradient(125deg, #161A35 0%, #26206F 55%, #285A9B 100%); }
        .text-gold-gradient { background: linear-gradient(90deg, #FEC624 0%, #FFE9A8 50%, #FEC624 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .text-eyebrow { color: #2183DF; letter-spacing: .14em; text-transform: uppercase; font-weight: 700; }

        /* Inputs on Cloud White with Mist borders; Sky Blue focus. */
        input:not([type=checkbox]):not([type=radio]):not([type=file]), select, textarea { background-color: #F6F8FC; border-color: #DCE5ED; color: #161A35; }
        input:not([type=checkbox]):not([type=radio]):focus, select:focus, textarea:focus { background-color: #FFFFFF; border-color: #2183DF; outline: none; box-shadow: 0 0 0 3px rgba(33, 131, 223, .18); }
        ::placeholder { color: #8A90A8; }
        input[type=checkbox], input[type=radio] { accent-color: #26206F; }
        a { text-underline-offset: 2px; }

        /* WhatsApp green is reserved for WhatsApp buttons. */
        .btn-whatsapp { background-image: linear-gradient(90deg, #25D366 0%, #1DA851 100%); color: #fff !important; }

        ::-webkit-scrollbar-track { background: #F6F8FC; }
        ::-webkit-scrollbar-thumb { background: #DCE5ED; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #8A90A8; }
    `;
    const style = document.createElement('style');
    style.id = 'aeroheights-brand';
    style.textContent = css;
    (document.head || document.documentElement).appendChild(style);
})();
