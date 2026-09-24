<?php
// Las Tapas - Verbindingstest
// Open deze pagina op je telefoon: http://JOUW-PC-IP/lastapas/test_verbinding.php
ini_set('display_errors', '0');

$resultaten = [];

// 1. PHP werkt (anders zag je deze pagina niet)
$resultaten[] = ['PHP draait op de server', true, 'PHP versie ' . PHP_VERSION];

// 2. Databaseverbinding
require_once __DIR__ . '/gedeeld.php';
try {
    $conn = db();   // gedeelde verbinding (maakt ontbrekende tabellen ook meteen aan)
} catch (Throwable $e) {
    $conn = new class($e->getMessage()) { public $connect_error; function __construct($m) { $this->connect_error = $m; } };
}
$dbOk = !$conn->connect_error;
$resultaten[] = ['Verbinding met database las_tapas_db', $dbOk, $dbOk ? 'Gelukt' : $conn->connect_error];

// 3. Tabellen
if ($dbOk) {
    $conn->set_charset('utf8mb4');
    foreach (['bestellingen', 'voorraad', 'tafels', 'chefs'] as $tabel) {
        $res = $conn->query("SELECT COUNT(*) AS n FROM `$tabel`");
        if ($res) {
            $n = $res->fetch_assoc()['n'];
            $resultaten[] = ["Tabel '$tabel'", true, "$n rijen"];
        } else {
            $resultaten[] = ["Tabel '$tabel'", false, $conn->error];
        }
    }

    // 4. Schrijftest (wordt meteen weer verwijderd)
    $ok = $conn->query("INSERT INTO bestellingen (tafel, pakket, gerecht, status, tijd) VALUES (99, 'TEST', 'verbindingstest', 'test', '00:00')");
    if ($ok) {
        $conn->query("DELETE FROM bestellingen WHERE tafel = 99 AND status = 'test'");
    }
    $resultaten[] = ['Schrijven naar database', (bool)$ok, $ok ? 'Gelukt (testregel weer verwijderd)' : $conn->error];
}

$serverIp = $_SERVER['SERVER_ADDR'] ?? 'onbekend';
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'onbekend';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Las Tapas - Verbindingstest</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #fdfbf7; color: #2d211d; margin: 0; padding: 16px; }
        .kaart { background: white; max-width: 600px; margin: 0 auto; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border-top: 6px solid #b33939; }
        h1 { color: #b33939; font-family: Georgia, serif; font-size: 1.5rem; margin-top: 0; }
        .rij { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid #eee; align-items: flex-start; }
        .icoon { font-size: 1.2rem; }
        .detail { font-size: 0.85rem; color: #786a63; word-break: break-word; }
        .info { background: #fcf8f2; padding: 12px; border-radius: 8px; font-size: 0.9rem; margin-top: 15px; }
        #js-test { margin-top: 15px; }
    </style>
</head>
<body>
    <div class="kaart">
        <h1>🔌 Verbindingstest</h1>

        <?php foreach ($resultaten as [$naam, $ok, $detail]): ?>
            <div class="rij">
                <span class="icoon"><?php echo $ok ? '✅' : '❌'; ?></span>
                <div>
                    <strong><?php echo htmlspecialchars($naam); ?></strong><br>
                    <span class="detail"><?php echo htmlspecialchars($detail); ?></span>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="rij" id="js-test">
            <span class="icoon" id="js-icoon">⏳</span>
            <div>
                <strong>Telefoon kan bestelling.php lezen</strong><br>
                <span class="detail" id="js-detail">Bezig met testen...</span>
            </div>
        </div>

        <div class="info">
            <strong>Server (PC) IP:</strong> <?php echo htmlspecialchars($serverIp); ?><br>
            <strong>Jouw apparaat IP:</strong> <?php echo htmlspecialchars($clientIp); ?><br>
            <strong>Pagina geopend via:</strong> <span id="adres"></span>
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

        document.getElementById('adres').innerText = window.location.origin;

        fetch('bestelling.php?client=1&_=' + Date.now(), { cache: 'no-store' })
            .then(res => res.text().then(t => ({ status: res.status, t })))
            .then(({ status, t }) => {
                try {
                    const data = JSON.parse(t);
                    const ok = data && Array.isArray(data.voorraad);
                    document.getElementById('js-icoon').innerText = ok ? '✅' : '❌';
                    document.getElementById('js-detail').innerText = ok
                        ? `Gelukt: ${data.voorraad.length} voorraad-items ontvangen`
                        : 'Antwoord ontvangen maar onverwacht: ' + t.slice(0, 200);
                } catch (e) {
                    document.getElementById('js-icoon').innerText = '❌';
                    document.getElementById('js-detail').innerText =
                        `Geen geldige JSON (HTTP ${status}): ` + t.replace(/<[^>]*>/g, ' ').trim().slice(0, 300);
                }
            })
            .catch(err => {
                document.getElementById('js-icoon').innerText = '❌';
                document.getElementById('js-detail').innerText = 'Kon bestelling.php niet bereiken: ' + err.message;
            });
    </script>
</body>
</html>
