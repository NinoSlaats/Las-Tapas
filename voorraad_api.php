<?php
/*
 * Las Tapas - voorraad_api.php
 * Voorraad opvragen (iedereen) en aanpassen (alleen chef of baas).
 * Gebruikt de gedeelde databaseverbinding uit gedeeld.php.
 */
require __DIR__ . '/gedeeld.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

session_start();
$rol = $_SESSION['rol'] ?? '';
session_write_close();

function antwoord($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

try {
    db();
} catch (Throwable $e) {
    antwoord(['succes' => false, 'fout' => 'Databaseverbinding mislukt']);
}

$methode = $_SERVER['REQUEST_METHOD'];

// Alle voorraad opvragen
if ($methode === 'GET') {
    $voorraad = [];
    foreach (alleRijen(q("SELECT id, naam, aantal FROM voorraad")) as $rij) {
        $voorraad[] = ['id' => (int)$rij['id'], 'naam' => $rij['naam'], 'aantal' => (int)$rij['aantal']];
    }
    antwoord($voorraad);
}

// Aantal van één product aanpassen
if ($methode === 'POST') {
    if ($rol !== 'chef' && $rol !== 'baas') {
        antwoord(['succes' => false, 'fout' => 'Geen toegang: log in als chef of baas.'], 403);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (($data['actie'] ?? '') === 'update') {
        try {
            q("UPDATE voorraad SET aantal = ? WHERE id = ?", "ii", [max(0, intval($data['aantal'] ?? 0)), intval($data['id'] ?? 0)]);
            antwoord(['succes' => true]);
        } catch (Throwable $e) {
            antwoord(['succes' => false, 'fout' => 'Kon database niet updaten']);
        }
    }
}

antwoord(['succes' => false, 'fout' => 'Ongeldige aanvraag']);
