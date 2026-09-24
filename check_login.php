<?php
/*
 * Las Tapas - check_login.php
 * Controleert gebruikersnaam en wachtwoord.
 * Na 5 foute pogingen binnen 15 minuten wordt die gebruikersnaam tijdelijk geblokkeerd.
 */
session_start();
require __DIR__ . '/gedeeld.php';

// Onverwachte fout: netjes terug naar de inlogpagina met uitleg
set_exception_handler(function ($e) {
    terugNaarLogin('Er ging iets mis: ' . $e->getMessage());
});

const MAX_POGINGEN = 5;
const BLOKKEER_MINUTEN = 15;

function terugNaarLogin($melding) {
    header('Location: login.html?fout=' . urlencode($melding));
    exit();
}

try {
    db();
} catch (Throwable $e) {
    terugNaarLogin('De database is niet bereikbaar. Staat WampServer aan?');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit();
}

$gebruikersnaam = trim($_POST['gebruikersnaam'] ?? '');
$wachtwoord = $_POST['wachtwoord'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Oude pogingen opruimen
q("DELETE FROM login_pogingen WHERE tijd < (NOW() - INTERVAL 1 DAY)");

// Te veel foute pogingen voor deze gebruikersnaam?
$r = q("SELECT COUNT(*) AS n, MIN(tijd) AS eerste FROM login_pogingen
        WHERE gebruikersnaam = ? AND tijd > (NOW() - INTERVAL ? MINUTE)",
       "si", [$gebruikersnaam, BLOKKEER_MINUTEN])->fetch_assoc();
if (intval($r['n']) >= MAX_POGINGEN) {
    $vrijOm = date('H:i', strtotime($r['eerste']) + BLOKKEER_MINUTEN * 60);
    terugNaarLogin("Te veel foute pogingen. Probeer het na $vrijOm opnieuw.");
}

$row = q("SELECT id, wachtwoord, rol, naam FROM chefs WHERE gebruikersnaam = ?", "s", [$gebruikersnaam])->fetch_assoc();

$ingelogd = false;
if ($row) {
    $opgeslagen = $row['wachtwoord'];
    if (password_verify($wachtwoord, $opgeslagen)) {
        $ingelogd = true;
    } elseif (!password_get_info($opgeslagen)['algo'] && hash_equals($opgeslagen, $wachtwoord)) {
        // Oud account met leesbaar wachtwoord: inloggen en meteen veilig opslaan
        $ingelogd = true;
        q("UPDATE chefs SET wachtwoord = ? WHERE id = ?", "si", [password_hash($wachtwoord, PASSWORD_DEFAULT), $row['id']]);
    }
}

if ($ingelogd) {
    q("DELETE FROM login_pogingen WHERE gebruikersnaam = ?", "s", [$gebruikersnaam]);
    session_regenerate_id(true);
    $_SESSION['user_id'] = intval($row['id']);
    $_SESSION['gebruiker'] = $row['naam'];
    $_SESSION['rol'] = $row['rol'];

    $doel = ['baas' => 'baas_paneel.php', 'chef' => 'chef.php', 'serveerster' => 'serveerster.php'];
    header('Location: ' . ($doel[$row['rol']] ?? 'login.html'));
    exit();
}

// Foute poging opslaan
q("INSERT INTO login_pogingen (gebruikersnaam, ip, tijd) VALUES (?, ?, NOW())", "ss", [$gebruikersnaam, $ip]);
$over = MAX_POGINGEN - (intval($r['n']) + 1);
usleep(500000);
terugNaarLogin($over > 0
    ? "Onjuiste gebruikersnaam of wachtwoord. Nog $over " . ($over === 1 ? 'poging' : 'pogingen') . "."
    : "Te veel foute pogingen. Probeer het over " . BLOKKEER_MINUTEN . " minuten opnieuw.");
