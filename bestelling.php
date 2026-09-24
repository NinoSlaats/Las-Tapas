<?php
/*
 * Las Tapas - bestelling.php
 * De centrale "API": alle schermen (klant, chef, serveerster) praten via dit bestand met de database.
 *
 * Wie mag wat?
 *  - Klanten (niet ingelogd, maar met een geldige tafelcode uit de QR-code):
 *    tafel bezetten, bestellen, tijd verlengen, betaalverzoek doen, eigen tafel bekijken.
 *  - Personeel (ingelogd als chef / serveerster / baas): alle overige acties.
 *
 * Prijzen, menu, arrangementen en instellingen staan in de database en zijn
 * aan te passen via het beheerderspaneel (Menu & prijzen).
 */

require_once __DIR__ . '/gedeeld.php';
require_once __DIR__ . '/bon_functies.php';   // bon per e-mail

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

function jsonOut($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
function stuur($data) {
    echo jsonOut($data);
    exit;
}
function fout($melding, $httpCode = 200) {
    http_response_code($httpCode);
    stuur(["succes" => false, "melding" => $melding]);
}

register_shutdown_function(function () {
    $f = error_get_last();
    if ($f && in_array($f['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        echo jsonOut(["succes" => false, "melding" => "Serverfout: " . $f['message']]);
    }
});
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Wie is er ingelogd? (klanten zijn nooit ingelogd)
session_start();
$medewerkerNaam = $_SESSION['gebruiker'] ?? '';
$rol = $_SESSION['rol'] ?? '';
session_write_close();
$isPersoneel = in_array($rol, ['chef', 'serveerster', 'baas'], true);

function vereisRol(array $rollen) {
    global $rol;
    if (!in_array($rol, $rollen, true)) {
        fout("Geen toegang: log in als medewerker om dit te doen.", 403);
    }
}

// Klanten moeten de juiste code uit de QR-code van hun tafel meesturen
function vereisTafelCode($tafel, $code) {
    global $isPersoneel;
    if ($isPersoneel) return;
    if ($tafel < 1 || !hash_equals(tafelCode($tafel), (string)$code)) {
        fout("Deze QR-code is ongeldig of verouderd. Vraag het personeel om hulp.", 403);
    }
}

// Hoort deze telefoon bij het huidige bezoek aan de tafel?
// (Personeel mag altijd; tafels van vóór deze update hebben nog geen sessiecode.)
function vereisSessie($t, $input) {
    global $isPersoneel;
    if ($isPersoneel || !$t || $t['sessie'] === '') return;
    if (!hash_equals($t['sessie'], (string)($input['sessie'] ?? ''))) {
        stuur(["succes" => false, "code" => "sessie_verlopen",
               "melding" => "Jullie bezoek aan deze tafel is afgesloten. Meld je opnieuw aan."]);
    }
}

// ===================== HULPFUNCTIES =====================

// "2x Pan con Tomate, 1x Cerveza San Miguel" -> [['aantal'=>2,'naam'=>'Pan con Tomate'], ...]
function parseItems($tekst) {
    $items = [];
    foreach (explode(',', $tekst) as $deel) {
        if (preg_match('/^\s*(\d+)\s*x\s+(.+?)\s*$/u', $deel, $m)) {
            $items[] = ['aantal' => max(0, intval($m[1])), 'naam' => $m[2]];
        }
    }
    return $items;
}

function categorieVan($naam) {
    $menu = menuOpNaam();
    return $menu[$naam]['categorie'] ?? '';
}

function berekenDrankKosten($items, $pakketNaam) {
    if (pakketInfo($pakketNaam)['drank']) return 0.0;
    $prijzen = drankprijzen();
    $totaal = 0.0;
    foreach ($items as $it) {
        if (isset($prijzen[$it['naam']])) $totaal += $it['aantal'] * $prijzen[$it['naam']];
    }
    return round($totaal, 2);
}

function zoekVoorraad($naam) {
    return q("SELECT id, naam, aantal FROM voorraad WHERE naam = ? OR naam LIKE ? ORDER BY (naam = ?) DESC LIMIT 1",
             "sss", [$naam, "%$naam%", $naam])->fetch_assoc();
}

function controleerVoorraad($items) {
    foreach ($items as $it) {
        $v = zoekVoorraad($it['naam']);
        if ($v && $it['aantal'] > intval($v['aantal'])) {
            return "Helaas, van '{$v['naam']}' zijn er nog maar {$v['aantal']} op voorraad (gevraagd: {$it['aantal']}).";
        }
    }
    return null;
}

// $richting = -1 (afboeken) of +1 (terugzetten)
function pasVoorraadAan($items, $richting) {
    foreach ($items as $it) {
        $v = zoekVoorraad($it['naam']);
        if ($v) {
            q("UPDATE voorraad SET aantal = GREATEST(0, aantal + ?) WHERE id = ?", "ii", [$richting * $it['aantal'], $v['id']]);
        }
    }
}

// Bestelde gerechten vastleggen voor statistieken en de ronde-limiet
function registreerVerkocht($bestellingId, $tafel, $items, $datum = null) {
    foreach ($items as $it) {
        q("INSERT INTO verkocht (bestelling_id, tafel, naam, categorie, aantal, datum) VALUES (?, ?, ?, ?, ?, COALESCE(?, NOW()))",
          "iissis", [$bestellingId, $tafel, $it['naam'], categorieVan($it['naam']), $it['aantal'], $datum]);
    }
}

function haalTafel($tafel) {
    return q("SELECT * FROM tafels WHERE tafel = ?", "i", [$tafel])->fetch_assoc();
}

// Hoeveel gerechten mag deze tafel nog bestellen in de huidige ronde?
// Drankjes tellen niet mee. Geeft null terug als de ronde-limiet uit staat.
function rondeInfo($t) {
    $maxPp = intval(instelling('ronde_max_pp', 0));
    $minuten = max(1, intval(instelling('ronde_minuten', 10)));
    $personen = personenVan($t);
    if ($maxPp <= 0 || $personen <= 0) return null;

    $limiet = $maxPp * $personen;
    $gebruikt = 0;
    $volgende = null;
    if ($t['start_tijd'] !== null) {
        $r = q("SELECT COALESCE(SUM(aantal), 0) AS n, MIN(datum) AS eerste FROM verkocht
                WHERE tafel = ? AND categorie <> 'drankje' AND datum >= ? AND datum > (NOW() - INTERVAL ? MINUTE)",
               "isi", [intval($t['tafel']), $t['start_tijd'], $minuten])->fetch_assoc();
        $gebruikt = intval($r['n']);
        if ($r['eerste']) $volgende = date('H:i', strtotime($r['eerste']) + $minuten * 60);
    }
    return [
        'max_pp' => $maxPp,
        'minuten' => $minuten,
        'limiet' => $limiet,
        'gebruikt' => $gebruikt,
        'over' => max(0, $limiet - $gebruikt),
        'volgende_ronde' => $gebruikt >= $limiet ? $volgende : null,
    ];
}

function verrijkTafel($t) {
    $t['rekening'] = berekenRekening($t);
    $t['drank_inbegrepen'] = pakketInfo($t['pakket'])['drank'];
    $t['seconden_over'] = $t['eind_tijd'] ? (strtotime($t['eind_tijd']) - time()) : null;
    $t['eind_tijd_kort'] = $t['eind_tijd'] ? date('H:i', strtotime($t['eind_tijd'])) : null;
    $t['laatste_activiteit_kort'] = $t['laatste_activiteit'] ? date('H:i', strtotime($t['laatste_activiteit'])) : null;
    $t['ronde'] = rondeInfo($t);
    $t['oproep_tijd_kort'] = !empty($t['oproep_tijd']) ? date('H:i', strtotime($t['oproep_tijd'])) : null;
    $t['oproep_minuten'] = !empty($t['oproep_tijd']) ? max(0, intdiv(time() - strtotime($t['oproep_tijd']), 60)) : null;
    return $t;
}

function verrijkBestelling($rij) {
    $items = parseItems($rij['gerecht']);
    $rij['voorraad_aantal'] = null;
    if ($items) {
        $v = zoekVoorraad($items[0]['naam']);
        if ($v) $rij['voorraad_aantal'] = intval($v['aantal']);
    }
    return $rij;
}

// Afgeronde tafel bewaren in het archief (voor omzet en statistieken)
function archiveer($t, $status, $door, $betaalmethode, $bonStatus = '') {
    $rek = berekenRekening($t);
    q("INSERT INTO archief (tafel, gast_naam, pakket, volw, sen, kind, verlengingen, drank_kosten, totaal,
                            betaalmethode, status, afgerond_door, start_tijd, afgerond_op, bon_keuze, bon_status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)",
      "issiiiiddssssss",
      [intval($t['tafel']), $t['gast_naam'], $t['pakket'], intval($t['volw']), intval($t['sen']), intval($t['kind']),
       intval($t['verlengingen']), floatval($t['drank_kosten']), $rek['totaal'],
       $betaalmethode, $status, $door, $t['start_tijd'], $t['bon_keuze'] ?? '', $bonStatus]);
}

// Tafel afsluiten: archiveren, bestellingen en tafel verwijderen
function sluitTafel($t, $status, $door, $betaalmethode, $bonStatus = '') {
    // Een tafel die nooit heeft besteld en vertrokken is, hoeft niet in de statistieken
    if ($status === 'betaald' || $t['start_tijd'] !== null) {
        archiveer($t, $status, $door, $betaalmethode, $bonStatus);
    }
    q("DELETE FROM bestellingen WHERE tafel = ?", "i", [intval($t['tafel'])]);
    q("DELETE FROM tafels WHERE tafel = ?", "i", [intval($t['tafel'])]);
}

// ===================== HOOFDPROGRAMMA =====================

try {
    db();

    // Bezorgde bestellingen blijven bewaard tot de tafel wordt afgesloten (dan worden ze verwijderd).
    // Het personeel ziet ze na 10 minuten niet meer (zie het opvragen van bestellingen hieronder).
    q("UPDATE bestellingen SET bezorgd_op = NOW() WHERE status = 'bezorgd' AND bezorgd_op IS NULL");

    // Achtergelaten tafels automatisch opruimen (geen activiteit in X uur)
    q("UPDATE tafels SET laatste_activiteit = NOW() WHERE laatste_activiteit IS NULL");
    $uren = max(1, intval(instelling('opruimen_na_uren', 4)));
    $verlaten = alleRijen(q("SELECT * FROM tafels WHERE status = 'bezet' AND laatste_activiteit < (NOW() - INTERVAL ? HOUR)", "i", [$uren]));
    foreach ($verlaten as $t) {
        sluitTafel($t, 'verlaten', 'Automatisch opgeruimd', '');
    }

    $methode = $_SERVER['REQUEST_METHOD'];

    // ===================== GET: gegevens opvragen =====================
    if ($methode === 'GET') {
        $view = $_GET['view'] ?? '';
        $tafelParam = isset($_GET['tafel']) ? intval($_GET['tafel']) : 0;
        $code = $_GET['code'] ?? '';

        // Menu, arrangementen en prijzen voor de klantpagina en de chef (openbaar)
        if ($view === 'config') {
            stuur([
                'pakketten' => pakketten(),
                'menu' => menuItems(true),
                'drankprijzen' => drankprijzen(),
                'verleng_minuten' => intval(instelling('verleng_minuten', 30)),
                'verleng_prijs_pp' => floatval(instelling('verleng_prijs_pp', 6)),
                'ronde_max_pp' => intval(instelling('ronde_max_pp', 0)),
                'ronde_minuten' => intval(instelling('ronde_minuten', 10)),
                'allergenen' => ALLERGENEN,
                'categorieen' => CATEGORIEEN,
            ]);
        }

        // Mag de gast van deze tafel nog een beoordeling geven? (alleen vlak na een echte betaling)
        if ($view === 'review_mogelijk') {
            vereisTafelCode($tafelParam, $code);
            $bezoek = q("SELECT id FROM archief WHERE tafel = ? AND status = 'betaald' AND beoordeling IS NULL
                         AND afgerond_op > (NOW() - INTERVAL 30 MINUTE) LIMIT 1", "i", [$tafelParam])->fetch_assoc();
            stuur(["mogelijk" => (bool)$bezoek]);
        }

        if (($_GET['type'] ?? '') === 'voorraad') {
            stuur(alleRijen(q("SELECT * FROM voorraad")));
        }

        // Tafels: personeel ziet alles, een klant alleen zijn eigen tafel (met geldige code)
        if ($view === 'tafels') {
            $tafels = [];
            if ($isPersoneel && $tafelParam === 0) {
                foreach (alleRijen(q("SELECT * FROM tafels")) as $t) $tafels[$t['tafel']] = verrijkTafel($t);
            } elseif ($tafelParam > 0) {
                vereisTafelCode($tafelParam, $code);
                $t = haalTafel($tafelParam);
                if ($t) $tafels[$t['tafel']] = verrijkTafel($t);
            }
            stuur($tafels);
        }

        // Bestellingen: personeel ziet alles, een klant alleen die van zijn eigen tafel
        $bestellingen = [];
        if ($isPersoneel && $tafelParam === 0) {
            // Personeel: bezorgde bestellingen ouder dan 10 minuten niet meer tonen
            $rijen = alleRijen(q("SELECT * FROM bestellingen
                                  WHERE NOT (status = 'bezorgd' AND bezorgd_op < (NOW() - INTERVAL 10 MINUTE))
                                  ORDER BY id DESC"));
        } elseif ($tafelParam > 0) {
            vereisTafelCode($tafelParam, $code);
            $rijen = alleRijen(q("SELECT * FROM bestellingen WHERE tafel = ? ORDER BY id DESC", "i", [$tafelParam]));
        } else {
            $rijen = [];
        }
        foreach ($rijen as $rij) $bestellingen[] = verrijkBestelling($rij);

        if (isset($_GET['client'])) {
            stuur(["bestellingen" => $bestellingen, "voorraad" => alleRijen(q("SELECT * FROM voorraad"))]);
        }
        stuur($bestellingen);
    }

    // ===================== POST: acties uitvoeren =====================
    if ($methode !== 'POST') fout("Onbekende aanvraag.");

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) fout("Geen JSON data ontvangen.");
    $actie = $input['actie'] ?? '';
    $code = $input['code'] ?? '';

    // ---------- Tafelstatus ----------
    if ($actie === 'update_tafel_status') {
        $tafel = intval($input['tafel'] ?? 0);
        $status = $input['status'] ?? '';
        if ($tafel < 1 || $tafel > 100) fout("Ongeldig tafelnummer.");

        $huidig = haalTafel($tafel);
        $huidigStatus = $huidig['status'] ?? 'vrij';

        if ($status === 'bezet') {
            vereisTafelCode($tafel, $code);
            if (in_array($huidigStatus, ['betalen', 'geaccepteerd'], true)) {
                fout("Er loopt al een betaling voor deze tafel.");
            }
            $gastNaam = mb_substr(trim($input['gast_naam'] ?? ''), 0, 100);
            $volw = max(0, min(30, intval($input['volw'] ?? 0)));
            $sen  = max(0, min(30, intval($input['sen'] ?? 0)));
            $kind = max(0, min(30, intval($input['kind'] ?? 0)));
            $gezelschap = "$volw volw, $sen sen, $kind kind";
            $pakket = $input['pakket'] ?? '';
            if (!isset(pakketten()[$pakket])) fout("Onbekend arrangement.");
            if ($volw + $sen + $kind < 1) fout("Geef minimaal 1 persoon op.");

            $mijnSessie = (string)($input['sessie'] ?? '');

            if (!$huidig) {
                // Nieuw bezoek: nieuwe sessiecode
                $sessie = bin2hex(random_bytes(12));
                q("INSERT INTO tafels (tafel, status, gast_naam, gezelschap, pakket, volw, sen, kind,
                                       start_tijd, eind_tijd, verlengingen, drank_kosten, betaalmethode, geaccepteerd_door, laatste_activiteit, sessie)
                   VALUES (?, 'bezet', ?, ?, ?, ?, ?, ?, NULL, NULL, 0, 0, '', '', NOW(), ?)",
                  "isssiiis", [$tafel, $gastNaam, $gezelschap, $pakket, $volw, $sen, $kind, $sessie]);
                stuur(["succes" => true, "sessie" => $sessie]);
            }

            // De tafel is al in gebruik door een ander bezoek: niet overschrijven, maar aanbieden om aan te sluiten
            if ($huidig['sessie'] !== '' && !hash_equals($huidig['sessie'], $mijnSessie)) {
                stuur(["succes" => false, "code" => "tafel_bezet",
                       "melding" => "Deze tafel is al in gebruik.",
                       "tafel" => ['gast_naam' => $huidig['gast_naam'], 'pakket' => $huidig['pakket'],
                                   'volw' => intval($huidig['volw']), 'sen' => intval($huidig['sen']), 'kind' => intval($huidig['kind'])]]);
            }

            $sessie = $huidig['sessie'] !== '' ? $huidig['sessie'] : bin2hex(random_bytes(12));
            if ($huidig['start_tijd'] === null) {
                // Nog niets besteld: gegevens (ook arrangement) mogen nog worden aangepast
                q("UPDATE tafels SET gast_naam=?, gezelschap=?, pakket=?, volw=?, sen=?, kind=?, laatste_activiteit=NOW(), sessie=? WHERE tafel=?",
                  "sssiiisi", [$gastNaam, $gezelschap, $pakket, $volw, $sen, $kind, $sessie, $tafel]);
            } else {
                q("UPDATE tafels SET sessie=? WHERE tafel=?", "si", [$sessie, $tafel]);
            }
            stuur(["succes" => true, "sessie" => $sessie]);
        }

        if ($status === 'betalen') {
            vereisTafelCode($tafel, $code);
            $betaalmethode = ($input['betaalmethode'] ?? '') === 'online' ? 'online' : 'contant';
            if ($huidigStatus === 'vrij') {
                stuur(["succes" => false, "code" => "sessie_verlopen", "melding" => "Jullie bezoek aan deze tafel is afgesloten. Meld je opnieuw aan."]);
            }
            vereisSessie($huidig, $input);
            if (in_array($huidigStatus, ['betalen', 'geaccepteerd'], true)) stuur(["succes" => true]);
            if ($huidigStatus !== 'bezet') fout("Deze tafel is niet in gebruik.");
            // Bon: geen, per e-mail of op papier
            $bonKeuze = in_array($input['bon_keuze'] ?? '', ['geen', 'email', 'papier'], true) ? $input['bon_keuze'] : 'geen';
            $bonEmail = trim((string)($input['bon_email'] ?? ''));
            $bonTaal = in_array($input['bon_taal'] ?? '', ['nl', 'en', 'es'], true) ? $input['bon_taal'] : 'nl';
            if ($bonKeuze === 'email' && !filter_var($bonEmail, FILTER_VALIDATE_EMAIL)) {
                fout("Vul een geldig e-mailadres in voor de bon.");
            }
            if ($bonKeuze !== 'email') $bonEmail = '';
            q("UPDATE tafels SET status='betalen', betaalmethode=?, geaccepteerd_door='', laatste_activiteit=NOW(),
                                 bon_keuze=?, bon_email=?, bon_taal=? WHERE tafel=?",
              "ssssi", [$betaalmethode, $bonKeuze, mb_substr($bonEmail, 0, 190), $bonTaal, $tafel]);
            stuur(["succes" => true]);
        }

        if ($status === 'geaccepteerd') {
            vereisRol(['serveerster', 'baas']);
            if ($huidigStatus === 'geaccepteerd') {
                fout("Dit betaalverzoek is al geaccepteerd door " . $huidig['geaccepteerd_door'] . ".");
            }
            if ($huidigStatus !== 'betalen') fout("Er is geen open betaalverzoek voor deze tafel.");
            q("UPDATE tafels SET status='geaccepteerd', geaccepteerd_door=? WHERE tafel=?",
              "si", [$medewerkerNaam ?: 'Een serveerster', $tafel]);
            stuur(["succes" => true]);
        }

        if ($status === 'vrij') {
            // Tafel afsluiten: na betaling, of omdat de gasten zonder betalen zijn vertrokken
            vereisRol(['serveerster', 'baas']);
            if ($huidig) {
                $reden = ($input['reden'] ?? '') === 'verlaten' ? 'verlaten' : 'betaald';
                $betaalmethode = '';
                $bon = null;
                $bonStatus = '';
                if ($reden === 'betaald') {
                    $betaalmethode = $huidig['betaalmethode']
                        ?: (($input['betaalmethode'] ?? '') === 'online' ? 'online' : 'contant');
                    $huidig['betaalmethode'] = $betaalmethode;
                    // Bon per e-mail? Dan nu versturen (de tafel wordt hoe dan ook afgesloten)
                    if ($huidig['bon_keuze'] === 'email' && $huidig['bon_email'] !== '') {
                        [$gelukt, $melding] = stuurBonPerMail($huidig);
                        $bonStatus = $gelukt ? 'verstuurd' : 'mislukt';
                        $bon = ['gelukt' => $gelukt, 'melding' => $gelukt ? "📧 Bon gemaild naar {$huidig['bon_email']}." : "⚠️ Bon kon niet worden gemaild: $melding"];
                    } elseif ($huidig['bon_keuze'] === 'papier') {
                        $bonStatus = 'papier';
                    }
                }
                sluitTafel($huidig, $reden, $medewerkerNaam ?: 'Personeel', $betaalmethode, $bonStatus);
                stuur(["succes" => true, "bon" => $bon]);
            }
            stuur(["succes" => true]);
        }

        fout("Onbekende tafelstatus.");
    }

    // ---------- Aansluiten bij het gezelschap aan deze tafel (tweede telefoon) ----------
    if ($actie === 'aansluiten') {
        $tafel = intval($input['tafel'] ?? 0);
        vereisTafelCode($tafel, $code);
        $t = haalTafel($tafel);
        if (!$t) fout("Deze tafel is niet (meer) in gebruik. Meld je opnieuw aan.");
        if ($t['sessie'] === '') {
            $t['sessie'] = bin2hex(random_bytes(12));
            q("UPDATE tafels SET sessie=? WHERE tafel=?", "si", [$t['sessie'], $tafel]);
        }
        stuur(["succes" => true, "sessie" => $t['sessie'],
               "gast_naam" => $t['gast_naam'], "pakket" => $t['pakket'],
               "volw" => intval($t['volw']), "sen" => intval($t['sen']), "kind" => intval($t['kind']),
               "status" => $t['status']]);
    }

    // ---------- Tijd verlengen (klant) ----------
    if ($actie === 'verleng') {
        $tafel = intval($input['tafel'] ?? 0);
        vereisTafelCode($tafel, $code);
        $t = haalTafel($tafel);
        if (!$t) stuur(["succes" => false, "code" => "sessie_verlopen", "melding" => "Jullie bezoek aan deze tafel is afgesloten. Meld je opnieuw aan."]);
        vereisSessie($t, $input);
        if ($t['status'] !== 'bezet') fout("Deze tafel is niet in gebruik.");
        if ($t['eind_tijd'] === null) fout("Jullie tijd start pas bij de eerste bestelling.");

        q("UPDATE tafels SET eind_tijd = DATE_ADD(GREATEST(eind_tijd, NOW()), INTERVAL ? MINUTE),
                             verlengingen = verlengingen + 1, laatste_activiteit = NOW() WHERE tafel = ?",
          "ii", [intval(instelling('verleng_minuten', 30)), $tafel]);
        stuur(["succes" => true, "tafel" => verrijkTafel(haalTafel($tafel))]);
    }

    // ---------- Beoordeling na het betalen (klant) ----------
    // Hoort bij het laatst betaalde bezoek aan deze tafel (maximaal 30 minuten geleden),
    // en kan per bezoek maar één keer worden gegeven.
    if ($actie === 'review') {
        $tafel = intval($input['tafel'] ?? 0);
        vereisTafelCode($tafel, $code);
        $sterren = intval($input['sterren'] ?? 0);
        if ($sterren < 1 || $sterren > 5) fout("Kies 1 tot 5 sterren.");
        $tekst = mb_substr(trim($input['tekst'] ?? ''), 0, 300);

        $bezoek = q("SELECT id FROM archief WHERE tafel = ? AND status = 'betaald' AND beoordeling IS NULL
                     AND afgerond_op > (NOW() - INTERVAL 30 MINUTE) ORDER BY afgerond_op DESC LIMIT 1",
                    "i", [$tafel])->fetch_assoc();
        if (!$bezoek) fout("Er is geen recent bezoek gevonden om te beoordelen.");
        q("UPDATE archief SET beoordeling = ?, review_tekst = ?, review_op = NOW() WHERE id = ?",
          "isi", [$sterren, $tekst, intval($bezoek['id'])]);
        stuur(["succes" => true]);
    }

    // ---------- Serveerster roepen (klant) ----------
    if ($actie === 'roep') {
        $tafel = intval($input['tafel'] ?? 0);
        vereisTafelCode($tafel, $code);
        $t = haalTafel($tafel);
        if (!$t) stuur(["succes" => false, "code" => "sessie_verlopen", "melding" => "Jullie bezoek aan deze tafel is afgesloten. Meld je opnieuw aan."]);
        vereisSessie($t, $input);
        if ($t['oproep_status'] !== '') fout("Er is al een serveerster geroepen voor deze tafel.");

        $redenen = ['vraag', 'probleem', 'allergie', 'anders'];
        $reden = in_array($input['reden'] ?? '', $redenen, true) ? $input['reden'] : 'vraag';
        $tekst = mb_substr(trim($input['tekst'] ?? ''), 0, 200);
        q("UPDATE tafels SET oproep_status='open', oproep_reden=?, oproep_tekst=?, oproep_tijd=NOW(), oproep_door='',
                             laatste_activiteit=NOW() WHERE tafel=?", "ssi", [$reden, $tekst, $tafel]);
        stuur(["succes" => true, "tafel" => verrijkTafel(haalTafel($tafel))]);
    }

    // ---------- Oproep annuleren (klant, alleen zolang nog niemand hem heeft geaccepteerd) ----------
    if ($actie === 'roep_annuleer') {
        $tafel = intval($input['tafel'] ?? 0);
        vereisTafelCode($tafel, $code);
        $t = haalTafel($tafel);
        vereisSessie($t, $input);
        if ($t && $t['oproep_status'] === 'geaccepteerd') {
            fout($t['oproep_door'] . " is al onderweg naar jullie tafel.");
        }
        q("UPDATE tafels SET oproep_status='', oproep_reden='', oproep_tekst='', oproep_tijd=NULL, oproep_door='' WHERE tafel=?", "i", [$tafel]);
        stuur(["succes" => true]);
    }

    // ---------- Oproep accepteren (serveerster) ----------
    if ($actie === 'roep_accepteer') {
        vereisRol(['serveerster', 'baas']);
        $tafel = intval($input['tafel'] ?? 0);
        $t = haalTafel($tafel);
        if (!$t || $t['oproep_status'] === '') fout("Deze oproep bestaat niet meer (misschien geannuleerd door de gast).");
        if ($t['oproep_status'] === 'geaccepteerd') fout("Deze oproep is al geaccepteerd door " . $t['oproep_door'] . ".");
        q("UPDATE tafels SET oproep_status='geaccepteerd', oproep_door=? WHERE tafel=?",
          "si", [$medewerkerNaam ?: 'Een serveerster', $tafel]);
        stuur(["succes" => true]);
    }

    // ---------- Oproep afgehandeld (serveerster) ----------
    if ($actie === 'roep_afgehandeld') {
        vereisRol(['serveerster', 'baas']);
        $tafel = intval($input['tafel'] ?? 0);
        q("UPDATE tafels SET oproep_status='', oproep_reden='', oproep_tekst='', oproep_tijd=NULL, oproep_door='' WHERE tafel=?", "i", [$tafel]);
        stuur(["succes" => true]);
    }

    // ---------- Status van een bestelling (personeel) ----------
    if ($actie === 'update_status') {
        vereisRol(['chef', 'serveerster', 'baas']);
        $id = intval($input['id'] ?? 0);
        $nieuw = $input['nieuwe_status'] ?? '';
        if (!in_array($nieuw, ['nieuw', 'bezig', 'klaar', 'onderweg', 'bezorgd'], true)) fout("Ongeldige status.");

        if ($nieuw === 'bezig') {
            $huidig = q("SELECT status, chef_naam FROM bestellingen WHERE id = ?", "i", [$id])->fetch_assoc();
            if ($huidig && $huidig['status'] !== 'nieuw' && $huidig['chef_naam'] !== '') {
                fout("Deze bestelling is al geaccepteerd door " . $huidig['chef_naam'] . ".");
            }
            q("UPDATE bestellingen SET status='bezig', chef_naam=?, bezorgd_op=NULL WHERE id=?", "si", [$medewerkerNaam, $id]);
        } elseif ($nieuw === 'onderweg') {
            q("UPDATE bestellingen SET status='onderweg', serveerster_naam=?, bezorgd_op=NULL WHERE id=?", "si", [$medewerkerNaam, $id]);
        } elseif ($nieuw === 'bezorgd') {
            q("UPDATE bestellingen SET status='bezorgd', bezorgd_op=NOW(),
                      serveerster_naam = IF(serveerster_naam = '', ?, serveerster_naam) WHERE id=?", "si", [$medewerkerNaam, $id]);
        } else {
            q("UPDATE bestellingen SET status=?, bezorgd_op=NULL,
                      chef_naam = IF(chef_naam = '', ?, chef_naam) WHERE id=?", "ssi", [$nieuw, $medewerkerNaam, $id]);
        }
        stuur(["succes" => true]);
    }

    // ---------- Gerecht aanpassen (chef) ----------
    if ($actie === 'bewerk_gerecht') {
        vereisRol(['chef', 'baas']);
        $id = intval($input['id'] ?? 0);
        $nieuwGerecht = trim($input['gerecht'] ?? '');
        $order = q("SELECT * FROM bestellingen WHERE id = ?", "i", [$id])->fetch_assoc();
        if (!$order) fout("Bestelling niet gevonden.");
        if (!in_array($order['status'], ['nieuw', 'bezig'], true)) fout("Deze bestelling is al klaar en kan niet meer worden aangepast.");
        $nieuweItems = parseItems($nieuwGerecht);
        if (!$nieuweItems) fout("Ongeldig gerecht.");
        $menu = menuItems(false);
        $menuNamen = array_column($menu, 'naam');
        foreach ($nieuweItems as $it) {
            if (!in_array($it['naam'], $menuNamen, true)) fout("'{$it['naam']}' staat niet op het menu.");
            if ($it['aantal'] < 1 || $it['aantal'] > 20) fout("Ongeldig aantal voor '{$it['naam']}'.");
        }

        $oudeItems = parseItems($order['gerecht']);
        pasVoorraadAan($oudeItems, +1);
        $probleem = controleerVoorraad($nieuweItems);
        if ($probleem) {
            pasVoorraadAan($oudeItems, -1);
            fout($probleem);
        }
        pasVoorraadAan($nieuweItems, -1);

        $nieuwBedrag = berekenDrankKosten($nieuweItems, $order['pakket']);
        $verschil = $nieuwBedrag - floatval($order['bedrag']);
        q("UPDATE bestellingen SET gerecht=?, bedrag=?, aangepast=1 WHERE id=?", "sdi", [$nieuwGerecht, $nieuwBedrag, $id]);
        q("UPDATE tafels SET drank_kosten = GREATEST(0, drank_kosten + ?) WHERE tafel=?", "di", [$verschil, intval($order['tafel'])]);

        // Statistieken bijwerken
        q("DELETE FROM verkocht WHERE bestelling_id = ?", "i", [$id]);
        registreerVerkocht($id, intval($order['tafel']), $nieuweItems, $order['besteld_op']);
        stuur(["succes" => true]);
    }

    // ---------- Bestelling verwijderen (chef) ----------
    if ($actie === 'verwijder') {
        vereisRol(['chef', 'baas']);
        $id = intval($input['id'] ?? 0);
        $order = q("SELECT * FROM bestellingen WHERE id = ?", "i", [$id])->fetch_assoc();
        if (!$order) fout("Bestelling niet gevonden.");

        pasVoorraadAan(parseItems($order['gerecht']), +1);
        q("UPDATE tafels SET drank_kosten = GREATEST(0, drank_kosten - ?) WHERE tafel=?", "di", [floatval($order['bedrag']), intval($order['tafel'])]);
        q("DELETE FROM verkocht WHERE bestelling_id = ?", "i", [$id]);
        q("DELETE FROM bestellingen WHERE id = ?", "i", [$id]);
        stuur(["succes" => true]);
    }

    // ---------- Nieuwe bestelling (klant) ----------
    if ($actie === '' || $actie === 'bestel') {
        $tafel = intval($input['tafel'] ?? 0);
        vereisTafelCode($tafel, $code);
        $gerecht = trim($input['gerecht'] ?? '');
        $opmerking = mb_substr(trim($input['opmerking'] ?? ''), 0, 200);
        if ($gerecht === '') fout("Je mandje is leeg.");

        $t = haalTafel($tafel);
        if (!$t) stuur(["succes" => false, "code" => "sessie_verlopen", "melding" => "Jullie bezoek aan deze tafel is afgesloten. Meld je opnieuw aan."]);
        vereisSessie($t, $input);
        if (in_array($t['status'], ['betalen', 'geaccepteerd'], true)) fout("Jullie zijn al aan het afrekenen.");
        if ($t['eind_tijd'] !== null && strtotime($t['eind_tijd']) <= time()) {
            fout("Jullie tijd zit erop. Verleng de tijd om nog iets te bestellen.");
        }

        $items = parseItems($gerecht);
        if (!$items) fout("Ongeldige bestelling.");

        // Alleen gerechten die echt op het (actieve) menu staan
        $menu = menuOpNaam();
        foreach ($items as $it) {
            if (!isset($menu[$it['naam']])) fout("'{$it['naam']}' staat niet (meer) op het menu.");
            if ($it['aantal'] < 1 || $it['aantal'] > 20) fout("Ongeldig aantal voor '{$it['naam']}'.");
        }

        // Ronde-limiet (drankjes tellen niet mee)
        $ronde = rondeInfo($t);
        if ($ronde) {
            $eten = 0;
            foreach ($items as $it) {
                if ($menu[$it['naam']]['categorie'] !== 'drankje') $eten += $it['aantal'];
            }
            if ($eten > $ronde['over']) {
                $wanneer = $ronde['volgende_ronde'] ? " De volgende ronde begint om {$ronde['volgende_ronde']}." : '';
                fout("Per ronde van {$ronde['minuten']} minuten kunnen jullie {$ronde['limiet']} gerechten bestellen "
                   . "({$ronde['max_pp']} per persoon). Deze ronde nog {$ronde['over']}.{$wanneer}");
            }
        }

        $probleem = controleerVoorraad($items);
        if ($probleem) fout($probleem);

        $pakket = $t['pakket'];
        $bedrag = berekenDrankKosten($items, $pakket);

        q("INSERT INTO bestellingen (tafel, pakket, gerecht, status, tijd, bedrag, opmerking, besteld_op)
           VALUES (?, ?, ?, 'nieuw', ?, ?, ?, NOW())",
          "isssds", [$tafel, $pakket, $gerecht, date("H:i"), $bedrag, $opmerking]);
        $bestellingId = db()->insert_id;
        pasVoorraadAan($items, -1);

        // Drankkosten optellen en bij de eerste bestelling de klok laten starten
        q("UPDATE tafels SET drank_kosten = drank_kosten + ?,
                  start_tijd = IFNULL(start_tijd, NOW()),
                  eind_tijd  = IFNULL(eind_tijd, DATE_ADD(NOW(), INTERVAL ? MINUTE)),
                  laatste_activiteit = NOW()
           WHERE tafel = ?", "dii", [$bedrag, pakketInfo($pakket)['duur'], $tafel]);

        registreerVerkocht($bestellingId, $tafel, $items);
        stuur(["succes" => true, "melding" => "Bestelling opgeslagen!", "tafel" => verrijkTafel(haalTafel($tafel))]);
    }

    fout("Onbekende actie.");

} catch (Throwable $e) {
    fout($e->getMessage());
}
