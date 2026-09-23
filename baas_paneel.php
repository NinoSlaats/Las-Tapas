<?php
/*
 * Las Tapas - baas_paneel.php
 * Medewerkers beheren (alleen voor de baas).
 * Alle formulieren hebben een beveiligingscode (CSRF-token), en verwijderen gaat via POST.
 */
session_start();
require __DIR__ . '/gedeeld.php';
vereisBaas();
set_exception_handler('toonFoutPagina');
db();

$geldigeRollen = ['chef', 'serveerster', 'baas'];
$melding = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfControle();
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'voeg_toe' || $actie === 'bewerk') {
        $naam = trim($_POST['naam'] ?? '');
        $gebruikersnaam = trim($_POST['gebruikersnaam'] ?? '');
        $rol = in_array($_POST['rol'] ?? '', $geldigeRollen, true) ? $_POST['rol'] : 'chef';
        $wachtwoord = $_POST['wachtwoord'] ?? '';

        if ($naam === '' || $gebruikersnaam === '') {
            $melding = ['fout', 'Vul een naam en gebruikersnaam in.'];
        } else {
            $id = intval($_POST['id'] ?? 0);
            $bestaat = q("SELECT id FROM chefs WHERE gebruikersnaam = ? AND id <> ?", "si", [$gebruikersnaam, $id])->fetch_assoc();
            if ($bestaat) {
                $melding = ['fout', "De gebruikersnaam '$gebruikersnaam' is al in gebruik."];
            } elseif ($actie === 'voeg_toe') {
                if (strlen($wachtwoord) < 4) {
                    $melding = ['fout', 'Kies een wachtwoord van minimaal 4 tekens.'];
                } else {
                    q("INSERT INTO chefs (naam, gebruikersnaam, wachtwoord, rol) VALUES (?, ?, ?, ?)",
                      "ssss", [$naam, $gebruikersnaam, password_hash($wachtwoord, PASSWORD_DEFAULT), $rol]);
                    header("Location: baas_paneel.php?ok=toegevoegd");
                    exit();
                }
            } else {
                // Jezelf geen andere rol geven (anders sluit je jezelf buiten)
                if ($id === intval($_SESSION['user_id'])) $rol = 'baas';
                if ($wachtwoord !== '') {
                    q("UPDATE chefs SET naam = ?, gebruikersnaam = ?, wachtwoord = ?, rol = ? WHERE id = ?",
                      "ssssi", [$naam, $gebruikersnaam, password_hash($wachtwoord, PASSWORD_DEFAULT), $rol, $id]);
                } else {
                    q("UPDATE chefs SET naam = ?, gebruikersnaam = ?, rol = ? WHERE id = ?",
                      "sssi", [$naam, $gebruikersnaam, $rol, $id]);
                }
                if ($id === intval($_SESSION['user_id'])) $_SESSION['gebruiker'] = $naam;
                header("Location: baas_paneel.php?ok=opgeslagen");
                exit();
            }
        }
    }

    if ($actie === 'verwijder') {
        $id = intval($_POST['id'] ?? 0);
        if ($id !== intval($_SESSION['user_id'])) {
            q("DELETE FROM chefs WHERE id = ?", "i", [$id]);
        }
        header("Location: baas_paneel.php?ok=verwijderd");
        exit();
    }
}

$okTeksten = ['toegevoegd' => 'Medewerker toegevoegd.', 'opgeslagen' => 'Wijzigingen opgeslagen.', 'verwijderd' => 'Medewerker verwijderd.'];
if (!$melding && isset($_GET['ok'], $okTeksten[$_GET['ok']])) $melding = ['ok', $okTeksten[$_GET['ok']]];

$medewerkers = alleRijen(q("SELECT id, naam, gebruikersnaam, rol FROM chefs ORDER BY rol, naam"));
$bewerkData = null;
if (isset($_GET['bewerk_id'])) {
    $bewerkData = q("SELECT id, naam, gebruikersnaam, rol FROM chefs WHERE id = ?", "i", [intval($_GET['bewerk_id'])])->fetch_assoc();
}

