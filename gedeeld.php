<?php
/*
 * Las Tapas - gedeeld.php
 * Code die door alle PHP-bestanden wordt gebruikt:
 *  - de databaseverbinding
 *  - het (eenmalig) aanmaken van alle tabellen en startgegevens
 *  - hulpfuncties voor menu, arrangementen, instellingen, tafelcodes en beveiliging
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Europe/Amsterdam');
mysqli_report(MYSQLI_REPORT_OFF);

// Verhoog dit getal als er tabellen/kolommen bijkomen; dan wordt de database bijgewerkt.
const SCHEMA_VERSIE = '9';

// De 14 allergenen die een restaurant in de EU moet kunnen benoemen
const ALLERGENEN = [
    'gluten'      => ['label' => 'Gluten',      'icoon' => '🌾'],
    'schaaldieren'=> ['label' => 'Schaaldieren','icoon' => '🦐'],
    'eieren'      => ['label' => 'Ei',          'icoon' => '🥚'],
    'vis'         => ['label' => 'Vis',         'icoon' => '🐟'],
    'pinda'       => ['label' => 'Pinda',       'icoon' => '🥜'],
    'soja'        => ['label' => 'Soja',        'icoon' => '🫘'],
    'melk'        => ['label' => 'Melk',        'icoon' => '🥛'],
    'noten'       => ['label' => 'Noten',       'icoon' => '🌰'],
    'selderij'    => ['label' => 'Selderij',    'icoon' => '🥬'],
    'mosterd'     => ['label' => 'Mosterd',     'icoon' => '🟡'],
    'sesam'       => ['label' => 'Sesam',       'icoon' => '⚪'],
    'sulfiet'     => ['label' => 'Sulfiet',     'icoon' => '🍷'],
    'lupine'      => ['label' => 'Lupine',      'icoon' => '🌼'],
    'weekdieren'  => ['label' => 'Weekdieren',  'icoon' => '🦪'],
];

const CATEGORIEEN = [
    'voorgerecht'  => '🫒 Voorgerechten',
    'hoofdgerecht' => '🔥 Warme tapas',
    'drankje'      => '🍷 Drankjes',
    'toetje'       => '🍨 Toetjes',
];

// ===================== DATABASE =====================

function db() {
    static $conn = null;
    if ($conn === null) {
        $c = @new mysqli('localhost', 'root', '', 'las_tapas_db');
        if ($c->connect_error) {
            throw new RuntimeException('DB Verbinding mislukt: ' . $c->connect_error);
        }
        $c->set_charset('utf8mb4');
        $conn = $c;
        setupDatabase($conn);
    }
    return $conn;
}

// Prepared statement uitvoeren: q("SELECT ... WHERE id = ?", "i", [5])
function q($sql, $types = '', $params = []) {
    $conn = db();
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('SQL-fout: ' . $conn->error);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) throw new RuntimeException('SQL-fout: ' . $stmt->error);
    $res = $stmt->get_result();
    return $res ?: $stmt;
}

function alleRijen($res) {
    $lijst = [];
    while ($r = $res->fetch_assoc()) $lijst[] = $r;
    return $lijst;
}

function kolomToevoegen($conn, $tabel, $kolom, $definitie) {
    $check = $conn->query("SHOW COLUMNS FROM `$tabel` LIKE '$kolom'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `$tabel` ADD COLUMN `$kolom` $definitie");
    }
}

// Maakt alle tabellen en startgegevens aan. Draait alleen als SCHEMA_VERSIE is veranderd.
function setupDatabase($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS instellingen (
        sleutel VARCHAR(50) PRIMARY KEY,
        waarde VARCHAR(255) NOT NULL DEFAULT ''
    )");
    $r = $conn->query("SELECT waarde FROM instellingen WHERE sleutel = 'schema_versie'");
    $rij = $r ? $r->fetch_assoc() : null;
    if ($rij && $rij['waarde'] === SCHEMA_VERSIE) return;

    // --- Bestaande tabellen ---
    $conn->query("CREATE TABLE IF NOT EXISTS chefs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        naam VARCHAR(100) NOT NULL,
        gebruikersnaam VARCHAR(100) NOT NULL UNIQUE,
        wachtwoord VARCHAR(255) NOT NULL,
        rol VARCHAR(20) NOT NULL DEFAULT 'chef'
    )");
    $k = $conn->query("SHOW COLUMNS FROM chefs LIKE 'wachtwoord'");
    $ki = $k ? $k->fetch_assoc() : null;
    if ($ki && stripos($ki['Type'], 'varchar(255)') === false) {
        $conn->query("ALTER TABLE chefs MODIFY wachtwoord VARCHAR(255) NOT NULL");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS bestellingen (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tafel INT NOT NULL,
        pakket VARCHAR(255) DEFAULT '',
        gerecht TEXT NOT NULL,
        status VARCHAR(50) DEFAULT 'nieuw',
        tijd VARCHAR(50) DEFAULT ''
    )");
    kolomToevoegen($conn, 'bestellingen', 'bezorgd_op', "DATETIME NULL DEFAULT NULL");
    kolomToevoegen($conn, 'bestellingen', 'chef_naam', "VARCHAR(100) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'bestellingen', 'serveerster_naam', "VARCHAR(100) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'bestellingen', 'bedrag', "DECIMAL(8,2) NOT NULL DEFAULT 0");
    kolomToevoegen($conn, 'bestellingen', 'opmerking', "VARCHAR(255) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'bestellingen', 'besteld_op', "DATETIME NULL DEFAULT NULL");
    kolomToevoegen($conn, 'bestellingen', 'aangepast', "TINYINT NOT NULL DEFAULT 0");   // door de keuken gewijzigd

    $conn->query("CREATE TABLE IF NOT EXISTS voorraad (
        id INT AUTO_INCREMENT PRIMARY KEY,
        naam VARCHAR(100) NOT NULL,
        categorie VARCHAR(50) NOT NULL,
        aantal INT NOT NULL DEFAULT 0,
        minimum_drempel INT NOT NULL DEFAULT 5,
        eenheid VARCHAR(20) NOT NULL DEFAULT 'Porties'
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS tafels (
        tafel INT PRIMARY KEY,
        status VARCHAR(20) DEFAULT 'bezet',
        gast_naam VARCHAR(100) DEFAULT '',
        gezelschap VARCHAR(100) DEFAULT '',
        pakket VARCHAR(100) DEFAULT ''
    )");
    kolomToevoegen($conn, 'tafels', 'geaccepteerd_door', "VARCHAR(100) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'tafels', 'volw', "INT NOT NULL DEFAULT 0");
    kolomToevoegen($conn, 'tafels', 'sen', "INT NOT NULL DEFAULT 0");
    kolomToevoegen($conn, 'tafels', 'kind', "INT NOT NULL DEFAULT 0");
    kolomToevoegen($conn, 'tafels', 'start_tijd', "DATETIME NULL DEFAULT NULL");
    kolomToevoegen($conn, 'tafels', 'eind_tijd', "DATETIME NULL DEFAULT NULL");
    kolomToevoegen($conn, 'tafels', 'verlengingen', "INT NOT NULL DEFAULT 0");
    kolomToevoegen($conn, 'tafels', 'drank_kosten', "DECIMAL(8,2) NOT NULL DEFAULT 0");
    kolomToevoegen($conn, 'tafels', 'betaalmethode', "VARCHAR(20) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'tafels', 'laatste_activiteit', "DATETIME NULL DEFAULT NULL");
    // Bon: 'geen', 'email' of 'papier' (gekozen door de gast bij het afrekenen)
    kolomToevoegen($conn, 'tafels', 'bon_keuze', "VARCHAR(10) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'tafels', 'bon_email', "VARCHAR(190) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'tafels', 'bon_taal', "VARCHAR(2) NOT NULL DEFAULT 'nl'");
    kolomToevoegen($conn, 'archief', 'bon_keuze', "VARCHAR(10) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'archief', 'bon_status', "VARCHAR(20) NOT NULL DEFAULT ''");

    // Unieke code per bezoek: telefoons van een afgesloten bezoek kunnen de tafel niet opnieuw openen
    kolomToevoegen($conn, 'tafels', 'sessie', "VARCHAR(32) NOT NULL DEFAULT ''");
    // Serveerster roepen: '' (geen), 'open' (wacht op serveerster) of 'geaccepteerd'
    kolomToevoegen($conn, 'tafels', 'oproep_status', "VARCHAR(20) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'tafels', 'oproep_reden', "VARCHAR(30) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'tafels', 'oproep_tekst', "VARCHAR(200) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'tafels', 'oproep_tijd', "DATETIME NULL DEFAULT NULL");
    kolomToevoegen($conn, 'tafels', 'oproep_door', "VARCHAR(100) NOT NULL DEFAULT ''");

    // --- Nieuwe tabellen ---
    $conn->query("CREATE TABLE IF NOT EXISTS menu (
        id VARCHAR(40) PRIMARY KEY,
        naam VARCHAR(100) NOT NULL,
        omschrijving VARCHAR(255) NOT NULL DEFAULT '',
        categorie VARCHAR(20) NOT NULL DEFAULT 'hoofdgerecht',
        prijs DECIMAL(6,2) NOT NULL DEFAULT 0,
        allergenen VARCHAR(255) NOT NULL DEFAULT '',
        actief TINYINT NOT NULL DEFAULT 1,
        volgorde INT NOT NULL DEFAULT 0
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS pakketten (
        naam VARCHAR(100) PRIMARY KEY,
        omschrijving VARCHAR(255) NOT NULL DEFAULT '',
        duur INT NOT NULL DEFAULT 120,
        drank TINYINT NOT NULL DEFAULT 0,
        prijs_volw DECIMAL(6,2) NOT NULL DEFAULT 0,
        prijs_sen DECIMAL(6,2) NOT NULL DEFAULT 0,
        prijs_kind DECIMAL(6,2) NOT NULL DEFAULT 0,
        volgorde INT NOT NULL DEFAULT 0
    )");

    // Afgeronde tafels (voor omzet en statistieken)
    $conn->query("CREATE TABLE IF NOT EXISTS archief (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tafel INT NOT NULL,
        gast_naam VARCHAR(100) NOT NULL DEFAULT '',
        pakket VARCHAR(100) NOT NULL DEFAULT '',
        volw INT NOT NULL DEFAULT 0,
        sen INT NOT NULL DEFAULT 0,
        kind INT NOT NULL DEFAULT 0,
        verlengingen INT NOT NULL DEFAULT 0,
        drank_kosten DECIMAL(8,2) NOT NULL DEFAULT 0,
        totaal DECIMAL(8,2) NOT NULL DEFAULT 0,
        betaalmethode VARCHAR(20) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'betaald',
        afgerond_door VARCHAR(100) NOT NULL DEFAULT '',
        start_tijd DATETIME NULL DEFAULT NULL,
        afgerond_op DATETIME NOT NULL
    )");

    // Beoordeling van de gast na het betalen (1 t/m 5 sterren)
    kolomToevoegen($conn, 'archief', 'beoordeling', "TINYINT NULL DEFAULT NULL");
    kolomToevoegen($conn, 'archief', 'review_tekst', "VARCHAR(300) NOT NULL DEFAULT ''");
    kolomToevoegen($conn, 'archief', 'review_op', "DATETIME NULL DEFAULT NULL");

    // Elk besteld gerecht (voor 'populairste gerechten' en de ronde-limiet)
    $conn->query("CREATE TABLE IF NOT EXISTS verkocht (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bestelling_id INT NOT NULL,
        tafel INT NOT NULL,
        naam VARCHAR(100) NOT NULL,
        categorie VARCHAR(20) NOT NULL DEFAULT '',
        aantal INT NOT NULL DEFAULT 1,
        datum DATETIME NOT NULL,
        INDEX (bestelling_id),
        INDEX (tafel, datum)
    )");

    // Mislukte inlogpogingen (om wachtwoorden raden te blokkeren)
    $conn->query("CREATE TABLE IF NOT EXISTS login_pogingen (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gebruikersnaam VARCHAR(100) NOT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        tijd DATETIME NOT NULL,
        INDEX (gebruikersnaam, tijd)
    )");

    // --- Startgegevens: menu (alleen als het menu nog leeg is) ---
    $r = $conn->query("SELECT COUNT(*) AS n FROM menu");
    if ($r && intval($r->fetch_assoc()['n']) === 0) {
        $startMenu = [
            ['pan', 'Pan con Tomate', 'Geroosterde boerenboterham met verse tomaat, knoflook en olijfolie', 'voorgerecht', 0, 'gluten'],
            ['olijven', 'Aceitunas & Queso', 'Spaanse manzanilla olijven met blokjes oude Manchego kaas', 'voorgerecht', 0, 'melk'],
            ['jamon', 'Jamón Serrano', 'Authentieke gedroogde Spaanse serranoham', 'voorgerecht', 0, ''],
            ['patatas', 'Patatas Bravas', 'Krokante aardappeltjes met pittige brava-saus en huisgemaakte aioli', 'hoofdgerecht', 0, 'eieren'],
            ['gambas', 'Gambas al Ajillo', 'Sissende knoflookgarnalen met chilipeper en verse peterselie', 'hoofdgerecht', 0, 'schaaldieren'],
            ['albondigas', 'Albóndigas', 'Spaanse rundergehaktballetjes in rijke tomaten-kruidensaus', 'hoofdgerecht', 0, 'gluten,eieren,selderij'],
            ['paella', 'Paella Valenciana', 'Traditionele saffraanrijst met malse kip en mediterrane groenten', 'hoofdgerecht', 0, 'selderij'],
            ['croquetas', 'Croquetas de Jamón', 'Romige hamkroketjes met een krokante korst (4 stuks)', 'hoofdgerecht', 0, 'gluten,eieren,melk'],
            ['chorizo', 'Chorizo al Vino', 'Pikante Spaanse chorizo worst gestoofd in rode wijn', 'hoofdgerecht', 0, 'sulfiet'],
            ['sangria', 'Sangría Tradicional', 'Heerlijke frisse karaf huisgemaakte traditionele sangría', 'drankje', 16.50, 'sulfiet'],
            ['tinto', 'Tinto de Verano', 'Spaanse zomerse cocktail van rode wijn met citroenlimonade', 'drankje', 5.50, 'sulfiet'],
            ['cerveza', 'Cerveza San Miguel', 'Koud verfrissend Spaans tapbier (30cl)', 'drankje', 4.20, 'gluten'],
            ['agua', 'Agua de Valencia', 'Cocktail met cava, gin, wodka en vers sinaasappelsap', 'drankje', 8.50, 'sulfiet'],
            ['churros', 'Churros con Chocolate', 'Krokante Spaanse churros met dikke warme chocoladesaus', 'toetje', 0, 'gluten,eieren,melk'],
            ['crema', 'Crema Catalana', 'Fluwelen Spaanse vanillepudding met een gekaramelliseerd suikerlaagje', 'toetje', 0, 'eieren,melk'],
            ['helado', 'Helado de Turrón', 'Ambachtelijk Spaans noga-ijs met amandelsnippers', 'toetje', 0, 'melk,eieren,noten'],
        ];
        $stmt = $conn->prepare("INSERT INTO menu (id, naam, omschrijving, categorie, prijs, allergenen, actief, volgorde) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
        foreach ($startMenu as $i => $m) {
            $volgorde = ($i + 1) * 10;
            $stmt->bind_param("ssssdsi", $m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $volgorde);
            $stmt->execute();
        }
    }

    // --- Startgegevens: arrangementen ---
    $r = $conn->query("SELECT COUNT(*) AS n FROM pakketten");
    if ($r && intval($r->fetch_assoc()['n']) === 0) {
        $conn->query("INSERT INTO pakketten (naam, omschrijving, duur, drank, prijs_volw, prijs_sen, prijs_kind, volgorde) VALUES
            ('Tapas Esencial (2u)', 'Onbeperkt tapas eten. Drankjes kun je los bijbestellen.', 120, 0, 32.50, 27.50, 17.50, 1),
            ('Tapas & Bebidas (2u + Drank)', 'Onbeperkt tapas eten + Spaanse drankjes.', 120, 1, 42.50, 37.50, 22.50, 2),
            ('Gran Tapas (3u + Drank)', '3 uur lang onbeperkt genieten van eten én drankjes!', 180, 1, 49.50, 44.50, 25.00, 3)");
    }

    // --- Standaardinstellingen (bestaande waarden blijven staan) ---
    $geheim = bin2hex(random_bytes(16));
    $conn->query("INSERT IGNORE INTO instellingen (sleutel, waarde) VALUES
        ('verleng_minuten', '30'),
        ('verleng_prijs_pp', '6.00'),
        ('ronde_max_pp', '3'),
        ('ronde_minuten', '10'),
        ('opruimen_na_uren', '4'),
        ('tafel_geheim', '$geheim'),
        ('smtp_host', 'smtp.gmail.com'),
        ('smtp_poort', '587'),
        ('smtp_beveiliging', 'tls'),
        ('smtp_gebruiker', ''),
        ('smtp_wachtwoord', ''),
        ('smtp_controle_uit', '0'),
        ('mail_afzender_naam', 'Las Tapas')");

    // Elk menu-item krijgt een voorraadregel (koppeling op naam)
    $conn->query("INSERT INTO voorraad (naam, categorie, aantal)
                  SELECT m.naam, m.categorie, 25 FROM menu m
                  WHERE NOT EXISTS (SELECT 1 FROM voorraad v WHERE v.naam = m.naam)");

    // Oude 'vrij'-rijen opruimen (vrije tafels staan niet meer in de tabel)
    $conn->query("DELETE FROM tafels WHERE status = 'vrij'");

    $conn->query("INSERT INTO instellingen (sleutel, waarde) VALUES ('schema_versie', '" . SCHEMA_VERSIE . "')
                  ON DUPLICATE KEY UPDATE waarde = '" . SCHEMA_VERSIE . "'");
}

// ===================== INSTELLINGEN, MENU EN ARRANGEMENTEN =====================

function instellingen($vernieuw = false) {
    static $cache = null;
    if ($cache === null || $vernieuw) {
        $cache = [];
        foreach (alleRijen(q("SELECT sleutel, waarde FROM instellingen")) as $r) {
            $cache[$r['sleutel']] = $r['waarde'];
        }
    }
    return $cache;
}

function instelling($sleutel, $standaard = null) {
    $alle = instellingen();
    return $alle[$sleutel] ?? $standaard;
}

// Alle arrangementen, op naam: ['duur', 'drank', 'prijs' => ['volw','sen','kind'], 'omschrijving', 'volgorde']
function pakketten() {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (alleRijen(q("SELECT * FROM pakketten ORDER BY volgorde, naam")) as $p) {
            $cache[$p['naam']] = [
                'omschrijving' => $p['omschrijving'],
                'duur' => intval($p['duur']),
                'drank' => (bool)$p['drank'],
                'prijs' => [
                    'volw' => floatval($p['prijs_volw']),
                    'sen'  => floatval($p['prijs_sen']),
                    'kind' => floatval($p['prijs_kind']),
                ],
                'volgorde' => intval($p['volgorde']),
            ];
        }
    }
    return $cache;
}

function pakketInfo($naam) {
    $alle = pakketten();
    return $alle[$naam] ?? ['omschrijving' => '', 'duur' => 120, 'drank' => false,
                            'prijs' => ['volw' => 0, 'sen' => 0, 'kind' => 0], 'volgorde' => 0];
}

// Menu-items (standaard alleen de actieve), met allergenen als lijst
function menuItems($alleenActief = true) {
    $sql = "SELECT * FROM menu" . ($alleenActief ? " WHERE actief = 1" : "") . " ORDER BY FIELD(categorie, 'voorgerecht', 'hoofdgerecht', 'drankje', 'toetje'), volgorde, naam";
    $lijst = [];
    foreach (alleRijen(q($sql)) as $m) {
        $m['prijs'] = floatval($m['prijs']);
        $m['actief'] = intval($m['actief']);
        $m['volgorde'] = intval($m['volgorde']);
        $m['allergenen'] = array_values(array_filter(explode(',', $m['allergenen'])));
        $lijst[] = $m;
    }
    return $lijst;
}

// Actieve menu-items op naam (voor het controleren van bestellingen)
function menuOpNaam() {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (menuItems(true) as $m) $cache[$m['naam']] = $m;
    }
    return $cache;
}

// Prijzen van drankjes (alleen van toepassing bij arrangementen zonder drank)
function drankprijzen() {
    $prijzen = [];
    foreach (menuOpNaam() as $naam => $m) {
        if ($m['categorie'] === 'drankje' && $m['prijs'] > 0) $prijzen[$naam] = $m['prijs'];
    }
    return $prijzen;
}

// ===================== REKENING =====================

function personenVan($t) {
    return intval($t['volw'] ?? 0) + intval($t['sen'] ?? 0) + intval($t['kind'] ?? 0);
}

// Teksten op de rekening in drie talen (Nederlands is de standaard op het scherm)
const REKENING_TEKSTEN = [
    'nl' => ['volw' => 'Volwassene', 'sen' => 'Senior (65+)', 'kind' => 'Kind', 'drank' => 'Drankjes (niet inbegrepen)', 'verlenging' => 'Verlenging %d min (%d pers.)'],
    'en' => ['volw' => 'Adult', 'sen' => 'Senior (65+)', 'kind' => 'Child', 'drank' => 'Drinks (not included)', 'verlenging' => 'Extension %d min (%d pers.)'],
    'es' => ['volw' => 'Adulto', 'sen' => 'Mayor (65+)', 'kind' => 'Niño', 'drank' => 'Bebidas (no incluidas)', 'verlenging' => 'Ampliación %d min (%d pers.)'],
];

// Rekening van een tafel: arrangement per persoon + losse drankjes + verlengingen
function berekenRekening($t, $taal = 'nl') {
    $tk = REKENING_TEKSTEN[$taal] ?? REKENING_TEKSTEN['nl'];
    $info = pakketInfo($t['pakket']);
    $regels = [];
    foreach (['volw', 'sen', 'kind'] as $sleutel) {
        $n = intval($t[$sleutel] ?? 0);
        if ($n > 0) {
            $regels[] = ['omschrijving' => "{$n}× {$tk[$sleutel]}", 'bedrag' => round($n * $info['prijs'][$sleutel], 2)];
        }
    }
    $drank = floatval($t['drank_kosten'] ?? 0);
    if ($drank > 0) {
        $regels[] = ['omschrijving' => $tk['drank'], 'bedrag' => round($drank, 2)];
    }
    $verl = intval($t['verlengingen'] ?? 0);
    $personen = personenVan($t);
    if ($verl > 0 && $personen > 0) {
        $minuten = $verl * intval(instelling('verleng_minuten', 30));
        $regels[] = [
            'omschrijving' => sprintf($tk['verlenging'], $minuten, $personen),
            'bedrag' => round($verl * $personen * floatval(instelling('verleng_prijs_pp', 6)), 2),
        ];
    }
    $totaal = 0.0;
    foreach ($regels as $r) $totaal += $r['bedrag'];
    return ['regels' => $regels, 'totaal' => round($totaal, 2)];
}

// ===================== TAFELCODES =====================

// Geheime code per tafel, voor in de QR-code. Zonder deze code kan een klant
// niet bestellen voor (of kijken bij) een andere tafel.
function tafelCode($tafel) {
    return substr(hash_hmac('sha256', 'tafel-' . intval($tafel), instelling('tafel_geheim', 'lastapas')), 0, 8);
}

// ===================== BEVEILIGING VOOR FORMULIEREN =====================

function esc($tekst) {
    return htmlspecialchars((string)$tekst, ENT_QUOTES, 'UTF-8');
}

// CSRF-token: een geheime code in elk formulier, zodat een formulier alleen
// vanaf onze eigen pagina verstuurd kan worden (sessie moet actief zijn).
function csrfToken() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrfVeld() {
    return '<input type="hidden" name="csrf" value="' . esc(csrfToken()) . '">';
}

function csrfControle() {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    if (!$ok) {
        http_response_code(403);
        die('Ongeldig formulier (beveiligingscode klopt niet). Ga terug en probeer het opnieuw.');
    }
}

function vereisBaas() {
    if (($_SESSION['rol'] ?? '') !== 'baas') {
        header('Location: login.html');
        exit();
    }
}

// Duidelijke foutpagina voor de beheerpagina's als er iets misgaat met de database
function toonFoutPagina(Throwable $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Fout</title></head>'
       . '<body style="font-family: sans-serif; padding: 30px; background: #fdfbf7;">'
       . '<h2 style="color: #b33939;">Er ging iets mis</h2><p>' . esc($e->getMessage()) . '</p>'
       . '<p>Controleer of WampServer groen is en de database <strong>las_tapas_db</strong> bestaat.</p>'
       . '<a href="baas_paneel.php">Terug naar het beheerderspaneel</a></body></html>';
    exit();
}
