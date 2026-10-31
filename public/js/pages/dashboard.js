App.ready.then(async (user) => {
    const { $, esc, peso, num, icon, initials, daysUntil, labels } = App;

    const h = new Date().getHours();
    $('#greet').textContent = `${h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'}, ${user.name.split(' ')[0]}`;
    $('#today').textContent = new Date().toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

    const res = await App.api('/dashboard');
    if (!res.ok) return;
    const d = res.data;
    const isStaff = user.role !== 'cashier';

    /* ---------- KPI cards ---------- */
    const sparkPath = (pts, w = 84, hgt = 30) => {
        const min = Math.min(...pts), max = Math.max(...pts);
        return pts.map((v, i) => `${i ? 'L' : 'M'}${((i / (pts.length - 1)) * w).toFixed(1)} ${(hgt - 3 - ((v - min) / (max - min || 1)) * (hgt - 6)).toFixed(1)}`).join(' ');
    };
    const chip = (delta, invert = false) => {
        if (delta === 0) return '<span class="chip flat">Stable</span>';
        const good = invert ? delta < 0 : delta > 0;
        return `<span class="chip ${good ? 'up' : 'down'}">${icon(delta > 0 ? 'up' : 'down')}${Math.abs(delta)}%</span>`;
    };
    const spark = (pts, color) => `<svg class="spark" viewBox="0 0 84 30"><path d="${sparkPath(pts)}" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
    const k = d.kpis;
    const targetPct = d.target ? Math.min(100, Math.round((k.sales_today / d.target) * 100)) : 0;

    const cards = [
        { label: "Today's sales", value: peso(k.sales_today, 0), icon: 'peso', tone: 't-brand', foot: `${chip(k.sales_delta)} <small>vs yesterday</small>`, side: spark(k.sales_spark, '#2563eb'),
          extra: `<div class="bar-m" title="${targetPct}% of daily target"><i style="width:${targetPct}%;background:linear-gradient(90deg,#60a5fa,#2563eb)"></i></div><small class="muted" style="display:block;margin-top:6px;font-size:12px">${targetPct}% of ${peso(d.target, 0)} daily target</small>` },
        { label: 'Transactions', value: num(k.txns_today), icon: 'receipt', tone: 't-info', foot: `${chip(k.txns_delta)} <small>vs yesterday</small>`, side: spark(k.txns_spark, '#0891b2') },
        { label: 'Low stock items', value: num(k.low_stock), icon: 'alert', tone: 't-warn', foot: `<small>${k.out_of_stock} out of stock</small>`, href: '/medicines?stock=low' },
        { label: `Expiring in ${k.expiry_days} days`, value: num(k.expiring), icon: 'clock', tone: 't-bad', foot: k.expired ? `<span class="chip down">${k.expired} expired</span> <small>remove from shelf</small>` : '<small>No expired stock on hand</small>', href: '/stock?status=expiring' },
    ];

    $('#kpis').innerHTML = cards.map((c, n) => `<div class="card kpi in" style="animation-delay:${n * 0.05}s${c.href && isStaff ? ';cursor:pointer' : ''}" ${c.href && isStaff ? `onclick="location.href='${c.href}'"` : ''}>
        <div class="kpi-top">${c.label}<span class="kpi-ic ${c.tone}">${icon(c.icon)}</span></div>
        <div class="kpi-v num">${c.value}</div>
        <div class="kpi-f"><span>${c.foot}</span>${c.side ?? ''}</div>
        ${c.extra ?? ''}
    </div>`).join('');

    /* ---------- Revenue chart ---------- */
    let current = 'day';

    function drawChart() {
        const { labels: xs, values } = d.revenue[current];
        const box = $('#chart');
        const W = box.clientWidth, H = box.clientHeight;
        if (!W) return;
        const pad = { l: 52, r: 10, t: 12, b: 28 };
        const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
        const max = Math.max(1000, Math.ceil(Math.max(...values) / 1000) * 1000);
        const x = (i) => pad.l + (i / (values.length - 1)) * iw;
        const y = (v) => pad.t + ih - (v / max) * ih;

        let line = `M${x(0)} ${y(values[0])}`;
        for (let i = 1; i < values.length; i++) {
            const mx = (x(i - 1) + x(i)) / 2;
            line += ` C${mx} ${y(values[i - 1])} ${mx} ${y(values[i])} ${x(i)} ${y(values[i])}`;
        }
        const area = `${line} L${x(values.length - 1)} ${pad.t + ih} L${x(0)} ${pad.t + ih} Z`;
        const ticks = [0, 1, 2, 3, 4].map((t) => (max / 4) * t);
        const step = Math.ceil(xs.length / 8);

        box.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Revenue chart">
            <defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b82f6" stop-opacity=".35"/><stop offset="1" stop-color="#3b82f6" stop-opacity="0"/></linearGradient></defs>
            ${ticks.map((t) => `<line x1="${pad.l}" x2="${W - pad.r}" y1="${y(t)}" y2="${y(t)}" stroke="var(--line)" ${t ? 'stroke-dasharray="3 5"' : ''}/>
                <text x="${pad.l - 10}" y="${y(t) + 4}" text-anchor="end" font-size="11" fill="var(--muted)">${t >= 1000 ? +(t / 1000).toFixed(1) + 'k' : t}</text>`).join('')}
            ${xs.map((l, i) => (i % step === 0 ? `<text x="${x(i)}" y="${H - 6}" text-anchor="middle" font-size="11" fill="var(--muted)">${l}</text>` : '')).join('')}
            <path d="${area}" fill="url(#g)"/>
            <path d="${line}" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round"/>
            <line id="cl" y1="${pad.t}" y2="${pad.t + ih}" stroke="var(--brand)" stroke-dasharray="3 4" opacity="0"/>
            <circle id="cd" r="5" fill="var(--surface)" stroke="#2563eb" stroke-width="2.5" opacity="0"/>
            <rect x="${pad.l}" y="${pad.t}" width="${iw}" height="${ih}" fill="transparent" id="hit"/>
        </svg><div class="tip" id="tip"></div>`;

        const cl = $('#cl'), cd = $('#cd'), tip = $('#tip');
        $('#hit').addEventListener('mousemove', (e) => {
            const i = Math.max(0, Math.min(values.length - 1, Math.round(((e.clientX - box.getBoundingClientRect().left - pad.l) / iw) * (values.length - 1))));
            cl.setAttribute('x1', x(i)); cl.setAttribute('x2', x(i));
            cd.setAttribute('cx', x(i)); cd.setAttribute('cy', y(values[i]));
            cl.style.opacity = cd.style.opacity = tip.style.opacity = 1;
            tip.innerHTML = `<small>${xs[i]}</small><b class="num">${peso(values[i])}</b>`;
            tip.style.left = x(i) + 'px';
            tip.style.top = y(values[i]) - 14 + 'px';
        });
        $('#hit').addEventListener('mouseleave', () => (cl.style.opacity = cd.style.opacity = tip.style.opacity = 0));

        const sum = values.reduce((a, b) => a + b, 0);
        $('#c-total').textContent = peso(sum, 0);
        $('#c-avg').textContent = peso(sum / values.length, 0);
        $('#c-peak').textContent = peso(Math.max(...values), 0);
    }

    $('#range').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        current = b.dataset.r;
        [...$('#range').children].forEach((c) => c.classList.toggle('on', c === b));
        drawChart();
    });
    drawChart();
    new ResizeObserver(drawChart).observe($('#chart'));

    /* ---------- Category donut ---------- */
    const palette = ['#2563eb', '#06b6d4', '#8b5cf6', '#f59e0b', '#10b981', '#ec4899', '#94a3b8'];
    let cats = d.categories.map((c) => ({ name: c.name, total: +c.total }));
    if (cats.length > 6) cats = [...cats.slice(0, 5), { name: 'Others', total: cats.slice(5).reduce((a, c) => a + c.total, 0) }];
    const catTotal = cats.reduce((a, c) => a + c.total, 0);
    const R = 62, C = 2 * Math.PI * R;
    let offset = 0;

    $('#donut').innerHTML = `<svg viewBox="0 0 160 160" width="160" height="160" style="transform:rotate(-90deg)">
        <circle cx="80" cy="80" r="${R}" fill="none" stroke="var(--surface-2)" stroke-width="18"/>
        ${cats.map((c, i) => {
            const len = catTotal ? (c.total / catTotal) * C : 0;
            const seg = `<circle cx="80" cy="80" r="${R}" fill="none" stroke="${palette[i]}" stroke-width="18" stroke-dasharray="${Math.max(len - 3, 0)} ${C}" stroke-dashoffset="${-offset}"><title>${esc(c.name)}: ${peso(c.total)}</title></circle>`;
            offset += len;
            return seg;
        }).join('')}
    </svg><div class="donut-c"><b class="num">${peso(catTotal, 0)}</b><small>This month</small></div>`;
    $('#legend').innerHTML = cats.length
        ? cats.map((c, i) => `<li><i style="background:${palette[i]}"></i>${esc(c.name)}<span class="num">${catTotal ? Math.round((c.total / catTotal) * 100) : 0}%</span></li>`).join('')
        : '<li class="muted">No sales yet this month</li>';

    /* ---------- Recent transactions ---------- */
    const tones = ['t-brand', 't-info', 't-warn', 't-ok', 't-bad'];
    $('#txns').innerHTML = d.recent.length
        ? d.recent.map((s, i) => `<tr class="clickable" onclick="location.href='/transactions?invoice=${esc(s.invoice_no)}'">
            <td><b class="num">${esc(s.invoice_no)}</b><br><small class="muted">${App.datetime(s.created_at)}</small></td>
            <td><div class="cell"><span class="av ${tones[i % 5]}">${initials(s.customer_name || 'Walk in')}</span><div><b>${esc(s.customer_name || 'Walk-in customer')}</b><small>by ${esc(s.user?.name ?? '—')}</small></div></div></td>
            <td>${labels.payment[s.payment_method]}</td>
            <td class="r num"><b>${peso(s.total)}</b></td>
            <td><span class="pill ${s.status === 'paid' ? 'ok' : 'bad'}">${s.status === 'paid' ? 'Paid' : 'Voided'}</span></td>
        </tr>`).join('')
        : App.emptyRow(5, 'No transactions yet. Ring up your first sale in Point of Sale.', 'receipt');

    /* ---------- Inventory alerts ---------- */
    function drawAlerts(key) {
        const empty = (text) => `<li class="muted" style="justify-content:center;padding:28px 0">${icon('check')} ${text}</li>`;
        if (key === 'low') {
            $('#alerts').innerHTML = d.low_stock.length
                ? d.low_stock.map((m) => {
                    const pct = Math.min(100, Math.round((m.stock / Math.max(m.reorder_level, 1)) * 100));
                    const tone = m.stock === 0 || pct < 25 ? 'bad' : 'warn';
                    return `<li><div class="grow"><b>${esc(m.name)}</b><small>${num(m.stock)} ${esc(m.unit)}(s) left · reorder at ${num(m.reorder_level)}</small>
                        <div class="bar-m"><i style="width:${Math.max(pct, 3)}%;background:var(--${tone})"></i></div></div>
                        <span class="pill ${tone}">${m.stock === 0 ? 'Out' : tone === 'bad' ? 'Critical' : 'Low'}</span></li>`;
                }).join('')
                : empty('All items are well stocked');
        } else {
            $('#alerts').innerHTML = d.expiring.length
                ? d.expiring.map((b) => {
                    const days = daysUntil(b.expiry_date);
                    const tone = days <= 30 ? 'bad' : 'warn';
                    return `<li><div class="grow"><b>${esc(b.medicine?.name)}</b><small>Batch ${esc(b.batch_no)} · ${num(b.quantity)} on hand · ${App.date(b.expiry_date)}</small>
                        <div class="bar-m"><i style="width:${Math.max(4, 100 - (days / k.expiry_days) * 100)}%;background:var(--${tone})"></i></div></div>
                        <span class="pill ${tone}">${days}d left</span></li>`;
                }).join('')
                : empty('Nothing expiring soon');
        }
    }
    drawAlerts('low');
    $('#alert-tabs').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        [...$('#alert-tabs').children].forEach((c) => c.classList.toggle('on', c === b));
        drawAlerts(b.dataset.a);
    });

    /* ---------- Top sellers & payments ---------- */
    const topMax = Math.max(1, ...d.top.map((t) => +t.units));
    $('#top').innerHTML = d.top.length
        ? d.top.map((t, i) => `<li>
            <span class="rank ${i < 3 ? 'top' : ''}">${i + 1}</span>
            <div class="grow"><b>${esc(t.name)}</b><small>${peso(t.revenue)} revenue</small>
                <div class="bar-m"><i style="width:${(t.units / topMax) * 100}%;background:linear-gradient(90deg,#60a5fa,#2563eb)"></i></div></div>
            <b class="num" style="flex:none">${num(t.units)} <small>units</small></b>
        </li>`).join('')
        : '<li class="muted">No sales in the last 7 days</li>';

    const payIcons = { cash: ['cash', 't-ok'], gcash: ['phone', 't-info'], card: ['card', 't-brand'] };
    const payTotal = d.payments.reduce((a, p) => a + +p.total, 0);
    $('#payments').innerHTML = d.payments.length
        ? d.payments.map((p) => `<li>
            <span class="kpi-ic ${payIcons[p.payment_method][1]}">${icon(payIcons[p.payment_method][0])}</span>
            <div class="grow"><b>${labels.payment[p.payment_method]}</b><small>${num(p.count)} transactions</small>
                <div class="bar-m"><i style="width:${(p.total / payTotal) * 100}%;background:var(--brand)"></i></div></div>
            <b class="num" style="flex:none">${peso(p.total, 0)}</b>
        </li>`).join('')
        : '<li class="muted">No payments yet this month</li>';
});
