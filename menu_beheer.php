<?php
/*
 * Las Tapas - menu_beheer.php
 * De baas beheert hier het menu (met allergenen en drankprijzen), de arrangementen
 * en de algemene instellingen. Alles wordt direct gebruikt door de klantpagina.
 */
session_start();
require __DIR__ . '/gedeeld.php';
vereisBaas();
set_exception_handler('toonFoutPagina');
db();

// Unieke, korte code voor een nieuw menu-item (bijv. "Patatas Bravas" -> "patatas-bravas")
function maakMenuId($naam) {
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $naam);
    $s = strtolower($s !== false ? $s : $naam);
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    $s = substr($s !== '' ? $s : 'item', 0, 30);
    $basis = $s;
    $i = 2;
    while (q("SELECT 1 FROM menu WHERE id = ?", "s", [$s])->fetch_assoc()) {
        $s = $basis . '-' . $i++;
    }
    return $s;
}

// Na opslaan terug naar hetzelfde tabblad en hetzelfde gerecht/arrangement
function terug($ok, $sectie, $kies = '') {
    header('Location: menu_beheer.php?ok=' . urlencode($ok) . '&sectie=' . urlencode($sectie) . '&kies=' . urlencode($kies));
    exit();
}

$fout = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfControle();
    $actie = $_POST['actie'] ?? '';

    // ----- Menu-item opslaan of toevoegen -----
    if ($actie === 'menu_opslaan' || $actie === 'menu_toevoegen') {
        $naam = trim($_POST['naam'] ?? '');
        $omschrijving = mb_substr(trim($_POST['omschrijving'] ?? ''), 0, 255);
        $categorie = array_key_exists($_POST['categorie'] ?? '', CATEGORIEEN) ? $_POST['categorie'] : 'hoofdgerecht';
        $prijs = max(0, round(floatval(str_replace(',', '.', $_POST['prijs'] ?? '0')), 2));
        $allergenen = array_values(array_intersect(array_keys(ALLERGENEN), (array)($_POST['allergenen'] ?? [])));
        $volgorde = intval($_POST['volgorde'] ?? 0);
        $actief = !empty($_POST['actief']) ? 1 : 0;
        $id = $_POST['id'] ?? '';

        if ($naam === '' || mb_strlen($naam) > 100) {
            $fout = 'Geef het gerecht een naam (maximaal 100 tekens).';
        } elseif (strpos($naam, ',') !== false || preg_match('/^\d+\s*x\s/i', $naam)) {
            $fout = 'Een naam mag geen komma bevatten en niet beginnen met bijvoorbeeld "2x ".';
        } elseif (q("SELECT 1 FROM menu WHERE naam = ? AND id <> ?", "ss", [$naam, $id])->fetch_assoc()) {
            $fout = "Er staat al een gerecht met de naam '$naam' op het menu.";
        } elseif ($actie === 'menu_toevoegen') {
            $nieuwId = maakMenuId($naam);
            q("INSERT INTO menu (id, naam, omschrijving, categorie, prijs, allergenen, actief, volgorde) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
              "ssssdsii", [$nieuwId, $naam, $omschrijving, $categorie, $prijs, implode(',', $allergenen), $actief, $volgorde]);
            // Voorraadregel aanmaken (koppeling op naam)
            if (!q("SELECT 1 FROM voorraad WHERE naam = ?", "s", [$naam])->fetch_assoc()) {
                q("INSERT INTO voorraad (naam, categorie, aantal) VALUES (?, ?, 25)", "ss", [$naam, $categorie]);
            }
            terug('toegevoegd', 'menu', $nieuwId);
        } else {
            $oud = q("SELECT naam FROM menu WHERE id = ?", "s", [$id])->fetch_assoc();
            if (!$oud) {
                $fout = 'Dit gerecht bestaat niet meer.';
            } else {
                q("UPDATE menu SET naam=?, omschrijving=?, categorie=?, prijs=?, allergenen=?, actief=?, volgorde=? WHERE id=?",
                  "sssdsiis", [$naam, $omschrijving, $categorie, $prijs, implode(',', $allergenen), $actief, $volgorde, $id]);
                // Naam gewijzigd? Dan ook de voorraadregel hernoemen
                if ($oud['naam'] !== $naam) {
                    q("UPDATE voorraad SET naam = ? WHERE naam = ?", "ss", [$naam, $oud['naam']]);
                }
                terug('opgeslagen', 'menu', $id);
            }
        }
    }

    // ----- Menu-item verwijderen -----
    if ($actie === 'menu_verwijderen') {
        $id = $_POST['id'] ?? '';
        $oud = q("SELECT naam FROM menu WHERE id = ?", "s", [$id])->fetch_assoc();
        if ($oud) {
            q("DELETE FROM menu WHERE id = ?", "s", [$id]);
            q("DELETE FROM voorraad WHERE naam = ?", "s", [$oud['naam']]);
        }
        terug('verwijderd', 'menu');
    }

    // ----- Arrangement opslaan of toevoegen -----
    if ($actie === 'pakket_opslaan' || $actie === 'pakket_toevoegen') {
        $naam = trim($_POST['naam'] ?? '');
        $omschrijving = mb_substr(trim($_POST['omschrijving'] ?? ''), 0, 255);
        $duur = max(15, min(600, intval($_POST['duur'] ?? 120)));
        $drank = !empty($_POST['drank']) ? 1 : 0;
        $pv = max(0, round(floatval(str_replace(',', '.', $_POST['prijs_volw'] ?? '0')), 2));
        $ps = max(0, round(floatval(str_replace(',', '.', $_POST['prijs_sen'] ?? '0')), 2));
        $pk = max(0, round(floatval(str_replace(',', '.', $_POST['prijs_kind'] ?? '0')), 2));
        $volgorde = intval($_POST['volgorde'] ?? 0);

        if ($naam === '' || mb_strlen($naam) > 100) {
            $fout = 'Geef het arrangement een naam (maximaal 100 tekens).';
        } elseif ($actie === 'pakket_toevoegen') {
            if (q("SELECT 1 FROM pakketten WHERE naam = ?", "s", [$naam])->fetch_assoc()) {
                $fout = "Er bestaat al een arrangement met de naam '$naam'.";
            } else {
                q("INSERT INTO pakketten (naam, omschrijving, duur, drank, prijs_volw, prijs_sen, prijs_kind, volgorde) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                  "ssiidddi", [$naam, $omschrijving, $duur, $drank, $pv, $ps, $pk, $volgorde]);
                terug('toegevoegd', 'arrangementen', $naam);
            }
        } else {
            // De naam van een bestaand arrangement blijft vast (tafels verwijzen ernaar)
            q("UPDATE pakketten SET omschrijving=?, duur=?, drank=?, prijs_volw=?, prijs_sen=?, prijs_kind=?, volgorde=? WHERE naam=?",
              "siidddis", [$omschrijving, $duur, $drank, $pv, $ps, $pk, $volgorde, $naam]);
            terug('opgeslagen', 'arrangementen', $naam);
        }
    }

    // ----- Arrangement verwijderen -----
    if ($actie === 'pakket_verwijderen') {
        $naam = $_POST['naam'] ?? '';
        if (q("SELECT 1 FROM tafels WHERE pakket = ?", "s", [$naam])->fetch_assoc()) {
            $fout = "Het arrangement '$naam' is nu in gebruik bij een tafel en kan daarom niet worden verwijderd.";
        } else {
            q("DELETE FROM pakketten WHERE naam = ?", "s", [$naam]);
            terug('verwijderd', 'arrangementen');
        }
    }

    // ----- Instellingen -----
    if ($actie === 'instellingen_opslaan') {
        $nieuw = [
            'verleng_minuten'  => (string)max(5, min(240, intval($_POST['verleng_minuten'] ?? 30))),
            'verleng_prijs_pp' => number_format(max(0, floatval(str_replace(',', '.', $_POST['verleng_prijs_pp'] ?? '6'))), 2, '.', ''),
            'ronde_max_pp'     => (string)max(0, min(20, intval($_POST['ronde_max_pp'] ?? 3))),
            'ronde_minuten'    => (string)max(1, min(120, intval($_POST['ronde_minuten'] ?? 10))),
            'opruimen_na_uren' => (string)max(1, min(24, intval($_POST['opruimen_na_uren'] ?? 4))),
        ];
        foreach ($nieuw as $sleutel => $waarde) {
            q("INSERT INTO instellingen (sleutel, waarde) VALUES (?, ?) ON DUPLICATE KEY UPDATE waarde = ?", "sss", [$sleutel, $waarde, $waarde]);
        }
        terug('opgeslagen', 'instellingen');
    }
}

