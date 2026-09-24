<?php
/*
 * Las Tapas - bon.php
 * Printbare bon van een tafel die nog in gebruik is (voor de serveerster).
 * Gebruik: bon.php?tafel=9  (alleen voor ingelogd personeel)
 */
session_start();
require __DIR__ . '/bon_functies.php';

if (!in_array($_SESSION['rol'] ?? '', ['serveerster', 'baas', 'chef'], true)) {
    header('Location: login.html');
    exit();
}
set_exception_handler('toonFoutPagina');
db();

$tafel = intval($_GET['tafel'] ?? 0);
$t = q("SELECT * FROM tafels WHERE tafel = ?", "i", [$tafel])->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon tafel <?php echo $tafel; ?> - Las Tapas</title>
    <style>
        body { margin: 0; background: #fbf5ea; font-family: 'Segoe UI', Arial, sans-serif; }
        .knoppen { text-align: center; padding: 15px; }
        .knoppen button { background: #a8232b; color: white; border: none; padding: 12px 22px; border-radius: 8px; font-weight: bold; font-size: 1rem; cursor: pointer; margin: 0 5px; }
        .knoppen button.grijs { background: #786a63; }
        .leeg { text-align: center; padding: 40px; color: #786a63; }
        @media print {
            .knoppen { display: none; }
            body { background: white; }
        }
    </style>
</head>
<body>
    <div class="knoppen">
        <button onclick="window.print()">🖨️ Printen</button>
        <button class="grijs" onclick="window.close()">Sluiten</button>
    </div>
    <?php if ($t): ?>
        <?php echo bonHtml($t, $t['bon_taal'] ?: 'nl'); ?>
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    <?php else: ?>
        <p class="leeg">Tafel <?php echo $tafel; ?> is niet (meer) in gebruik, dus er is geen bon.</p>
    <?php endif; ?>
</body>
</html>
