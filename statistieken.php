<?php
/*
 * Las Tapas - statistieken.php
 * Omzet en cijfers voor de baas. De gegevens komen uit:
 *  - archief:  elke afgesloten tafel (bij "betaald & vrijmaken" of "vertrokken zonder betalen")
 *  - verkocht: elk besteld gerecht
 */
session_start();
require __DIR__ . '/gedeeld.php';
vereisBaas();
set_exception_handler('toonFoutPagina');
db();

$periodes = [
    'vandaag' => ['Vandaag',          "DATE(%s) = CURDATE()"],
    'week'    => ['Afgelopen 7 dagen',  "%s >= (NOW() - INTERVAL 7 DAY)"],
    'maand'   => ['Afgelopen 30 dagen', "%s >= (NOW() - INTERVAL 30 DAY)"],
    'alles'   => ['Alles',            "1 = 1"],
];
$periode = array_key_exists($_GET['periode'] ?? '', $periodes) ? $_GET['periode'] : 'week';
$wArchief = sprintf($periodes[$periode][1], 'afgerond_op');
$wVerkocht = sprintf($periodes[$periode][1], 'datum');

function euroTekst($bedrag) {
    return '€ ' . number_format((float)$bedrag, 2, ',', '.');
}

// ---- Kerncijfers (alleen betaalde tafels tellen mee voor de omzet) ----
$kern = q("SELECT COUNT(*) AS tafels, COALESCE(SUM(totaal), 0) AS omzet,
                  COALESCE(SUM(volw + sen + kind), 0) AS gasten,
                  COALESCE(SUM(drank_kosten), 0) AS drank, COALESCE(SUM(verlengingen), 0) AS verlengingen
           FROM archief WHERE status = 'betaald' AND $wArchief")->fetch_assoc();
$verlaten = q("SELECT COUNT(*) AS n, COALESCE(SUM(totaal), 0) AS bedrag
               FROM archief WHERE status = 'verlaten' AND $wArchief")->fetch_assoc();

$tafels = intval($kern['tafels']);
$gasten = intval($kern['gasten']);
$omzet = floatval($kern['omzet']);

// ---- Omzet per dag ----
$perDag = alleRijen(q("SELECT DATE(afgerond_op) AS dag, SUM(totaal) AS omzet, COUNT(*) AS tafels
                       FROM archief WHERE status = 'betaald' AND $wArchief
                       GROUP BY DATE(afgerond_op) ORDER BY dag DESC LIMIT 31"));
$perDag = array_reverse($perDag);

// ---- Per arrangement en per betaalmethode ----
$perPakket = alleRijen(q("SELECT pakket, COUNT(*) AS tafels, SUM(volw + sen + kind) AS gasten, SUM(totaal) AS omzet
                          FROM archief WHERE status = 'betaald' AND $wArchief
                          GROUP BY pakket ORDER BY omzet DESC"));
$perMethode = alleRijen(q("SELECT betaalmethode, COUNT(*) AS tafels, SUM(totaal) AS omzet
                           FROM archief WHERE status = 'betaald' AND $wArchief
                           GROUP BY betaalmethode ORDER BY omzet DESC"));

// ---- Populairste gerechten en drankjes ----
$topEten = alleRijen(q("SELECT naam, SUM(aantal) AS aantal FROM verkocht
                        WHERE categorie <> 'drankje' AND $wVerkocht
                        GROUP BY naam ORDER BY aantal DESC LIMIT 10"));
$topDrank = alleRijen(q("SELECT naam, SUM(aantal) AS aantal FROM verkocht
                         WHERE categorie = 'drankje' AND $wVerkocht
                         GROUP BY naam ORDER BY aantal DESC LIMIT 5"));

// ---- Drukste uren (aantal bestelde gerechten per uur) ----
$perUur = alleRijen(q("SELECT HOUR(datum) AS uur, SUM(aantal) AS aantal FROM verkocht
                       WHERE $wVerkocht GROUP BY HOUR(datum) ORDER BY uur"));

// ---- Laatst afgesloten tafels ----
$recent = alleRijen(q("SELECT * FROM archief WHERE $wArchief ORDER BY afgerond_op DESC LIMIT 15"));

// Balkjes tekenen (breedte relatief aan de hoogste waarde)
function balken($rijen, $labelVeld, $waardeVeld, $isGeld = false) {
    if (!$rijen) return '<p class="uitleg">Nog geen gegevens in deze periode.</p>';
    $max = max(array_map(fn($r) => (float)$r[$waardeVeld], $rijen)) ?: 1;
    $html = '<div class="balken">';
    foreach ($rijen as $r) {
        $w = max(2, round((float)$r[$waardeVeld] / $max * 100));
        $waarde = $isGeld ? euroTekst($r[$waardeVeld]) : intval($r[$waardeVeld]);
        $html .= '<div class="balk-rij"><span class="balk-label">' . esc($r[$labelVeld]) . '</span>'
               . '<span class="balk-spoor"><span class="balk" style="width: ' . $w . '%"></span></span>'
               . '<span class="balk-waarde">' . esc($waarde) . '</span></div>';
    }
    return $html . '</div>';
}

$dagLabels = array_map(function ($r) {
    $r['label'] = date('d-m', strtotime($r['dag']));
    return $r;
}, $perDag);
$uurLabels = array_map(function ($r) {
    $r['label'] = sprintf('%02d:00', $r['uur']);
    return $r;
}, $perUur);
$methodeLabels = array_map(function ($r) {
    $namen = ['contant' => '💶 Contant', 'online' => '📱 Online', '' => 'Onbekend'];
    $r['label'] = ($namen[$r['betaalmethode']] ?? $r['betaalmethode']) . ' (' . intval($r['tafels']) . ' tafels)';
    return $r;
}, $perMethode);
$pakketLabels = array_map(function ($r) {
    $r['label'] = $r['pakket'] . ' (' . intval($r['tafels']) . ' tafels, ' . intval($r['gasten']) . ' gasten)';
    return $r;
}, $perPakket);

$paginaTitel = 'Statistieken';
$actievePagina = 'statistieken.php';
$extraStijl = '
    .periode-kies { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
    .periode-kies a { padding: 7px 14px; border-radius: 16px; background: #f0eae1; color: var(--grijs); text-decoration: none; font-weight: bold; font-size: 0.85rem; }
    .periode-kies a.actief { background: var(--inkt); color: white; }
    .kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
    .kpi { background: var(--vlak); border: 1px solid var(--lijn); border-radius: 10px; padding: 14px; }
    .kpi .waarde { font-size: 1.5rem; font-weight: bold; color: var(--inkt); }
    .kpi .label { font-size: 0.85rem; color: var(--grijs); }
    .kpi.hoofd { background: var(--rood); border-color: var(--rood); }
    .kpi.hoofd .waarde, .kpi.hoofd .label { color: white; }
    .kpi.waarschuwing .waarde { color: #c0392b; }
    .twee-kolom { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
    .balken { display: flex; flex-direction: column; gap: 7px; }
    .balk-rij { display: grid; grid-template-columns: minmax(90px, 38%) 1fr auto; gap: 10px; align-items: center; font-size: 0.9rem; }
    .balk-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .balk-spoor { background: #f0eae1; border-radius: 4px; height: 14px; }
    .balk { display: block; height: 100%; background: var(--rood); border-radius: 4px; }
    .balk-waarde { font-weight: bold; min-width: 60px; text-align: right; }
    .status-verlaten { color: #c0392b; font-weight: bold; }
    @media (max-width: 700px) {
        .twee-kolom { grid-template-columns: 1fr; }
        .balk-rij { grid-template-columns: 1fr auto; }
        .balk-spoor { grid-column: 1 / -1; order: 3; }
    }
';
include __DIR__ . '/beheer_kop.php';
?>

    <div class="periode-kies">
        <?php foreach ($periodes as $k => $p): ?>
            <a href="?periode=<?php echo $k; ?>" class="<?php echo $k === $periode ? 'actief' : ''; ?>"><?php echo esc($p[0]); ?></a>
        <?php endforeach; ?>
    </div>

    <div class="kpis">
        <div class="kpi hoofd"><div class="waarde"><?php echo euroTekst($omzet); ?></div><div class="label">Omzet</div></div>
        <div class="kpi"><div class="waarde"><?php echo $tafels; ?></div><div class="label">Betaalde tafels</div></div>
        <div class="kpi"><div class="waarde"><?php echo $gasten; ?></div><div class="label">Gasten</div></div>
        <div class="kpi"><div class="waarde"><?php echo euroTekst($tafels ? $omzet / $tafels : 0); ?></div><div class="label">Gemiddeld per tafel</div></div>
        <div class="kpi"><div class="waarde"><?php echo euroTekst($gasten ? $omzet / $gasten : 0); ?></div><div class="label">Gemiddeld per gast</div></div>
        <div class="kpi"><div class="waarde"><?php echo euroTekst($kern['drank']); ?></div><div class="label">Losse drankjes</div></div>
        <div class="kpi"><div class="waarde"><?php echo intval($kern['verlengingen']); ?>×</div><div class="label">Tijd verlengd</div></div>
        <div class="kpi <?php echo intval($verlaten['n']) > 0 ? 'waarschuwing' : ''; ?>">
            <div class="waarde"><?php echo intval($verlaten['n']); ?></div>
            <div class="label">Vertrokken zonder betalen (<?php echo euroTekst($verlaten['bedrag']); ?>)</div>
        </div>
    </div>

    <h2>Omzet per dag</h2>
    <?php echo balken($dagLabels, 'label', 'omzet', true); ?>

    <div class="twee-kolom">
        <div>
            <h2>Per arrangement</h2>
            <?php echo balken($pakketLabels, 'label', 'omzet', true); ?>
        </div>
        <div>
            <h2>Per betaalmethode</h2>
            <?php echo balken($methodeLabels, 'label', 'omzet', true); ?>
        </div>
    </div>

    <div class="twee-kolom">
        <div>
            <h2>Populairste gerechten</h2>
            <?php echo balken($topEten, 'naam', 'aantal'); ?>
        </div>
        <div>
            <h2>Populairste drankjes</h2>
            <?php echo balken($topDrank, 'naam', 'aantal'); ?>
            <h2>Drukste uren</h2>
            <p class="uitleg">Aantal bestelde gerechten en drankjes per uur.</p>
            <?php echo balken($uurLabels, 'label', 'aantal'); ?>
        </div>
    </div>

    <h2>Laatst afgesloten tafels</h2>
    <?php if (!$recent): ?>
        <p class="uitleg">Nog geen afgesloten tafels in deze periode. Tafels komen hier zodra een serveerster ze vrijmaakt.</p>
    <?php else: ?>
    <div class="tabel-scroll">
        <table class="kaarten">
            <thead><tr><th>Afgerond</th><th>Tafel</th><th>Gast</th><th>Arrangement</th><th>Totaal</th><th>Status</th><th>Door</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $a): ?>
                <tr>
                    <td data-label="Afgerond"><?php echo esc(date('d-m H:i', strtotime($a['afgerond_op']))); ?></td>
                    <td data-label="Tafel"><?php echo intval($a['tafel']); ?></td>
                    <td data-label="Gast"><?php echo esc($a['gast_naam']); ?> (<?php echo intval($a['volw']) + intval($a['sen']) + intval($a['kind']); ?>p)</td>
                    <td data-label="Arrangement"><?php echo esc($a['pakket']); ?></td>
                    <td data-label="Totaal"><strong><?php echo euroTekst($a['totaal']); ?></strong></td>
                    <td data-label="Status">
                        <?php if ($a['status'] === 'verlaten'): ?>
                            <span class="status-verlaten">Niet betaald</span>
                        <?php else: ?>
                            Betaald (<?php echo esc($a['betaalmethode'] ?: '-'); ?>)
                        <?php endif; ?>
                    </td>
                    <td data-label="Door"><?php echo esc($a['afgerond_door']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
