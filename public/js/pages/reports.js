App.ready.then(() => {
    const { $, esc, peso, num, labels } = App;
    const iso = (d) => d.toLocaleDateString('en-CA');
    let report = null;

    function preset(p) {
        const t = new Date();
        const ranges = {
            today: [t, t],
            '7d': [new Date(t.getFullYear(), t.getMonth(), t.getDate() - 6), t],
            month: [new Date(t.getFullYear(), t.getMonth(), 1), t],
            last: [new Date(t.getFullYear(), t.getMonth() - 1, 1), new Date(t.getFullYear(), t.getMonth(), 0)],
        };
        $('#from').value = iso(ranges[p][0]);
        $('#to').value = iso(ranges[p][1]);
        [...$('#presets').children].forEach((b) => b.classList.toggle('on', b.dataset.p === p));
        load();
    }

    async function load() {
        const res = await App.api('/reports', { query: { from: $('#from').value, to: $('#to').value } });
        if (!res.ok) return App.fail(null, res);
        report = res.data;
        const s = report.summary;
        $('#range-label').textContent = `${App.date(report.range.from)} – ${App.date(report.range.to)}`;

        const margin = s.net ? Math.round((s.profit / s.net) * 100) : 0;
        const cards = [
            ['Net sales', peso(s.net), `Gross ${peso(s.gross)}`],
            ['Transactions', num(s.transactions), `${num(s.items_sold)} items sold`],
            ['Average sale', peso(s.average), 'per transaction'],
            ['Gross profit', peso(s.profit), `${margin}% margin · COGS ${peso(s.cogs)}`],
            ['Senior / PWD discounts', peso(s.discounts), 'plus VAT exemptions'],
            ['VAT collected', peso(s.vat), 'output tax on regular sales'],
            ['Voided sales', num(s.voided_count), peso(s.voided_total) + ' reversed'],
            ['Items sold', num(s.items_sold), 'units across all products'],
        ];
        $('#summary').innerHTML = cards.map(([l, v, sub]) => `<div class="card mini"><small>${l}</small><b class="num">${v}</b><span>${sub}</span></div>`).join('');

        drawChart();

        const empty = (cols) => App.emptyRow(cols, 'No sales in this period.', 'chart');
        $('#products').innerHTML = report.products.length
            ? report.products.map((p, i) => `<tr><td><span class="rank ${i < 3 ? 'top' : ''}">${i + 1}</span></td><td>${esc(p.name)}</td><td class="r num">${num(p.units)}</td><td class="r num"><b>${peso(p.revenue)}</b></td></tr>`).join('')
            : empty(4);

        const catTotal = report.categories.reduce((a, c) => a + +c.revenue, 0);
        $('#categories').innerHTML = report.categories.length
            ? report.categories.map((c) => `<tr><td>${esc(c.name)}</td><td class="r num">${num(c.units)}</td><td class="r num"><b>${peso(c.revenue)}</b></td><td class="r num">${Math.round((c.revenue / catTotal) * 100)}%</td></tr>`).join('')
            : empty(4);

        $('#cashiers').innerHTML = report.cashiers.length
            ? report.cashiers.map((c) => `<tr><td><div class="cell"><span class="av t-brand">${App.initials(c.name)}</span><b>${esc(c.name)}</b></div></td><td class="r num">${num(c.transactions)}</td><td class="r num"><b>${peso(c.total)}</b></td></tr>`).join('')
            : empty(3);

        $('#payments').innerHTML = report.payments.length
            ? report.payments.map((p) => `<tr><td>${labels.payment[p.payment_method]}</td><td class="r num">${num(p.transactions)}</td><td class="r num"><b>${peso(p.total)}</b></td></tr>`).join('')
                + report.discounts.map((d) => `<tr><td>${labels.discount[d.discount_type]} discount</td><td class="r num">${num(d.transactions)}</td><td class="r num" style="color:var(--bad)">−${peso(d.discount)}</td></tr>`).join('')
            : empty(3);

        $('#daily').innerHTML = report.daily.length
            ? [...report.daily].reverse().map((d) => `<tr><td>${App.date(d.day)}</td><td class="r num">${num(d.transactions)}</td><td class="r num">${peso(d.discounts)}</td><td class="r num"><b>${peso(d.total)}</b></td></tr>`).join('')
            : empty(4);
    }

    function drawChart() {
        const box = $('#daily-chart');
        const W = box.clientWidth, H = box.clientHeight;
        if (!report || !W) return;
        const days = report.daily;
        if (!days.length) return (box.innerHTML = '<p class="muted" style="text-align:center;padding-top:100px">No sales in this period.</p>');

        const pad = { l: 52, r: 8, t: 10, b: 28 };
        const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
        const max = Math.max(1000, Math.ceil(Math.max(...days.map((d) => +d.total)) / 1000) * 1000);
        const slot = iw / days.length;
        const bw = Math.max(3, Math.min(34, slot * 0.62));
        const y = (v) => pad.t + ih - (v / max) * ih;
        const step = Math.ceil(days.length / 10);

        box.innerHTML = `<svg viewBox="0 0 ${W} ${H}">
            <defs><linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#60a5fa"/><stop offset="1" stop-color="#2563eb"/></linearGradient></defs>
            ${[0, 1, 2, 3, 4].map((t) => {
                const v = (max / 4) * t;
                return `<line x1="${pad.l}" x2="${W - pad.r}" y1="${y(v)}" y2="${y(v)}" stroke="var(--line)" ${t ? 'stroke-dasharray="3 5"' : ''}/>
                    <text x="${pad.l - 10}" y="${y(v) + 4}" text-anchor="end" font-size="11" fill="var(--muted)">${v >= 1000 ? +(v / 1000).toFixed(1) + 'k' : v}</text>`;
            }).join('')}
            ${days.map((d, i) => {
                const cx = pad.l + slot * i + slot / 2;
                const label = new Date(d.day + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
                return `<rect x="${cx - bw / 2}" y="${y(+d.total)}" width="${bw}" height="${Math.max(0, pad.t + ih - y(+d.total))}" rx="${Math.min(6, bw / 2)}" fill="url(#bg)"><title>${label}: ${peso(d.total)} · ${d.transactions} transactions</title></rect>
                    ${i % step === 0 ? `<text x="${cx}" y="${H - 6}" text-anchor="middle" font-size="11" fill="var(--muted)">${label}</text>` : ''}`;
            }).join('')}
        </svg>`;
    }

    function exportCsv() {
        if (!report) return;
        const s = report.summary;
        const q = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
        const lines = [
            ['PharmaPOS sales report', `${report.range.from} to ${report.range.to}`],
            [],
            ['Summary'],
            ['Net sales', s.net], ['Gross sales', s.gross], ['Transactions', s.transactions], ['Average sale', s.average],
            ['Items sold', s.items_sold], ['Cost of goods', s.cogs], ['Gross profit', s.profit], ['Discounts', s.discounts],
            ['VAT', s.vat], ['Voided sales', s.voided_count], ['Voided amount', s.voided_total],
            [],
            ['Daily sales'], ['Date', 'Transactions', 'Discounts', 'Net sales'],
            ...report.daily.map((d) => [d.day, d.transactions, d.discounts, d.total]),
            [],
            ['Top products'], ['Product', 'Units', 'Revenue'],
            ...report.products.map((p) => [p.name, p.units, p.revenue]),
            [],
            ['By category'], ['Category', 'Units', 'Revenue'],
            ...report.categories.map((c) => [c.name, c.units, c.revenue]),
            [],
            ['By cashier'], ['Staff', 'Transactions', 'Total'],
            ...report.cashiers.map((c) => [c.name, c.transactions, c.total]),
        ];
        const csv = '﻿' + lines.map((l) => l.map(q).join(',')).join('\r\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
        a.download = `sales-report_${report.range.from}_${report.range.to}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
    }

    $('#presets').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (b) preset(b.dataset.p);
    });
    ['#from', '#to'].forEach((s) => $(s).addEventListener('change', () => {
        [...$('#presets').children].forEach((b) => b.classList.remove('on'));
        load();
    }));
    $('#export').addEventListener('click', exportCsv);
    new ResizeObserver(drawChart).observe($('#daily-chart'));

    preset('month');
});