$paginaTitel = 'Medewerkers';
$actievePagina = 'baas_paneel.php';
include __DIR__ . '/beheer_kop.php';
?>

    <?php if ($melding): ?>
        <div class="melding <?php echo $melding[0]; ?>"><?php echo esc($melding[1]); ?></div>
    <?php endif; ?>

    <h2 style="margin-top: 0;">Medewerkers Overzicht</h2>
    <div class="tabel-scroll">
    <table class="kaarten">
        <thead>
            <tr><th>ID</th><th>Naam</th><th>Gebruikersnaam</th><th>Rol</th><th>Acties</th></tr>
        </thead>
        <tbody>
            <?php foreach ($medewerkers as $m): ?>
            <tr>
                <td data-label="ID"><?php echo intval($m['id']); ?></td>
                <td data-label="Naam" class="cel-naam"><?php echo esc($m['naam']); ?></td>
                <td data-label="Gebruikersnaam"><?php echo esc($m['gebruikersnaam']); ?></td>
                <td data-label="Rol"><strong><?php echo esc(strtoupper($m['rol'])); ?></strong></td>
                <td class="cel-acties">
                    <a href="baas_paneel.php?bewerk_id=<?php echo intval($m['id']); ?>" class="btn-bewerk">Aanpassen</a>
                    <?php if (intval($m['id']) !== intval($_SESSION['user_id'])): ?>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Weet je zeker dat je <?php echo esc(addslashes($m['naam'])); ?> wilt verwijderen?');">
                            <?php echo csrfVeld(); ?>
                            <input type="hidden" name="actie" value="verwijder">
                            <input type="hidden" name="id" value="<?php echo intval($m['id']); ?>">
                            <button type="submit" class="btn-verwijder">Verwijderen</button>
                        </form>
                    <?php else: ?>
                        <small style="color: var(--grijs);">(Jijzelf)</small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <div class="form-box">
        <?php $bewerken = (bool)$bewerkData; ?>
        <h3 style="margin-top: 0;"><?php echo $bewerken ? 'Medewerker aanpassen: ' . esc($bewerkData['naam']) : 'Nieuwe medewerker toevoegen'; ?></h3>
        <form action="baas_paneel.php" method="POST">
            <?php echo csrfVeld(); ?>
            <input type="hidden" name="actie" value="<?php echo $bewerken ? 'bewerk' : 'voeg_toe'; ?>">
            <?php if ($bewerken): ?><input type="hidden" name="id" value="<?php echo intval($bewerkData['id']); ?>"><?php endif; ?>
            <div class="form-grid">
                <div>
                    <label>Volledige naam</label>
                    <input type="text" name="naam" required value="<?php echo $bewerken ? esc($bewerkData['naam']) : ''; ?>" placeholder="Bijv. Carlos de Kok">
                </div>
                <div>
                    <label>Gebruikersnaam (voor inlog)</label>
                    <input type="text" name="gebruikersnaam" required value="<?php echo $bewerken ? esc($bewerkData['gebruikersnaam']) : ''; ?>" placeholder="Bijv. carlos">
                </div>
                <div>
                    <label><?php echo $bewerken ? 'Nieuw wachtwoord <small>(leeg laten om niet te wijzigen)</small>' : 'Wachtwoord'; ?></label>
                    <input type="password" name="wachtwoord" <?php echo $bewerken ? '' : 'required minlength="4"'; ?> placeholder="****">
                </div>
                <div>
                    <label>Rol</label>
                    <select name="rol">
                        <?php foreach (['chef' => 'Chef (Keuken)', 'serveerster' => 'Serveerster (Bediening)', 'baas' => 'Baas (Eigenaar / Beheer)'] as $waarde => $label): ?>
                            <option value="<?php echo $waarde; ?>" <?php echo ($bewerken && $bewerkData['rol'] === $waarde) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" <?php echo $bewerken ? 'style="background: #f39c12;"' : ''; ?>><?php echo $bewerken ? 'Wijzigingen opslaan' : 'Medewerker opslaan'; ?></button>
            <?php if ($bewerken): ?><a href="baas_paneel.php" class="btn-annuleer">Annuleren</a><?php endif; ?>
        </form>
    </div>

</div>
</body>
</html>
