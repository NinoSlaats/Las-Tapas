<?php
/*
 * Las Tapas - qr_overzicht.php
 * Alle QR-codes op één plek:
 *  - Tafels: bekijken, groot tonen (om te laten scannen), openen, link kopiëren en printen.
 *  - Medewerkers: QR-code naar de inlogpagina voor personeel.
 * Alleen voor de baas en de serveerster, omdat de QR-codes de geheime tafelcodes bevatten.
 */
session_start();
require __DIR__ . '/gedeeld.php';

if (!in_array($_SESSION['rol'] ?? '', ['baas', 'serveerster'], true)) {
    header('Location: login.html');
    exit();
}
set_exception_handler('toonFoutPagina');
db();

$aantalTafels = 20;
$codes = [];
for ($i = 1; $i <= $aantalTafels; $i++) $codes[$i] = tafelCode($i);

$paginaTitel = 'QR-codes';
$actievePagina = 'qr_overzicht.php';
$extraStijl = '
    /* Tabbladen */
    .sectie-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--lijn); margin-bottom: 18px; flex-wrap: wrap; }
    .sectie-tabs button { background: none; color: var(--grijs); border-radius: 0; margin: 0; padding: 12px 18px; border-bottom: 3px solid transparent; margin-bottom: -2px; }
    .sectie-tabs button:hover { background: var(--vlak); }
    .sectie-tabs button.actief { color: var(--rood); border-bottom-color: var(--rood); }

    /* Werkbalk */
    .werkbalk { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
    .chips { display: flex; gap: 6px; flex-wrap: wrap; }
    .chips button { margin: 0; background: #f0eae1; color: var(--grijs); border-radius: 20px; padding: 8px 14px; font-size: 0.85rem; }
    .chips button.actief { background: var(--rood); color: white; }
    .werkbalk .rechts { margin-left: auto; display: flex; gap: 6px; flex-wrap: wrap; }
    .werkbalk .rechts input { width: 100px; margin: 0; padding: 8px 10px; border-radius: 20px; }
    .werkbalk .rechts button { margin: 0; padding: 8px 14px; border-radius: 20px; font-size: 0.85rem; }
    .knop-blauw { background: #1f4e8c !important; }
    .knop-groen { background: #27ae60 !important; }

    details.adres { background: var(--vlak); border: 1px solid var(--lijn); border-radius: 8px; padding: 8px 12px; margin-bottom: 18px; font-size: 0.9rem; }
    details.adres summary { cursor: pointer; font-weight: bold; color: var(--grijs); }
    .adres-rij { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .adres-rij input { flex: 1; min-width: 220px; margin: 0; }
    .adres-rij button { margin: 0; }
    .waarschuwing { color: #922b21; font-weight: bold; margin: 8px 0 0 0; }

    /* Kaartjes */
    .raster { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 14px; }
    .qr-kaart { background: white; border: 1px solid var(--lijn); border-top: 4px solid var(--rood); border-radius: 10px; padding: 12px; text-align: center; }
    .qr-kaart h3 { margin: 0; font-size: 1.25rem; }
    .etage { display: block; font-size: 0.75rem; color: var(--grijs); font-weight: bold; margin: 2px 0 8px; }
    .qr-knop { background: none !important; border: none; padding: 0; margin: 0 auto; display: block; cursor: zoom-in; }
    .qr-knop img { width: 140px; height: 140px; display: block; }
    .scan-klein { font-size: 0.7rem; color: #444; margin: 6px 0 0 0; }
    .kaart-acties { display: flex; gap: 4px; margin-top: 10px; }
    .kaart-acties > * { flex: 1; margin: 0; padding: 7px 4px; font-size: 0.75rem; border-radius: 6px; text-align: center; text-decoration: none; }
    .kaart-acties .open { background: white; color: var(--rood); border: 2px solid var(--rood); font-weight: bold; }
    .kaart-acties .kopieer { background: #f0eae1; color: var(--grijs); }

    /* Medewerkers */
    .medewerker-kaart { max-width: 360px; margin: 0 auto; background: white; border: 1px solid var(--lijn); border-top: 8px solid var(--inkt); border-radius: 12px; padding: 24px; text-align: center; }
    .medewerker-kaart img { width: 220px; height: 220px; }
    .medewerker-kaart .link { font-size: 0.75rem; color: var(--grijs); word-break: break-all; }
    .alleen { display: inline-block; margin-top: 10px; background: var(--inkt); color: white; padding: 5px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: bold; }

    /* Schermvullend */
    .groot { position: fixed; inset: 0; z-index: 2000; background: var(--creme); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 20px; }
    .groot[hidden] { display: none; }
    .groot h2 { margin: 0; font-size: clamp(2rem, 7vw, 4rem); line-height: 1; }
    .groot img { width: min(70vw, 62vh); height: min(70vw, 62vh); background: white; padding: 14px; border-radius: 16px; box-shadow: 0 6px 24px rgba(0,0,0,0.12); }
    .groot .scan { margin: 0; font-size: 1.1rem; font-weight: bold; }
    .groot-nav { display: flex; gap: 12px; }
    .groot-nav button { margin: 0; width: 56px; height: 56px; border-radius: 50%; font-size: 1.5rem; padding: 0; background: white; color: var(--inkt); border: 2px solid var(--lijn); }
    .groot-nav .sluit { width: auto; border-radius: 28px; padding: 0 22px; font-size: 1rem; background: var(--rood); color: white; border-color: var(--rood); }

    @media (max-width: 560px) {
        .werkbalk .rechts { margin-left: 0; width: 100%; }
        .werkbalk .rechts input { flex: 1; }
        .raster { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .qr-knop img { width: 110px; height: 110px; }
        .kaart-acties { flex-direction: column; }
    }

    /* Printen: alleen de QR-kaartjes */
    @media print {
        body { background: white; padding: 0; }
        .container { box-shadow: none; padding: 0; max-width: none; }
        .header-flex, .beheer-nav, .sectie-tabs, .werkbalk, details.adres, .kaart-acties, .groot, .uitleg, .taal-kiezer { display: none !important; }
        .raster { grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .qr-kaart { border: 2px dashed var(--rood); break-inside: avoid; }
        body.print-medewerker #sectie-tafels, body.print-tafels #sectie-medewerkers { display: none !important; }
        .sectie[hidden] { display: block !important; }
        body.print-medewerker .medewerker-kaart { box-shadow: none; margin-top: 40px; }
        body.print-medewerker .medewerker-knoppen { display: none; }
    }
';
include __DIR__ . '/beheer_kop.php';
?>

    <div class="sectie-tabs">
        <button type="button" data-sectie="tafels" class="actief" onclick="toonSectie('tafels')">🍽️ Tafels</button>
        <button type="button" data-sectie="medewerkers" onclick="toonSectie('medewerkers')">👤 Medewerkers</button>
    </div>

    <!-- ===== TAFELS ===== -->
    <section class="sectie" id="sectie-tafels">
        <div class="werkbalk">
            <div class="chips">
                <button type="button" class="actief" data-etage="alle" onclick="filterEtage('alle')">Alle tafels</button>
                <button type="button" data-etage="1" onclick="filterEtage('1')">1e etage</button>
                <button type="button" data-etage="2" onclick="filterEtage('2')">2e etage</button>
            </div>
            <form class="rechts" onsubmit="event.preventDefault(); gaNaarTafel();">
                <input type="number" id="zoek-tafel" min="1" max="<?php echo $aantalTafels; ?>" placeholder="Tafel nr.">
                <button type="submit" class="knop-blauw">🔍 Groot tonen</button>
                <button type="button" class="knop-groen" onclick="printen('tafels')">🖨️ Printen</button>
            </form>
        </div>
        <p class="uitleg">Tik op een QR-code om hem schermvullend te tonen. "Printen" print de tafels die je nu ziet (alle, of één etage).</p>

        <details class="adres">
            <summary>⚙️ Adres van de klantpagina</summary>
            <div class="adres-rij">
                <input type="text" id="adres">
                <button type="button" onclick="zetAdres()">Opslaan</button>
            </div>
            <p class="waarschuwing" id="waarschuwing" hidden></p>
        </details>

        <div class="raster" id="raster"></div>
    </section>

    <!-- ===== MEDEWERKERS ===== -->
    <section class="sectie" id="sectie-medewerkers" hidden>
        <div class="medewerker-kaart">
            <h3 style="margin-top: 0;">🍷 Las Tapas</h3>
            <p style="font-weight: bold; color: var(--grijs); margin-top: 0;">Inloggen personeel</p>
            <img id="medewerker-qr" alt="QR-code naar de inlogpagina voor medewerkers">
            <p class="uitleg">Scan met je telefoon of tablet om in te loggen als chef, serveerster of beheerder.</p>
            <p class="link" id="medewerker-link"></p>
            <span class="alleen">Alleen voor medewerkers</span>
            <div class="medewerker-knoppen" style="display: flex; gap: 8px; justify-content: center;">
                <button type="button" class="knop-blauw" onclick="toonGroot('login')">🔍 Groot tonen</button>
                <button type="button" class="knop-groen" onclick="printen('medewerker')">🖨️ Printen</button>
            </div>
        </div>
        <p class="uitleg" style="text-align: center; margin-top: 15px;">Hang deze QR-code op een plek waar alleen personeel komt, bijvoorbeeld in de keuken.</p>
    </section>

</div>

<!-- Schermvullende QR-code -->
<div class="groot" id="groot" hidden onclick="if (event.target === this) sluitGroot()">
    <h2 id="groot-titel">Mesa 1</h2>
    <span class="etage" id="groot-etage"></span>
    <img id="groot-qr" alt="QR-code">
    <p class="scan" id="groot-scan">📱 Scan om te bestellen</p>
    <div class="groot-nav">
        <button type="button" id="groot-vorige" onclick="bladeren(-1)" aria-label="Vorige tafel">←</button>
        <button type="button" class="sluit" onclick="sluitGroot()">Sluiten</button>
        <button type="button" id="groot-volgende" onclick="bladeren(1)" aria-label="Volgende tafel">→</button>
    </div>
</div>

<script>
    const codes = <?php echo json_encode($codes); ?>;     // geheime tafelcodes (berekend op de server)
    const STANDAARD_ADRES = 'https://candied-smugness-concise.ngrok-free.dev/lastapas/klant.html';

    let adres = localStorage.getItem('qr_overzicht_adres') || STANDAARD_ADRES;
    let etageFilter = 'alle';
    let grootTafel = null;

    const etageVan = t => (t <= 10 ? '1' : '2');
    const linkVan = t => `${adres}?tafel=${t}&code=${codes[t]}`;
    const loginLink = () => adres.replace(/klant\.html$/, 'login.html');
    const qrVoor = (data, px) => `https://api.qrserver.com/v1/create-qr-code/?size=${px}x${px}&margin=4&data=${encodeURIComponent(data)}`;
    const zichtbareTafels = () => Object.keys(codes).map(Number).filter(t => etageFilter === 'alle' || etageVan(t) === etageFilter);

    function toonSectie(sectie) {
        document.querySelectorAll('.sectie').forEach(el => el.hidden = el.id !== 'sectie-' + sectie);
        document.querySelectorAll('.sectie-tabs button').forEach(b => b.classList.toggle('actief', b.dataset.sectie === sectie));
    }

    function tekenRaster() {
        const raster = document.getElementById('raster');
        raster.innerHTML = '';
        zichtbareTafels().forEach(t => {
            const kaart = document.createElement('div');
            kaart.className = 'qr-kaart';
            kaart.innerHTML = `
                <h3>Mesa ${t}</h3>
                <span class="etage">🏢 ${etageVan(t)}e etage</span>
                <button type="button" class="qr-knop" aria-label="QR-code van tafel ${t} groot tonen">
                    <img src="${qrVoor(linkVan(t), 300)}" alt="QR-code tafel ${t}" loading="lazy">
                </button>
                <p class="scan-klein">Scan om te bestellen</p>
                <div class="kaart-acties">
                    <a class="open" href="${linkVan(t)}" target="_blank" rel="noopener">🔗 Openen</a>
                    <button type="button" class="kopieer">📋 Kopieer link</button>
                </div>`;
            kaart.querySelector('.qr-knop').addEventListener('click', () => toonGroot(t));
            kaart.querySelector('.kopieer').addEventListener('click', e => kopieer(linkVan(t), e.currentTarget));
            raster.appendChild(kaart);
        });
        // Medewerkers-QR hoort bij hetzelfde adres
        document.getElementById('medewerker-qr').src = qrVoor(loginLink(), 440);
        document.getElementById('medewerker-link').textContent = loginLink();
    }

    function filterEtage(etage) {
        etageFilter = etage;
        document.querySelectorAll('.chips button').forEach(b => b.classList.toggle('actief', b.dataset.etage === etage));
        tekenRaster();
    }

    // Schermvullend tonen (een tafel, of 'login' voor de medewerkers)
    function toonGroot(t) {
        grootTafel = t;
        const login = t === 'login';
        document.getElementById('groot-titel').textContent = login ? 'Las Tapas' : `Mesa ${t}`;
        document.getElementById('groot-etage').textContent = login ? '👤 Inloggen personeel' : `🏢 ${etageVan(t)}e etage · Tafel ${t}`;
        document.getElementById('groot-scan').textContent = login ? '📱 Scan om in te loggen' : '📱 Scan om te bestellen';
        document.getElementById('groot-qr').src = qrVoor(login ? loginLink() : linkVan(t), 600);
        document.getElementById('groot-vorige').hidden = login;
        document.getElementById('groot-volgende').hidden = login;
        document.getElementById('groot').hidden = false;
    }

    function sluitGroot() {
        document.getElementById('groot').hidden = true;
        grootTafel = null;
    }

    function bladeren(stap) {
        if (grootTafel === 'login') return;
        const lijst = zichtbareTafels();
        const i = lijst.indexOf(grootTafel);
        toonGroot(lijst[(i + stap + lijst.length) % lijst.length]);
    }

    function gaNaarTafel() {
        const t = parseInt(document.getElementById('zoek-tafel').value);
        if (codes[t]) toonGroot(t);
    }

    // Alleen de tafels of alleen de medewerkers-QR printen
    function printen(wat) {
        document.body.classList.remove('print-tafels', 'print-medewerker');
        document.body.classList.add(wat === 'medewerker' ? 'print-medewerker' : 'print-tafels');
        window.print();
    }

    function kopieer(link, knop) {
        const klaar = () => { knop.textContent = '✅ Gekopieerd'; setTimeout(() => knop.textContent = '📋 Kopieer link', 1500); };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(link).then(klaar).catch(() => prompt('Kopieer deze link:', link));
        } else {
            prompt('Kopieer deze link:', link);
        }
    }

    function controleerAdres(waarde) {
        try {
            const url = new URL(waarde);
            if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') return 'Let op: "localhost" werkt niet op een telefoon. Gebruik je ngrok-adres.';
            if (url.port === '5500' || url.port === '5501') return 'Let op: dit is de Live Server van VS Code; die kan geen PHP uitvoeren.';
            if (!url.pathname.endsWith('klant.html')) return 'Het adres moet eindigen op klant.html.';
            return '';
        } catch (e) {
            return 'Dit is geen geldig adres. Begin met https://';
        }
    }

    function zetAdres() {
        const waarde = document.getElementById('adres').value.trim().split('?')[0];
        const probleem = controleerAdres(waarde);
        const w = document.getElementById('waarschuwing');
        w.textContent = probleem;
        w.hidden = !probleem;
        if (probleem.startsWith('Dit is geen') || probleem.startsWith('Het adres')) return;
        adres = waarde;
        localStorage.setItem('qr_overzicht_adres', adres);
        tekenRaster();
    }

    document.addEventListener('keydown', e => {
        if (grootTafel === null) return;
        if (e.key === 'ArrowRight') bladeren(1);
        if (e.key === 'ArrowLeft') bladeren(-1);
        if (e.key === 'Escape') sluitGroot();
    });

    document.getElementById('adres').value = adres;
    tekenRaster();
</script>
</body>
</html>
