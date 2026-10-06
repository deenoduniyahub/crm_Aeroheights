<?php
/**
 * Aeroheights voucher design (shared by views/vouchers/print.php and views/hotel_bookings/print_voucher.php).
 * Navy gradient header band, summary tiles, stay cards with a check-in -> check-out timeline,
 * boarding-pass flight cards and a navy footer strip. Palette + Jost only.
 */
?>
<style>
    :root {
        --royal: #26206F; --midnight: #161A35; --ocean: #285A9B; --sky: #2183DF; --teal: #4C9AAF; --aqua: #37D4D9;
        --gold: #FEC624; --gold-2: #FFD95E; --gold-light: #FFE9A8; --cloud: #F6F8FC; --mist: #DCE5ED;
        --body: #4A5170; --muted: #8A90A8; --navy-grad: linear-gradient(125deg, #161A35 0%, #26206F 55%, #285A9B 100%);
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; }
    body { font-family: 'Jost', Arial, sans-serif; background: var(--mist); color: var(--midnight); padding: 24px 12px; font-size: 11.5px; line-height: 1.4;
           -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .num { font-variant-numeric: tabular-nums; }

    .doc { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 18px 40px rgba(22, 26, 53, .14); }

    /* ---------- Header band ---------- */
    .band { position: relative; background: var(--navy-grad); color: #fff; padding: 22px 26px 26px; overflow: hidden; }
    .band::before { content: ''; position: absolute; right: -60px; top: -80px; width: 260px; height: 260px; border-radius: 50%;
                    background: radial-gradient(circle, rgba(55, 212, 217, .28) 0%, rgba(55, 212, 217, 0) 70%); }
    .band::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 5px; background: linear-gradient(90deg, var(--gold) 0%, var(--gold-2) 50%, var(--gold) 100%); }
    .band-row { position: relative; display: flex; justify-content: space-between; align-items: center; gap: 18px; }
    .band-brand img { height: 54px; width: auto; display: block; }
    .band-brand .agency { font-size: 24px; font-weight: 800; letter-spacing: .02em; line-height: 1.1; }
    .band-brand .tagline { margin-top: 4px; font-size: 10px; letter-spacing: .22em; text-transform: uppercase; color: var(--aqua); font-weight: 600; }
    .band-side { display: flex; align-items: center; gap: 14px; }
    .partner { background: #fff; border-radius: 10px; padding: 5px 8px; line-height: 0; }
    .partner img { height: 30px; width: auto; }
    .qr-tile { background: #fff; border-radius: 12px; padding: 6px 6px 4px; text-align: center; box-shadow: 0 6px 16px rgba(0, 0, 0, .18); }
    .qr-tile .qr { width: 64px; height: 64px; line-height: 0; }
    .qr-tile .qr svg { width: 100%; height: 100%; display: block; }
    .qr-tile span { display: block; margin-top: 3px; font-size: 7.5px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--royal); }

    .band-title { position: relative; margin-top: 18px; display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
    .eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .24em; text-transform: uppercase;
               background: linear-gradient(90deg, var(--gold) 0%, var(--gold-light) 50%, var(--gold) 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .band-title h1 { margin: 2px 0 0; font-size: 30px; font-weight: 800; letter-spacing: .01em; line-height: 1; }
    .chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .chip { background: rgba(255, 255, 255, .1); border: 1px solid rgba(255, 255, 255, .22); border-radius: 999px; padding: 5px 12px; font-size: 11px; white-space: nowrap; }
    .chip b { font-weight: 700; }
    .chip small { color: rgba(255, 255, 255, .7); font-size: 9px; letter-spacing: .12em; text-transform: uppercase; margin-right: 5px; }

    .content { padding: 20px 26px 8px; }

    /* ---------- Summary tiles ---------- */
    .tiles { display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 18px; }
    .tile { border: 1px solid var(--mist); border-radius: 12px; padding: 9px 12px; background: #fff; }
    .tile .k { font-size: 8.5px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--sky); }
    .tile .v { margin-top: 2px; font-size: 14px; font-weight: 700; color: var(--midnight); line-height: 1.2; }
    .tile .s { font-size: 10px; color: var(--muted); }
    .tile.lead { background: var(--cloud); border-color: var(--cloud); border-left: 4px solid var(--royal); }

    /* ---------- Section heading ---------- */
    .sec { display: flex; align-items: center; gap: 10px; margin: 16px 0 9px; }
    .sec .dot { width: 22px; height: 22px; border-radius: 7px; background: var(--royal); color: var(--gold); display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; }
    .sec h2 { margin: 0; font-size: 12.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--royal); }
    .sec .line { flex: 1; height: 1px; background: var(--mist); }
    .sec .aside { font-size: 10.5px; color: var(--muted); }

    /* ---------- Stay cards ---------- */
    .stay { display: grid; grid-template-columns: 6px 1fr; border: 1px solid var(--mist); border-radius: 14px; overflow: hidden; margin-bottom: 9px; page-break-inside: avoid; }
    .stay .bar { background: var(--sky); }
    .stay.makkah .bar { background: var(--gold); }
    .stay.madinah .bar { background: var(--aqua); }
    .stay-body { padding: 10px 14px; display: grid; grid-template-columns: 1.25fr 1.5fr; gap: 14px; align-items: center; }
    .city-tag { display: inline-block; font-size: 8.5px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: var(--royal); background: var(--cloud); border-radius: 999px; padding: 2px 9px; }
    .hotel { margin-top: 4px; font-size: 15px; font-weight: 700; color: var(--midnight); line-height: 1.2; }
    .facts { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 5px; }
    .fact { font-size: 10px; color: var(--body); border: 1px solid var(--mist); border-radius: 6px; padding: 1px 7px; }
    .fact b { color: var(--midnight); font-weight: 600; }
    .timeline { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 8px; }
    .tl-end .k { font-size: 8.5px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
    .tl-end .d { font-size: 13px; font-weight: 700; color: var(--midnight); }
    .tl-end .w { font-size: 10px; color: var(--muted); }
    .tl-end.out { text-align: right; }
    .tl-mid { position: relative; text-align: center; min-width: 84px; }
    .tl-mid::before { content: ''; position: absolute; left: -4px; right: -4px; top: 50%; border-top: 2px dotted var(--mist); }
    .nights { position: relative; display: inline-block; background: var(--royal); color: #fff; border-radius: 999px; padding: 3px 11px; font-size: 10.5px; font-weight: 700; }
    .total-row { display: flex; justify-content: flex-end; margin: -2px 0 4px; }
    .total-row span { font-size: 11px; color: var(--body); background: var(--cloud); border-radius: 999px; padding: 4px 12px; }
    .total-row b { color: var(--royal); }

    /* ---------- Travellers ---------- */
    .list { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--mist); border-radius: 12px; overflow: hidden; }
    .list th { background: var(--cloud); color: var(--muted); font-size: 8.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; text-align: left; padding: 7px 10px; border-bottom: 1px solid var(--mist); }
    .list td { padding: 6px 10px; font-size: 11px; color: var(--midnight); border-bottom: 1px solid var(--mist); }
    .list tr:last-child td { border-bottom: none; }
    .list tbody tr:nth-child(even) td { background: #FBFCFE; }
    .list .idx { width: 30px; color: var(--sky); font-weight: 700; }
    .list .name { font-weight: 600; }
    .list .c { text-align: center; }
    .pill { display: inline-block; font-size: 9.5px; padding: 1px 8px; border-radius: 999px; background: var(--cloud); color: var(--body); }

    /* ---------- Boarding-pass flights ---------- */
    .passes { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .pass { position: relative; border: 1px solid var(--mist); border-radius: 14px; padding: 11px 14px 10px; background: #fff; page-break-inside: avoid; }
    .pass::before, .pass::after { content: ''; position: absolute; top: 50%; width: 14px; height: 14px; border-radius: 50%; background: var(--mist); transform: translateY(-50%); }
    .pass::before { left: -8px; } .pass::after { right: -8px; }
    .pass-head { display: flex; justify-content: space-between; align-items: center; font-size: 8.5px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--sky); }
    .pass-head .fno { color: var(--royal); font-size: 11px; letter-spacing: .06em; background: var(--cloud); border-radius: 6px; padding: 1px 8px; }
    .route { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; margin: 8px 0 7px; }
    .route .code { font-size: 22px; font-weight: 800; color: var(--midnight); letter-spacing: .04em; line-height: 1; }
    .route .code.to { text-align: right; }
    .route .plane { color: var(--sky); font-size: 15px; padding: 0 8px; }
    .pass-foot { display: flex; justify-content: space-between; gap: 8px; border-top: 1px dashed var(--mist); padding-top: 6px; font-size: 10.5px; color: var(--body); }
    .pass-foot b { color: var(--midnight); font-weight: 600; }
    .pass.empty .route .code { color: var(--mist); }

    /* ---------- Transport + notes ---------- */
    .ride { display: grid; grid-template-columns: auto 1fr auto; gap: 12px; align-items: center; border: 1px solid var(--mist); border-radius: 14px; padding: 9px 14px; }
    .ride .ico { width: 30px; height: 30px; border-radius: 9px; background: var(--cloud); color: var(--royal); display: inline-flex; align-items: center; justify-content: center; font-size: 15px; }
    .ride .route-chain { font-size: 13px; font-weight: 700; color: var(--midnight); letter-spacing: .03em; }
    .ride .sub { font-size: 10px; color: var(--muted); }
    .ride .who { text-align: right; font-size: 10.5px; color: var(--body); }
    .ride .who b { display: block; font-size: 12px; color: var(--royal); }

    .note { margin-top: 12px; border-radius: 12px; background: var(--cloud); padding: 9px 14px; font-size: 11px; color: var(--body); border-left: 4px solid var(--gold); }
    .note b { color: var(--midnight); }
    .note ul { margin: 4px 0 0; padding-left: 16px; }
    .note li { margin: 2px 0; }

    .urdu { margin-top: 14px; border: 1px solid var(--mist); border-radius: 14px; padding: 10px 14px 6px; direction: rtl; }
    .urdu h3 { margin: 0 0 6px; line-height: 2.1; font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', Tahoma, sans-serif; font-size: 13px; color: var(--royal); font-weight: 700; }
    .urdu ol { margin: 0; padding: 0; list-style: none; columns: 2; column-gap: 22px; counter-reset: u; }
    .urdu li { break-inside: avoid; counter-increment: u; position: relative; padding-right: 22px; margin-bottom: 4px;
               font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', Tahoma, Arial, sans-serif; font-size: 10.5px; line-height: 2; color: var(--midnight); text-align: right; }
    .urdu li::before { content: counter(u); position: absolute; right: 0; top: 6px; width: 16px; height: 16px; border-radius: 50%; background: var(--royal); color: var(--gold);
                       font-family: 'Jost', Arial, sans-serif; font-size: 9px; font-weight: 700; line-height: 16px; text-align: center; }

    /* ---------- Footer strip ---------- */
    .foot { margin-top: 16px; background: var(--navy-grad); color: #fff; padding: 12px 26px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .foot .contacts { display: flex; gap: 16px; flex-wrap: wrap; font-size: 11px; }
    .foot .contacts span small { display: block; font-size: 8px; letter-spacing: .18em; text-transform: uppercase; color: var(--aqua); font-weight: 600; }
    .foot .contacts b { font-weight: 700; }
    .foot .motto { font-size: 10px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase;
                   background: linear-gradient(90deg, var(--gold) 0%, var(--gold-light) 50%, var(--gold) 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }

    /* ---------- Screen-only bars ---------- */
    .public-bar { max-width: 800px; margin: 0 auto 12px; display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 12px; font-weight: 600; }
    .public-bar a { color: var(--royal); text-decoration: none; background: #fff; border: 1px solid var(--mist); padding: 7px 14px; border-radius: 999px; }
    .public-bar .verified { color: #fff; background: var(--royal); padding: 7px 14px; border-radius: 999px; }
    .public-bar .verified b { color: var(--gold); }
    .actions { max-width: 800px; margin: 14px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
    .actions button { font: 600 12px 'Jost', Arial, sans-serif; padding: 9px 18px; border-radius: 10px; border: none; cursor: pointer; }
    .btn-close { background: #fff; color: var(--body); border: 1px solid var(--mist) !important; }
    .btn-print { background: linear-gradient(90deg, var(--gold) 0%, var(--gold-2) 100%); color: var(--midnight); }

    .urdu { page-break-inside: avoid; break-inside: avoid; }

    /* Print: compact so a normal voucher (2 hotels, a family, 2 flights) fits one A4 page. */
    @media print {
        @page { size: A4 portrait; margin: 6mm; }
        body { background: #fff; padding: 0; font-size: 10px; }
        .doc { max-width: none; border-radius: 0; box-shadow: none; }
        .no-print { display: none !important; }
        .band { padding: 12px 16px 15px; }
        .band-brand img { height: 42px; }
        .qr-tile .qr { width: 54px; height: 54px; }
        .band-title { margin-top: 8px; }
        .band-title h1 { font-size: 24px; }
        .content { padding: 10px 16px 2px; }
        .tiles { gap: 8px; margin-bottom: 8px; }
        .tile { padding: 6px 10px; border-radius: 10px; }
        .tile .v { font-size: 12.5px; }
        .sec { margin: 9px 0 5px; }
        .sec .dot { width: 18px; height: 18px; font-size: 10px; border-radius: 6px; }
        .sec h2 { font-size: 11px; }
        .stay { margin-bottom: 6px; border-radius: 11px; }
        .stay-body { padding: 6px 12px; }
        .hotel { font-size: 13px; margin-top: 2px; }
        .facts { margin-top: 3px; }
        .tl-end .d { font-size: 11.5px; }
        .total-row { margin: -1px 0 0; }
        .total-row span { padding: 2px 10px; font-size: 10px; }
        .list th { padding: 4px 9px; }
        .list td { padding: 3px 9px; font-size: 10.5px; }
        .pass { padding: 7px 12px 6px; border-radius: 11px; }
        .route { margin: 4px 0; }
        .route .code { font-size: 18px; }
        .pass-foot { padding-top: 4px; font-size: 10px; }
        .ride { padding: 6px 12px; border-radius: 11px; }
        .note { margin-top: 7px; padding: 6px 12px; }
        .urdu { margin-top: 8px; padding: 9px 12px 3px; }
        .urdu h3 { font-size: 11.5px; margin-bottom: 2px; line-height: 2; }
        .urdu li { font-size: 9.5px; line-height: 1.95; margin-bottom: 1px; }
        .urdu li::before { top: 3px; }
        .foot { margin-top: 8px; padding: 8px 16px; }
    }
    @media (max-width: 640px) {
        .tiles { grid-template-columns: 1fr 1fr; }
        .stay-body, .passes { grid-template-columns: 1fr; }
        .urdu ol { columns: 1; }
    }
</style>