$okTeksten = ['toegevoegd' => 'Toegevoegd.', 'opgeslagen' => 'Opgeslagen.', 'verwijderd' => 'Verwijderd.'];
$melding = $fout ? ['fout', $fout] : (isset($_GET['ok'], $okTeksten[$_GET['ok']]) ? ['ok', $okTeksten[$_GET['ok']]] : null);

$menu = menuItems(false);

$openSectie = in_array($_GET['sectie'] ?? '', ['menu', 'arrangementen', 'instellingen'], true) ? $_GET['sectie'] : 'menu';
$openKies = $_GET['kies'] ?? '';
if ($fout) {
    $a = $_POST['actie'] ?? '';
    if (strpos($a, 'menu_') === 0) { $openSectie = 'menu'; $openKies = $a === 'menu_toevoegen' ? 'nieuw' : ($_POST['id'] ?? ''); }
    if (strpos($a, 'pakket_') === 0) { $openSectie = 'arrangementen'; $openKies = $a === 'pakket_toevoegen' ? 'nieuw' : ($_POST['naam'] ?? ''); }
}
$alleInstellingen = instellingen(true);

// Eén formulier voor een menu-item (bestaand of nieuw)
function menuFormulier($m, $nieuw = false) {
    $id = $nieuw ? 'nieuw' : $m['id'];
    ob_start(); ?>
    <div class="item-kaart menu-paneel <?php echo (!$nieuw && !$m['actief']) ? 'inactief' : ''; ?>" id="item-<?php echo esc($id); ?>"
         data-id="<?php echo esc($id); ?>" data-cat="<?php echo $nieuw ? '*' : esc($m['categorie']); ?>"
         data-naam="<?php echo esc($nieuw ? '➕ Nieuw gerecht toevoegen' : $m['naam'] . ($m['actief'] ? '' : ' (verborgen)')); ?>" hidden>
        <form method="POST">
            <?php echo csrfVeld(); ?>
            <input type="hidden" name="actie" value="<?php echo $nieuw ? 'menu_toevoegen' : 'menu_opslaan'; ?>">
            <?php if (!$nieuw): ?><input type="hidden" name="id" value="<?php echo esc($m['id']); ?>"><?php endif; ?>
            <div class="form-grid">
                <div><label>Naam</label><input type="text" name="naam" required maxlength="100" value="<?php echo esc($m['naam']); ?>"></div>
                <div><label>Categorie</label>
                    <select name="categorie">
                        <?php foreach (CATEGORIEEN as $k => $label): ?>
                            <option value="<?php echo $k; ?>" <?php echo $m['categorie'] === $k ? 'selected' : ''; ?>><?php echo esc($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="breed"><label>Omschrijving</label><input type="text" name="omschrijving" maxlength="255" value="<?php echo esc($m['omschrijving']); ?>"></div>
                <div><label>Prijs (€) <small>- alleen voor drankjes bij een arrangement zonder drank</small></label>
                    <input type="number" name="prijs" step="0.01" min="0" value="<?php echo number_format((float)$m['prijs'], 2, '.', ''); ?>"></div>
                <div><label>Volgorde <small>(lager = hoger in de lijst)</small></label>
                    <input type="number" name="volgorde" value="<?php echo intval($m['volgorde']); ?>"></div>
            </div>
            <p class="subkop">Allergenen</p>
            <div class="allergenen-grid">
                <?php foreach (ALLERGENEN as $k => $a): ?>
                    <label class="vinkje"><input type="checkbox" name="allergenen[]" value="<?php echo $k; ?>" <?php echo in_array($k, $m['allergenen'], true) ? 'checked' : ''; ?>> <?php echo $a['icoon'] . ' ' . esc($a['label']); ?></label>
                <?php endforeach; ?>
            </div>
            <label class="vinkje actief-vinkje"><input type="checkbox" name="actief" value="1" <?php echo $m['actief'] ? 'checked' : ''; ?>> Zichtbaar op het menu</label>
            <div class="item-knoppen">
                <button type="submit"><?php echo $nieuw ? 'Toevoegen aan menu' : 'Opslaan'; ?></button>
            </div>
        </form>
        <?php if (!$nieuw): ?>
            <form method="POST" class="verwijder-form" onsubmit="return confirm('<?php echo esc(addslashes($m['naam'])); ?> definitief verwijderen? Tip: haal liever het vinkje Zichtbaar weg als het tijdelijk op is.');">
                <?php echo csrfVeld(); ?>
                <input type="hidden" name="actie" value="menu_verwijderen">
                <input type="hidden" name="id" value="<?php echo esc($m['id']); ?>">
                <button type="submit" class="btn-verwijder">Verwijderen</button>
            </form>
        <?php endif; ?>
    </div>
    <?php return ob_get_clean();
}

$paginaTitel = 'Menu & prijzen';
$actievePagina = 'menu_beheer.php';
$extraStijl = '
    .item-kaart { background: var(--vlak); border: 1px solid var(--lijn); border-radius: 10px; padding: 16px; margin-bottom: 16px; position: relative; }
    .item-kaart.inactief { opacity: 0.6; }
    .item-kaart .breed { grid-column: 1 / -1; }
    .subkop { font-weight: bold; font-size: 0.9rem; margin: 14px 0 6px 0; }
    .allergenen-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 6px; }
    .vinkje { font-weight: normal; display: flex; align-items: center; gap: 6px; font-size: 0.9rem; }
    .vinkje input { width: 18px; height: 18px; margin: 0; }
    .actief-vinkje { margin-top: 12px; font-weight: bold; }
    .item-knoppen { display: flex; gap: 10px; }
    .verwijder-form { position: absolute; right: 16px; bottom: 16px; margin: 0; }
    .sectie-tabs { display: flex; gap: 0; border-bottom: 2px solid var(--lijn); margin-bottom: 20px; flex-wrap: wrap; }
    .sectie-tabs button { background: none; color: var(--grijs); border-radius: 0; margin: 0; padding: 12px 18px; border-bottom: 3px solid transparent; margin-bottom: -2px; }
    .sectie-tabs button:hover { background: var(--vlak); }
    .sectie-tabs button.actief { color: var(--rood); border-bottom-color: var(--rood); }
    .cat-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
    .cat-tabs button { margin: 0; background: #f0eae1; color: var(--grijs); border-radius: 20px; padding: 9px 16px; }
    .cat-tabs button.actief { background: var(--rood); color: white; }
    .kies-rij { margin-bottom: 15px; max-width: 420px; }
    .kies-rij select { font-weight: bold; }
    @media (max-width: 700px) {
        .verwijder-form { position: static; margin-top: 10px; }
        .verwijder-form .btn-verwijder, .item-knoppen button { width: 100%; padding: 12px; }
    }
';
include __DIR__ . '/beheer_kop.php';
?>

    <?php if ($melding): ?>
        <div class="melding <?php echo $melding[0]; ?>"><?php echo esc($melding[1]); ?></div>
    <?php endif; ?>

    <!-- Hoofdtabbladen -->
    <div class="sectie-tabs">
        <button type="button" data-sectie="menu" onclick="toonSectie('menu')">🍽️ Menu</button>
        <button type="button" data-sectie="arrangementen" onclick="toonSectie('arrangementen')">📦 Arrangementen</button>
        <button type="button" data-sectie="instellingen" onclick="toonSectie('instellingen')">⚙️ Instellingen</button>
    </div>

    <!-- ===== MENU ===== -->
    <section class="sectie" id="sectie-menu" hidden>
        <div class="cat-tabs">
            <?php foreach (CATEGORIEEN as $cat => $catLabel): ?>
                <button type="button" data-cat="<?php echo $cat; ?>" onclick="toonCategorie('<?php echo $cat; ?>')"><?php echo esc($catLabel); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="kies-rij">
            <label for="gerecht-kies">Kies een gerecht</label>
            <select id="gerecht-kies" onchange="toonGerecht(this.value)"></select>
        </div>
        <div id="menu-panelen">
            <?php foreach ($menu as $m) echo menuFormulier($m); ?>
            <?php echo menuFormulier(['naam' => '', 'omschrijving' => '', 'categorie' => 'hoofdgerecht', 'prijs' => 0, 'allergenen' => [], 'actief' => 1, 'volgorde' => 100], true); ?>
        </div>
        <p class="uitleg">Wijzigingen zijn direct zichtbaar voor klanten. Is iets tijdelijk op? Haal dan het vinkje "Zichtbaar op het menu" weg.</p>
    </section>

    <!-- ===== ARRANGEMENTEN ===== -->
    <section class="sectie" id="sectie-arrangementen" hidden>
        <div class="kies-rij">
            <label for="pakket-kies">Kies een arrangement</label>
            <select id="pakket-kies" onchange="toonPakket(this.value)">
                <?php foreach (pakketten() as $naam => $p): ?>
                    <option value="<?php echo esc($naam); ?>"><?php echo esc($naam); ?></option>
                <?php endforeach; ?>
                <option value="nieuw">➕ Nieuw arrangement toevoegen</option>
            </select>
        </div>

        <?php foreach (pakketten() as $naam => $p): ?>
        <div class="item-kaart pakket-paneel" data-id="<?php echo esc($naam); ?>" hidden>
            <form method="POST">
                <?php echo csrfVeld(); ?>
                <input type="hidden" name="actie" value="pakket_opslaan">
                <input type="hidden" name="naam" value="<?php echo esc($naam); ?>">
                <h3 style="margin-top: 0;"><?php echo esc($naam); ?></h3>
                <div class="form-grid">
                    <div class="breed"><label>Omschrijving</label><input type="text" name="omschrijving" value="<?php echo esc($p['omschrijving']); ?>"></div>
                    <div><label>Duur (minuten)</label><input type="number" name="duur" min="15" max="600" value="<?php echo $p['duur']; ?>"></div>
                    <div><label>Volgorde</label><input type="number" name="volgorde" value="<?php echo $p['volgorde']; ?>"></div>
                    <div><label>Prijs volwassene (€)</label><input type="number" step="0.01" min="0" name="prijs_volw" value="<?php echo number_format($p['prijs']['volw'], 2, '.', ''); ?>"></div>
                    <div><label>Prijs senior 65+ (€)</label><input type="number" step="0.01" min="0" name="prijs_sen" value="<?php echo number_format($p['prijs']['sen'], 2, '.', ''); ?>"></div>
                    <div><label>Prijs kind (€)</label><input type="number" step="0.01" min="0" name="prijs_kind" value="<?php echo number_format($p['prijs']['kind'], 2, '.', ''); ?>"></div>
                </div>
                <label class="vinkje actief-vinkje"><input type="checkbox" name="drank" value="1" <?php echo $p['drank'] ? 'checked' : ''; ?>> Drankjes inbegrepen</label>
                <div class="item-knoppen"><button type="submit">Opslaan</button></div>
            </form>
            <form method="POST" class="verwijder-form" onsubmit="return confirm('Arrangement definitief verwijderen?');">
                <?php echo csrfVeld(); ?>
                <input type="hidden" name="actie" value="pakket_verwijderen">
                <input type="hidden" name="naam" value="<?php echo esc($naam); ?>">
                <button type="submit" class="btn-verwijder">Verwijderen</button>
            </form>
        </div>
        <?php endforeach; ?>

        <div class="item-kaart pakket-paneel" data-id="nieuw" hidden>
            <form method="POST">
                <?php echo csrfVeld(); ?>
                <input type="hidden" name="actie" value="pakket_toevoegen">
                <h3 style="margin-top: 0;">Nieuw arrangement</h3>
                <div class="form-grid">
                    <div><label>Naam</label><input type="text" name="naam" required maxlength="100" placeholder="Bijv. Lunch Tapas (1u)"></div>
                    <div><label>Duur (minuten)</label><input type="number" name="duur" min="15" max="600" value="120"></div>
                    <div class="breed"><label>Omschrijving</label><input type="text" name="omschrijving"></div>
                    <div><label>Prijs volwassene (€)</label><input type="number" step="0.01" min="0" name="prijs_volw" value="0.00"></div>
                    <div><label>Prijs senior 65+ (€)</label><input type="number" step="0.01" min="0" name="prijs_sen" value="0.00"></div>
                    <div><label>Prijs kind (€)</label><input type="number" step="0.01" min="0" name="prijs_kind" value="0.00"></div>
                    <div><label>Volgorde</label><input type="number" name="volgorde" value="10"></div>
                </div>
                <label class="vinkje actief-vinkje"><input type="checkbox" name="drank" value="1"> Drankjes inbegrepen</label>
                <div class="item-knoppen"><button type="submit">Arrangement toevoegen</button></div>
            </form>
        </div>
        <p class="uitleg">De naam van een bestaand arrangement kan niet worden gewijzigd, omdat tafels ernaar verwijzen.</p>
    </section>

    <!-- ===== INSTELLINGEN ===== -->
    <section class="sectie" id="sectie-instellingen" hidden>
        <div class="item-kaart">
            <form method="POST">
                <?php echo csrfVeld(); ?>
                <input type="hidden" name="actie" value="instellingen_opslaan">
                <h3 style="margin-top: 0;">Tijd verlengen</h3>
                <div class="form-grid">
                    <div><label>Aantal minuten per keer</label>
                        <input type="number" name="verleng_minuten" min="5" max="240" value="<?php echo esc($alleInstellingen['verleng_minuten'] ?? 30); ?>"></div>
                    <div><label>Prijs per persoon (€)</label>
                        <input type="number" name="verleng_prijs_pp" step="0.01" min="0" value="<?php echo esc($alleInstellingen['verleng_prijs_pp'] ?? '6.00'); ?>"></div>
                </div>
                <h3>Ronde-limiet</h3>
                <div class="form-grid">
                    <div><label>Max. gerechten per persoon <small>(0 = geen limiet)</small></label>
                        <input type="number" name="ronde_max_pp" min="0" max="20" value="<?php echo esc($alleInstellingen['ronde_max_pp'] ?? 3); ?>"></div>
                    <div><label>Duur van een ronde (minuten)</label>
                        <input type="number" name="ronde_minuten" min="1" max="120" value="<?php echo esc($alleInstellingen['ronde_minuten'] ?? 10); ?>"></div>
                </div>
                <p class="uitleg">Drankjes tellen niet mee voor de ronde-limiet.</p>
                <h3>Achtergelaten tafels</h3>
                <div class="form-grid">
                    <div><label>Automatisch opruimen na ... uur zonder activiteit</label>
                        <input type="number" name="opruimen_na_uren" min="1" max="24" value="<?php echo esc($alleInstellingen['opruimen_na_uren'] ?? 4); ?>"></div>
                </div>
                <div class="item-knoppen"><button type="submit">Instellingen opslaan</button></div>
            </form>
        </div>
    </section>

</div>

<script>
    // Welk tabblad en welk gerecht/arrangement moet open staan? (na opslaan: hetzelfde als net)
    const START = <?php echo json_encode(['sectie' => $openSectie, 'kies' => (string)$openKies], JSON_UNESCAPED_UNICODE); ?>;
    let huidigeCategorie = 'voorgerecht';

    function toonSectie(sectie) {
        document.querySelectorAll('.sectie').forEach(el => el.hidden = el.id !== 'sectie-' + sectie);
        document.querySelectorAll('.sectie-tabs button').forEach(b => b.classList.toggle('actief', b.dataset.sectie === sectie));
    }

    // Categorie kiezen: keuzelijst vullen met de gerechten uit die categorie
    function toonCategorie(cat, kiesId) {
        huidigeCategorie = cat;
        document.querySelectorAll('.cat-tabs button').forEach(b => b.classList.toggle('actief', b.dataset.cat === cat));

        const select = document.getElementById('gerecht-kies');
        select.innerHTML = '';
        document.querySelectorAll('.menu-paneel').forEach(p => {
            if (p.dataset.cat === cat) select.add(new Option(p.dataset.naam, p.dataset.id));
        });
        select.add(new Option('➕ Nieuw gerecht toevoegen', 'nieuw'));

        const bestaat = [...select.options].some(o => o.value === kiesId);
        select.value = bestaat ? kiesId : select.options[0].value;
        toonGerecht(select.value);
    }

    function toonGerecht(id) {
        document.querySelectorAll('.menu-paneel').forEach(p => p.hidden = p.dataset.id !== id);
        // Nieuw gerecht: categorie alvast op de gekozen categorie zetten
        if (id === 'nieuw') {
            const catSelect = document.querySelector('#item-nieuw select[name="categorie"]');
            if (catSelect) catSelect.value = huidigeCategorie;
        }
    }

    function toonPakket(id) {
        document.querySelectorAll('.pakket-paneel').forEach(p => p.hidden = p.dataset.id !== id);
    }

    // Startpositie
    toonSectie(START.sectie);
    let startCat = 'voorgerecht';
    if (START.sectie === 'menu' && START.kies) {
        const paneel = document.querySelector(`.menu-paneel[data-id="${CSS.escape(START.kies)}"]`);
        if (paneel && paneel.dataset.cat !== '*') startCat = paneel.dataset.cat;
    }
    toonCategorie(startCat, START.sectie === 'menu' ? START.kies : '');

    const pakketKies = document.getElementById('pakket-kies');
    if (START.sectie === 'arrangementen' && [...pakketKies.options].some(o => o.value === START.kies)) {
        pakketKies.value = START.kies;
    }
    toonPakket(pakketKies.value);
</script>
</body>
</html>
