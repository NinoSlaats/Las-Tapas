<?php
session_start();
// Beveiliging: Alleen koks én de baas hebben toegang tot het keukenscherm
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'chef' &&$_SESSION['rol'] !== 'baas')) {
    header("Location: login.html"); 
    exit();
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Las Tapas - Cocina (Keuken)</title>
    <style>
        :root {
            --rood: #b33939;
            --donkerrood: #822626;
            --creme: #fdfbf7;
            --inkt: #2d211d;
            --grijs: #786a63;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--creme);
            color: var(--inkt);
            margin: 0;
            padding: 0;
        }
        header {
            background: linear-gradient(135deg, var(--rood), var(--donkerrood));
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .header-title h1 { margin: 0; font-size: 2rem; font-family: Georgia, serif; }
        .header-title p { margin: 5px 0 0 0; opacity: 0.9; font-size: 0.95rem; }
        
        .user-info {
            text-align: right;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-details {
            text-align: right;
        }
        .btn-actie {
            padding: 6px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: bold;
            display: inline-block;
            transition: opacity 0.2s;
        }
        .btn-voorraad {
            background: #27ae60;
            color: white;
            margin-right: 5px;
        }
        .btn-voorraad:hover { opacity: 0.85; }
        .btn-switch {
            background: #2980b9;
            color: white;
            margin-right: 5px;
        }
        .btn-switch:hover { background: #2471a3; }
        .btn-baas {
            background: #f39c12;
            color: white;
            margin-right: 5px;
        }
        .btn-baas:hover { background: #d68910; }
        .btn-uitlog {
            background: #c0392b;
            color: white;
        }
        .btn-uitlog:hover { opacity: 0.85; }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .top-controls {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .etage-rij {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .etage-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fcf8f2;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #e0d5cb;
        }
        .etage-box select {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 0.9rem;
            max-width: 250px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            justify-content: center;
            border-top: 1px solid #eee;
            padding-top: 15px;
            flex-wrap: wrap;
        }
        .tab-btn {
            background: #f0eae1;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            cursor: pointer;
            color: var(--grijs);
            transition: all 0.2s;
            position: relative;
        }
        .tab-btn.active {
            background: var(--rood);
            color: white;
        }

        /* Badge voor meldingen op tabbladen (bijv. nieuwe bestelling binnen) */
        .badge-melding {
            background: #e74c3c;
            color: white;
            padding: 2px 6px;
            border-radius: 50%;
            font-size: 0.75rem;
            position: absolute;
            top: -5px;
            right: -5px;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(231, 76, 60, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
        }

        .orders-raster {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .order-kaart {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            border-left: 6px solid var(--rood);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .order-kaart.bezig { border-left-color: #f39c12; }
        .order-kaart.klaar { border-left-color: #27ae60; }
        .order-kaart.onderweg { border-left-color: #3498db; }
        .order-kaart.bezorgd { border-left-color: #2ecc71; opacity: 0.8; }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }
        .order-header h3 { margin: 0; color: var(--inkt); font-size: 1.2rem; }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge.nieuw { background: #fadbd8; color: #c0392b; }
        .badge.bezig { background: #fef9e7; color: #d35400; }
        .badge.klaar { background: #e8f8f5; color: #16a085; }
        .badge.onderweg { background: #ebf5fb; color: #2980b9; }
        .badge.bezorgd { background: #eafaf1; color: #27ae60; }

        .order-body p { margin: 6px 0; font-size: 0.95rem; }
        .pakket-tag {
            display: inline-block;
            background: #fcf8f2;
            border: 1px solid #e0d5cb;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            color: var(--grijs);
            margin-bottom: 10px;
        }

        .order-footer {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            border-top: 1px solid #f5f5f5;
            padding-top: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            font-weight: bold;
            font-size: 0.85rem;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-start { background: #27ae60; color: white; }
        .btn-klaar { background: #27ae60; color: white; }
        .btn-verwijder { background: #c0392b; color: white; }
        
        .select-aanpas {
            background-color: #8e44ad;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 0.85rem;
            cursor: pointer;
            outline: none;
        }

        .geen-orders {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: var(--grijs);
            font-style: italic;
            background: white;
            border-radius: 12px;
        }

.btn-geluid { background: #8e44ad; color: white; border: none; cursor: pointer; margin-right: 5px; font-family: inherit; }
        .btn-geluid.uit { background: #7f8c8d; }
        .opmerking-tag {
            background: #fff3cd; border-left: 4px solid #f39c12; color: #7d5a00;
            padding: 6px 10px; border-radius: 6px; font-weight: bold; font-size: 0.9rem; margin-top: 8px;
        }
        .medewerker-tag {
            background: #eef6fb;
            border-radius: 6px;
            padding: 5px 8px;
            font-size: 0.85rem !important;
            color: #1f4e6b;
        }

        /* ===== Responsive: tablet ===== */
        @media (max-width: 700px) {
            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                padding: 15px 16px;
            }
            .user-info {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
                text-align: left;
            }
            .user-details { text-align: left; }
            .container { padding: 0 12px; margin: 15px auto; }
            .etage-rij { grid-template-columns: 1fr; }
        }

        /* ===== Responsive: telefoon ===== */
        @media (max-width: 480px) {
            .header-title h1 { font-size: 1.3rem; }
            .top-controls { padding: 12px; }
            .filter-tabs { justify-content: flex-start; }
            .tab-btn { padding: 8px 12px; font-size: 0.8rem; }
            .orders-raster { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <header>
        <div class="header-title">
            <h1>🍷 Las Tapas - Cocina (Keuken)</h1>
            <p>Live overzicht van binnenkomende Spaanse lekkernijen</p>
        </div>
        <div class="user-info">
            <div class="user-details">
                <span>Ingelogd als: <strong><?php echo htmlspecialchars($_SESSION['gebruiker']); ?></strong></span>
            </div>
            <div>
                <button id="geluid-knop" class="btn-actie btn-geluid uit" onclick="wisselGeluid()">🔕 Geluid uit</button>
                <a href="voorraad.php" class="btn-actie btn-voorraad">📦 Voorraad</a>
                
                <?php if (isset($_SESSION['rol']) &&$_SESSION['rol'] === 'baas'): ?>
                    <a href="serveerster.php" class="btn-actie btn-switch">🏃‍♂️ Naar Serveerster Scherm</a>
                <?php endif; ?>

                <?php if (isset($_SESSION['rol']) &&$_SESSION['rol'] === 'baas'): ?>
                    <a href="baas_paneel.php" class="btn-actie btn-baas">⚙️ Beheerderspaneel</a>
                <?php endif; ?>
                <a href="logout.php" class="btn-actie btn-uitlog">Uitloggen</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="top-controls">
            <div class="etage-rij">
                <div class="etage-box">
                    <span>🏢 1e Etage (Tafels 1 - 10)</span>
                    <select id="filter-etage-1">
                        <option value="">-- Status 1e Etage tafels --</option>
                    </select>
                </div>
                <div class="etage-box">
                    <span>🏢 2e Etage (Tafels 11 - 20)</span>
                    <select id="filter-etage-2">
                        <option value="">-- Status 2e Etage tafels --</option>
                    </select>
                </div>
            </div>

            <!-- Filter tabs -->
            <div class="filter-tabs">
                <button class="tab-btn active" onclick="zetTab('alles', this)">
                    Openstaande Orders 📂 <span id="nieuw-badge" class="badge-melding" style="display:none;">0</span>
                </button>
                <button class="tab-btn" onclick="zetTab('bezig', this)">
                    Actieve Orders 🔥 <span id="bezig-badge" class="badge-melding" style="display:none;">0</span>
                </button>
                <button class="tab-btn" onclick="zetTab('klaar', this)">Voltooide Orders ✅</button>
                <button class="tab-btn" onclick="zetTab('bezorging', this)">Bezorgstatus 🏃‍♂️</button>
            </div>
        </div>

        <div class="orders-raster" id="orders-raster">
            <div class="geen-orders">Bestellingen laden...</div>
        </div>
    </div>

    <script>
        // Voor ngrok (gratis versie): sla de ngrok-waarschuwingspagina over bij
        // alle data-aanvragen, anders krijgt de pagina HTML terug in plaats van data.
        (function () {
            const origineleFetch = window.fetch;
            window.fetch = function (url, opties = {}) {
                opties.headers = Object.assign({}, opties.headers, { 'ngrok-skip-browser-warning': 'true' });
                return origineleFetch(url, opties);
            };
        })();

        // Keuzelijst "gerecht aanpassen": wordt gevuld vanuit het menu in de database
        let menuOpties = [];

        function laadMenuOpties() {
            fetch('bestelling.php?view=config&_=' + Date.now(), { cache: 'no-store' })
                .then(res => res.json())
                .then(cfg => {
                    menuOpties = [];
                    (cfg.menu || []).forEach(m => {
                        menuOpties.push(`1x ${m.naam}`, `2x ${m.naam}`);
                    });
                })
                .catch(err => console.error('Fout bij laden menu:', err));
        }

        // ===== Geluid bij nieuwe meldingen =====
        // Browsers spelen pas geluid af na een tik/klik op de pagina; daarom een aan/uit-knop.
        let geluidAan = false;
        try { geluidAan = localStorage.getItem('geluid_aan') === '1'; } catch (e) {}
        let audioCtx = null;

        function maakAudio() {
            if (!audioCtx) {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (AC) audioCtx = new AC();
            }
            if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
        }

        function speelPling() {
            if (!geluidAan) return;
            maakAudio();
            if (!audioCtx) return;
            const nu = audioCtx.currentTime;
            [880, 1320].forEach((freq, i) => {
                const osc = audioCtx.createOscillator();
                const vol = audioCtx.createGain();
                const start = nu + i * 0.18;
                osc.type = 'sine';
                osc.frequency.value = freq;
                vol.gain.setValueAtTime(0.0001, start);
                vol.gain.exponentialRampToValueAtTime(0.3, start + 0.02);
                vol.gain.exponentialRampToValueAtTime(0.0001, start + 0.35);
                osc.connect(vol).connect(audioCtx.destination);
                osc.start(start);
                osc.stop(start + 0.4);
            });
        }

        function zetGeluidKnop() {
            const knop = document.getElementById('geluid-knop');
            if (!knop) return;
            knop.innerText = geluidAan ? '🔔 Geluid aan' : '🔕 Geluid uit';
            knop.classList.toggle('uit', !geluidAan);
        }

        function wisselGeluid() {
            geluidAan = !geluidAan;
            try { localStorage.setItem('geluid_aan', geluidAan ? '1' : '0'); } catch (e) {}
            if (geluidAan) { maakAudio(); speelPling(); }
            zetGeluidKnop();
        }

        // Na de eerste tik op de pagina mag het geluid weer (als het aan stond)
        document.addEventListener('click', () => { if (geluidAan) maakAudio(); }, { once: true });
        document.addEventListener('DOMContentLoaded', zetGeluidKnop);



        // Tekst veilig tonen: voorkomt dat een klant via zijn naam code op dit scherm kan uitvoeren
        function esc(tekst) {
            return String(tekst ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        let huidigeTab = 'alles';
        let alleBestellingenCache = [];
        let tafelsStatusCache = {};
        let bezorgdTimestamps = {};

        function laadData() {
            // Laad zowel bestellingen als de tafels status tegelijkertijd op in WAMP
            Promise.all([
                fetch('bestelling.php').then(res => res.json()),
                fetch('bestelling.php?view=tafels').then(res => res.json())
            ])
            .then(([bestellingenData, tafelsData]) => {
                let nu = Date.now();
                let opgeschoondeData = [];

                (bestellingenData || []).forEach(order => {
                    if (order.status === 'bezorgd') {
                        if (!bezorgdTimestamps[order.id]) {
                            bezorgdTimestamps[order.id] = nu;
                        }
                        let verstrekenTijd = nu - bezorgdTimestamps[order.id];
                        if (verstrekenTijd > 10 * 60 * 1000) {
                            return; // Na 10 minuten automatisch verbergen
                        }
                    } else {
                        delete bezorgdTimestamps[order.id];
                    }
                    opgeschoondeData.push(order);
                });

                alleBestellingenCache = opgeschoondeData;
                tafelsStatusCache = tafelsData || {};
                
                updateTafelDropdowns();
                updateNieuwBadge();
                filterBestellingen();
            })
            .catch(err => console.error('Fout bij ophalen keukendata:', err));
        }

        function updateTafelDropdowns() {
            // Vul etage 1 (Tafels 1 t/m 10)
            const select1 = document.getElementById('filter-etage-1');
            let html1 = '<option value="">-- Status 1e Etage tafels --</option>';
            for (let i = 1; i <= 10; i++) {
                let tafelInfo = tafelsStatusCache[i];
                let isBezet = tafelInfo && tafelInfo.status === 'bezet';
                let statusTekst = isBezet ? `Bezet (${esc(tafelInfo.gast_naam || 'Gast')})` : 'Open';
                let emoji = isBezet ? '🔴' : '🟢';
                html1 += `<option value="${i}">${emoji} Tafel ${i}: ${statusTekst}</option>`;
            }
            select1.innerHTML = html1;

            // Vul etage 2 (Tafels 11 t/m 20)
            const select2 = document.getElementById('filter-etage-2');
            let html2 = '<option value="">-- Status 2e Etage tafels --</option>';
            for (let i = 11; i <= 20; i++) {
                let tafelInfo = tafelsStatusCache[i];
                let isBezet = tafelInfo && tafelInfo.status === 'bezet';
                let statusTekst = isBezet ? `Bezet (${esc(tafelInfo.gast_naam || 'Gast')})` : 'Open';
                let emoji = isBezet ? '🔴' : '🟢';
                html2 += `<option value="${i}">${emoji} Tafel ${i}: ${statusTekst}</option>`;
            }
            select2.innerHTML = html2;
        }

        function zetBadge(id, count) {
            const badge = document.getElementById(id);
            if (!badge) return;
            if (count > 0) {
                badge.style.display = 'inline-block';
                badge.innerText = count;
            } else {
                badge.style.display = 'none';
            }
        }

        let bekendeNieuweOrders = null;   // null = eerste keer laden (dan nog geen geluid)

        function controleerNieuweOrders() {
            const nieuw = alleBestellingenCache.filter(o => o.status === 'nieuw').map(o => String(o.id));
            if (bekendeNieuweOrders !== null && nieuw.some(id => !bekendeNieuweOrders.has(id))) {
                speelPling();
            }
            bekendeNieuweOrders = new Set(nieuw);
        }

        function updateNieuwBadge() {
            controleerNieuweOrders();
            // Openstaande (nieuwe) orders en actieve orders (in bereiding) krijgen een melding
            zetBadge('nieuw-badge', alleBestellingenCache.filter(o => o.status === 'nieuw').length);
            zetBadge('bezig-badge', alleBestellingenCache.filter(o => o.status === 'bezig').length);
        }

        function zetTab(tab, element) {
            huidigeTab = tab;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            element.classList.add('active');
            filterBestellingen();
        }

        function filterBestellingen() {
            const raster = document.getElementById('orders-raster');
            let data = alleBestellingenCache;

            if (huidigeTab === 'alles') {
                data = data.filter(o => o.status === 'nieuw');
            } else if (huidigeTab === 'bezig') {
                data = data.filter(o => o.status === 'bezig');
            } else if (huidigeTab === 'klaar') {
                data = data.filter(o => o.status === 'klaar');
            } else if (huidigeTab === 'bezorging') {
                data = data.filter(o => o.status === 'onderweg' || o.status === 'bezorgd');
            }

            if (!data || data.length === 0) {
                raster.innerHTML = '<div class="geen-orders">¡Todo listo! Geen orders in deze categorie.</div>';
                return;
            }

            raster.innerHTML = data.map(order => {
                let statusKlasse = order.status || 'nieuw';

                let optionsHtml = `<option value="" disabled>✏️ Aanpassen...</option>`;
                menuOpties.forEach(optie => {
                    let geselecteerd = (optie === order.gerecht) ? 'selected' : '';
                    optionsHtml += `<option value="${esc(optie)}" ${geselecteerd}>${esc(optie)}</option>`;
                });

                let isBezorgTab = (huidigeTab === 'bezorging');

                let voorraadWaarschuwing = '';
                if (order.voorraad_aantal !== null && order.voorraad_aantal <= 5) {
                    voorraadWaarschuwing = `<div style="background: #fadbd8; color: #c0392b; padding: 6px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; margin-top: 8px; border-left: 4px solid #c0392b;">⚠️ Let op: Nog maar ${order.voorraad_aantal} over in voorraad!</div>`;
                }

                return `
                    <div class="order-kaart ${esc(statusKlasse)}">
                        <div>
                            <div class="order-header">
                                <h3>Mesa ${order.tafel} (Tafel)</h3>
                                <span class="badge ${esc(statusKlasse)}">${esc(order.status)}</span>
                            </div>
                            <div class="pakket-tag">📦 ${esc(order.pakket || 'Onbekend arrangement')}</div>
                            <div class="order-body">
                                <p><strong>Tapa / Gerecht:</strong> ${esc(order.gerecht)}</p>
                                <p><small style="color: var(--grijs);">🕒 Besteld om: ${esc(order.tijd || '--:--')}</small></p>
                                ${order.chef_naam ? `<p class="medewerker-tag">👨‍🍳 Geaccepteerd door: <strong>${esc(order.chef_naam)}</strong></p>` : ''}
                                ${order.serveerster_naam ? `<p class="medewerker-tag">🏃‍♀️ ${order.status === 'bezorgd' ? 'Bezorgd door' : 'Onderweg met'}: <strong>${esc(order.serveerster_naam)}</strong></p>` : ''}
                                ${order.opmerking ? `<div class="opmerking-tag">📝 ${esc(order.opmerking)}</div>` : ''}
                                ${voorraadWaarschuwing}
                            </div>
                        </div>
                        <div class="order-footer">
                            ${!isBezorgTab ? `
                                ${order.status === 'nieuw' ? `<button class="btn btn-start" onclick="updateStatus(${order.id}, 'bezig')">Start Bereiding</button>` : ''}
                                ${order.status !== 'klaar' ? `<button class="btn btn-klaar" onclick="updateStatus(${order.id}, 'klaar')">Klaar</button>` : ''}
                                
                                <select class="select-aanpas" onchange="bewerkGerecht(${order.id}, this.value)">
                                    ${optionsHtml}
                                </select>

                                <button class="btn btn-verwijder" onclick="verwijderOrder(${order.id})">Verwijderen</button>
                            ` : `
                                <p style="margin: 0; font-size: 0.85rem; color: var(--grijs);"><em>Status wordt beheerd door bediening (Verdwijnt 10 min na bezorging)</em></p>
                            `}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function updateStatus(id, nieuweStatus) {
            fetch('bestelling.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ actie: 'update_status', id: id, nieuwe_status: nieuweStatus })
            })
            .then(res => res.json())
            .then(data => { if (!data.succes && data.melding) alert(data.melding); laadData(); });
        }

        function bewerkGerecht(id, nieuwGerecht) {
            if (!nieuwGerecht) return;

            fetch('bestelling.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    actie: 'bewerk_gerecht',
                    id: id,
                    gerecht: nieuwGerecht
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.succes) {
                    laadData();
                } else {
                    alert(data.melding || 'Fout bij opslaan van het gerecht.');
                    laadData();
                }
            });
        }

        function verwijderOrder(id) {
            if (confirm('Weet je zeker dat je deze bestelling wilt verwijderen?')) {
                fetch('bestelling.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ actie: 'verwijder', id: id })
                })
                .then(res => res.json())
                .then(data => { if (!data.succes) alert(data.melding || 'Verwijderen is niet gelukt.'); laadData(); });
            }
        }

        laadMenuOpties();
        setInterval(laadMenuOpties, 60000);
        setInterval(laadData, 3000);
        laadData();
    </script>
</body>
</html>