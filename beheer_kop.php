<?php
/*
 * Las Tapas - beheer_kop.php
 * Gedeelde stijl en menubalk voor de beheerpagina's van de baas.
 * Gebruik: $paginaTitel en $actievePagina instellen, dan: include 'beheer_kop.php';
 */
$beheerPaginas = [
    'baas_paneel.php'   => '👥 Medewerkers',
    'menu_beheer.php'   => '🍽️ Menu & prijzen',
    'statistieken.php'  => '📊 Statistieken',
    'qr_generator.php'  => '🔳 QR-codes',
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Las Tapas - <?php echo esc($paginaTitel ?? 'Beheer'); ?></title>
    <style>
        :root { --rood: #b33939; --donkerrood: #822626; --creme: #fdfbf7; --inkt: #2d211d; --grijs: #786a63; --lijn: #e0d5cb; --vlak: #fcf8f2; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: var(--creme); color: var(--inkt); margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h1, h2, h3 { color: var(--rood); font-family: Georgia, serif; }
        h2 { margin-top: 30px; }

        .header-flex { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
        .header-flex h1 { margin: 0 0 4px 0; }
        .header-flex p { margin: 0; }
        .header-knoppen { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-uitlog { background: #786a63; text-decoration: none; padding: 8px 15px; border-radius: 6px; color: white; font-size: 0.9rem; font-weight: bold; }

        /* Menubalk tussen de beheerpagina's */
        .beheer-nav { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 25px; }
        .beheer-nav a { background: #f0eae1; color: var(--grijs); text-decoration: none; padding: 9px 16px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; }
        .beheer-nav a.actief { background: var(--rood); color: white; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; }
        th { background: var(--vlak); color: var(--grijs); }
        .tabel-scroll { overflow-x: auto; }

        .form-box { background: var(--vlak); padding: 20px; border-radius: 8px; margin-top: 20px; border: 1px solid var(--lijn); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        label { font-weight: bold; font-size: 0.9rem; }
        input[type=text], input[type=password], input[type=number], select, textarea {
            width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 6px; font-family: inherit; font-size: 0.95rem;
        }
        button, .knop { padding: 10px 20px; background: var(--rood); color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 15px; font-family: inherit; font-size: 0.95rem; text-decoration: none; display: inline-block; }
        button:hover, .knop:hover { background: var(--donkerrood); }
        .btn-bewerk { background: #f39c12; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: bold; display: inline-block; }
        .btn-verwijder { background: white; color: #c0392b; border: 2px solid #c0392b; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; margin-top: 0; cursor: pointer; }
        .btn-verwijder:hover { background: #fdecea; }
        .btn-annuleer { background: #786a63; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block; margin-top: 15px; font-weight: bold; }
        .inline-form { display: inline; margin: 0; }

        .melding { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .melding.ok { background: #eafaf1; color: #1e8449; border: 1px solid #abebc6; }
        .melding.fout { background: #fdecea; color: #922b21; border: 1px solid #f5c6c0; }
        .uitleg { color: var(--grijs); font-size: 0.9rem; }

        /* Telefoon: tabellen als kaarten */
        @media (max-width: 700px) {
            body { padding: 10px; }
            .container { padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
            h1 { font-size: 1.5rem; }
            table.kaarten thead { display: none; }
            table.kaarten, table.kaarten tbody, table.kaarten tr, table.kaarten td { display: block; width: 100%; }
            table.kaarten tr { background: var(--vlak); border: 1px solid var(--lijn); border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; }
            table.kaarten td { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 6px 0; border-bottom: none; }
            table.kaarten td::before { content: attr(data-label); color: var(--grijs); font-size: 0.85rem; font-weight: bold; }
            table.kaarten td.cel-naam { font-size: 1.1rem; font-weight: bold; }
            table.kaarten td.cel-acties { gap: 16px; margin-top: 10px; padding-top: 14px; border-top: 1px solid var(--lijn); }
            table.kaarten td.cel-acties::before { display: none; }
            .cel-acties > * { flex: 1; text-align: center; }
            .cel-acties .btn-bewerk, .cel-acties .btn-verwijder { width: 100%; padding: 12px 10px; font-size: 0.95rem; }
        }
        <?php echo $extraStijl ?? ''; ?>
    </style>
</head>
<body>
<div class="container">
    <div class="header-flex">
        <div>
            <h1>🍷 Las Tapas - Beheer</h1>
            <p>Welkom, <strong><?php echo esc($_SESSION['gebruiker'] ?? ''); ?></strong> (Eigenaar)</p>
        </div>
        <div class="header-knoppen">
            <a href="chef.php" class="btn-uitlog" style="background: #27ae60;">🍳 Keuken</a>
            <a href="serveerster.php" class="btn-uitlog" style="background: #2980b9;">🏃 Bediening</a>
            <a href="logout.php" class="btn-uitlog">Uitloggen</a>
        </div>
    </div>
    <nav class="beheer-nav">
        <?php foreach ($beheerPaginas as $bestand => $label): ?>
            <a href="<?php echo $bestand; ?>" class="<?php echo ($actievePagina ?? '') === $bestand ? 'actief' : ''; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </nav>
