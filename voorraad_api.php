<?php
header('Content-Type: application/json');
ini_set('display_errors', '0');

// Wie is er ingelogd? Voorraad bekijken mag iedereen, aanpassen alleen chef of baas.
session_start();
$rol = $_SESSION['rol'] ?? '';
session_write_close();

// Databaseverbinding voor Las Tapas
$host = 'localhost';
$db   = 'las_tapas_db';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(['succes' => false, 'fout' => 'Databaseverbinding mislukt']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Haal alle voorraaditems op uit de database
    $result = $conn->query("SELECT id, naam, aantal FROM voorraad");
    $voorraad = [];
    
    while ($row = $result->fetch_assoc()) {
        $voorraad[] = [
            'id' => (int)$row['id'],
            'naam' => $row['naam'],
            'aantal' => (int)$row['aantal']
        ];
    }
    
    echo json_encode($voorraad);
    exit();
}

if ($method === 'POST') {
    if ($rol !== 'chef' && $rol !== 'baas') {
        http_response_code(403);
        echo json_encode(['succes' => false, 'fout' => 'Geen toegang: log in als chef of baas.']);
        exit();
    }

    // Ontvang de JSON data van de frontend
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['actie']) && $data['actie'] === 'update') {
        $id = intval($data['id']);
        $aantal = max(0, intval($data['aantal']));

        $stmt = $conn->prepare("UPDATE voorraad SET aantal = ? WHERE id = ?");
        $stmt->bind_param("ii", $aantal, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['succes' => true]);
        } else {
            echo json_encode(['succes' => false, 'fout' => 'Kon database niet updaten']);
        }
        exit();
    }
}

echo json_encode(['succes' => false, 'fout' => 'Ongeldige aanvraag']);
?>