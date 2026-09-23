<?php
/*
 * Las Tapas - qr_generator.php
 * Maakt de QR-codes voor de 20 tafels. Elke QR-code bevat een geheime tafelcode,
 * zodat een klant niet kan bestellen voor een andere tafel door het nummer in de link te veranderen.
 * Alleen de baas kan deze pagina openen (anders zou iedereen alle codes kunnen zien).
 */
session_start();
require __DIR__ . '/gedeeld.php';
vereisBaas();
set_exception_handler('toonFoutPagina');
db();

$codes = [];
for ($i = 1; $i <= 20; $i++) $codes[$i] = tafelCode($i);

$paginaTitel = 'QR-codes';
$actievePagina = 'qr_generator.php';
$extraStijl = '
    .grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; margin-top: 20px; }
    .qr-kaartje { background: white; border: 2px dashed var(--rood); padding: 15px; border-radius: 10px; width: 170px; text-align: center; }
    .qr-kaartje h2 { color: var(--rood); margin: 5px 0; font-size: 1.2rem; }
    .qr-kaartje img { width: 130px; height: 130px; }
    .tafel-links { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee; }
    .tafel-links a { color: var(--rood); font-weight: bold; font-size: 0.85rem; text-decoration: none; }
    .tafel-links a:hover { text-decoration: underline; }
    .kopieer-knop { margin: 0; padding: 6px 8px; font-size: 0.8rem; background: #f0eae1; color: var(--grijs); }
    .kopieer-knop:hover { background: #e6dccf; }
    .etage-label { font-size: 0.75rem; color: #666; font-weight: bold; margin-bottom: 8px; display: block; }
    .print-btn { background-color: #27ae60; }
    .invoer-rij { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; }
    .invoer-rij > div { flex: 1; min-width: 240px; }
    .invoer-rij button { margin-top: 0; }
    .waarschuwing { background: #fdecea; color: #922b21; border: 1px solid #f5c6c0; padding: 10px 12px; border-radius: 6px; margin-top: 10px; font-size: 0.9rem; display: none; }
    @media print {
        body { background: white; padding: 0; }
        .container { box-shadow: none; padding: 0; }
        .no-print, .header-flex, .beheer-nav { display: none !important; }
        .qr-kaartje { break-inside: avoid; border: 1px solid #999; }
    }
';
include __DIR__ . '/beheer_kop.php';
?>

    <div class="no-print">
        <h2 style="margin-top: 0;">QR-codes voor de tafels</h2>
        <p class="uitleg">
            Elke QR-code bevat een geheime code voor die tafel. <strong>Oude QR-codes (zonder code) werken niet meer</strong>:
            print deze nieuwe versie en vervang de oude op de tafels.
            De QR-code voor medewerkers staat op een aparte pagina: <a href="medewerker_qr.html">QR-code medewerkers</a>.
        </p>
        <div class="form-box">
            <div class="invoer-rij">
                <div>
                    <label for="server-adres">Adres van de klantpagina</label>
                    <input type="text" id="server-adres" value="https://candied-smugness-concise.ngrok-free.dev/lastapas/klant.html">
                </div>
                <button onclick="genereerQRCodes()">QR-codes maken</button>
                <button class="print-btn" onclick="window.print()">🖨️ Printen</button>
            </div>
            <div class="waarschuwing" id="waarschuwing"></div>
            <p class="uitleg" id="voorbeeld-link" style="word-break: break-all;"></p>
        </div>
    </div>

    <div class="grid" id="container"></div>

</div>

<script>
    // Tafelcodes, berekend op de server
    const codes = <?php echo json_encode($codes); ?>;

    function controleerAdres(adres) {
        let url;
        try { url = new URL(adres); } catch (e) {
            return ['Dit is geen geldig adres. Begin met https:// of http://'];
        }
        const problemen = [];
        if (url.port === '5500' || url.port === '5501') problemen.push('Dit is de Live Server van VS Code: die kan geen PHP uitvoeren.');
        if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') problemen.push('"' + url.hostname + '" werkt alleen op deze pc, niet op een telefoon. Gebruik je ngrok-adres.');
        if (!url.pathname.endsWith('klant.html')) problemen.push('Het adres moet eindigen op klant.html.');
        return problemen;
    }

    function genereerQRCodes() {
        const basis = document.getElementById('server-adres').value.trim().split('?')[0];
        const problemen = controleerAdres(basis);
        const w = document.getElementById('waarschuwing');
        w.style.display = problemen.length ? 'block' : 'none';
        w.textContent = problemen.map(p => '⚠️ ' + p).join('  ');
        document.getElementById('voorbeeld-link').textContent = 'Voorbeeld (tafel 1): ' + basis + '?tafel=1&code=' + codes[1];

        const container = document.getElementById('container');
        container.innerHTML = '';
        for (let i = 1; i <= 20; i++) {
            const doel = `${basis}?tafel=${i}&code=${codes[i]}`;
            const qr = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' + encodeURIComponent(doel);
            const kaart = document.createElement('div');
            kaart.className = 'qr-kaartje';
            kaart.innerHTML = `
                <h2>Mesa ${i}</h2>
                <span class="etage-label">${i <= 10 ? '🏢 1e Etage' : '🏢 2e Etage'}</span>
                <img src="${qr}" alt="QR tafel ${i}">
                <p style="font-size: 0.7rem; margin-top: 6px; color: #444;">Scan om te bestellen</p>
                <div class="tafel-links no-print">
                    <a href="${doel}" target="_blank" rel="noopener">🔗 Open tafel ${i}</a>
                    <button type="button" class="kopieer-knop">📋 Kopieer link</button>
                </div>`;
            kaart.querySelector('.kopieer-knop').addEventListener('click', e => kopieerLink(doel, e.currentTarget));
            container.appendChild(kaart);
        }
    }

    // Link kopiëren (met reserve-methode voor browsers zonder klembord-toegang)
    function kopieerLink(link, knop) {
        const klaar = () => {
            knop.textContent = '✅ Gekopieerd';
            setTimeout(() => knop.textContent = '📋 Kopieer link', 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(klaar).catch(() => prompt('Kopieer deze link:', link));
        } else {
            prompt('Kopieer deze link:', link);
        }
    }

        document.getElementById('server-adres').addEventListener('keydown', e => { if (e.key === 'Enter') genereerQRCodes(); });
    genereerQRCodes();
</script>
</body>
</html>
