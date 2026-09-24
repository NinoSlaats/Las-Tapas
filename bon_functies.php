<?php
/*
 * Las Tapas - bon_functies.php
 * Maakt de bon (rekening) van een tafel: als HTML voor e-mail en printen, en als platte tekst.
 * De bon komt in de taal die de gast op zijn telefoon had gekozen (nl, en of es).
 */
require_once __DIR__ . '/gedeeld.php';
require_once __DIR__ . '/mailer.php';

const BON_TEKSTEN = [
    'nl' => [
        'titel' => 'Bon', 'datum' => 'Datum', 'tafel' => 'Tafel', 'naam' => 'Naam', 'arrangement' => 'Arrangement',
        'totaal' => 'Totaal', 'betaald' => 'Betaald', 'contant' => 'contant', 'online' => 'online',
        'bedankt' => '¡Muchas gracias! Bedankt voor jullie bezoek. Graag tot ziens bij Las Tapas.',
        'onderwerp' => 'Jullie bon van Las Tapas', 'btw' => 'Prijzen inclusief btw.',
    ],
    'en' => [
        'titel' => 'Receipt', 'datum' => 'Date', 'tafel' => 'Table', 'naam' => 'Name', 'arrangement' => 'Package',
        'totaal' => 'Total', 'betaald' => 'Paid', 'contant' => 'cash', 'online' => 'online',
        'bedankt' => '¡Muchas gracias! Thank you for your visit. We hope to see you again at Las Tapas.',
        'onderwerp' => 'Your receipt from Las Tapas', 'btw' => 'Prices include VAT.',
    ],
    'es' => [
        'titel' => 'Recibo', 'datum' => 'Fecha', 'tafel' => 'Mesa', 'naam' => 'Nombre', 'arrangement' => 'Menú',
        'totaal' => 'Total', 'betaald' => 'Pagado', 'contant' => 'en efectivo', 'online' => 'online',
        'bedankt' => '¡Muchas gracias por vuestra visita! Esperamos veros pronto en Las Tapas.',
        'onderwerp' => 'Vuestro recibo de Las Tapas', 'btw' => 'Precios con IVA incluido.',
    ],
];

function bonEuro($bedrag) {
    return '€ ' . number_format((float)$bedrag, 2, ',', '.');
}

// Alle gegevens die op de bon komen
function bonGegevens($t, $taal) {
    $taal = isset(BON_TEKSTEN[$taal]) ? $taal : 'nl';
    return [
        'taal' => $taal,
        'tk' => BON_TEKSTEN[$taal],
        'rekening' => berekenRekening($t, $taal),
        'datum' => date('d-m-Y H:i'),
        'methode' => $t['betaalmethode'] ?: 'contant',
    ];
}

// HTML-bon (werkt in e-mailprogramma's: alleen tabellen en inline stijlen)
function bonHtml($t, $taal = 'nl') {
    $g = bonGegevens($t, $taal);
    $tk = $g['tk'];
    $regels = '';
    foreach ($g['rekening']['regels'] as $r) {
        $regels .= '<tr><td style="padding:6px 0;border-bottom:1px solid #f0e6da;">' . esc($r['omschrijving']) . '</td>'
                 . '<td style="padding:6px 0;border-bottom:1px solid #f0e6da;text-align:right;white-space:nowrap;">' . bonEuro($r['bedrag']) . '</td></tr>';
    }
    $info = function ($label, $waarde) {
        return '<tr><td style="padding:2px 0;color:#786a63;">' . esc($label) . '</td><td style="padding:2px 0;text-align:right;">' . esc($waarde) . '</td></tr>';
    };
    return '<div style="background:#fbf5ea;padding:20px 10px;font-family:Segoe UI,Arial,sans-serif;color:#2b1d19;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:420px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;">'
        . '<tr><td style="background:#a8232b;color:#ffffff;text-align:center;padding:22px 16px 16px;">'
        . '<div style="font-family:Georgia,serif;font-size:26px;font-weight:bold;">🍷 Las Tapas</div>'
        . '<div style="font-size:11px;letter-spacing:3px;text-transform:uppercase;opacity:.85;margin-top:4px;">Taberna española</div></td></tr>'
        . '<tr><td style="height:8px;background:#1f4e8c;background-image:repeating-linear-gradient(90deg,#1f4e8c 0 8px,#fdf6e3 8px 16px);"></td></tr>'
        . '<tr><td style="padding:18px 22px 6px;">'
        . '<div style="font-family:Georgia,serif;font-size:20px;color:#a8232b;font-weight:bold;margin-bottom:10px;">' . esc($tk['titel']) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">'
        . $info($tk['datum'], $g['datum'])
        . $info($tk['tafel'], (string)intval($t['tafel']))
        . $info($tk['naam'], $t['gast_naam'])
        . $info($tk['arrangement'], $t['pakket'])
        . '</table></td></tr>'
        . '<tr><td style="padding:10px 22px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">'
        . $regels
        . '<tr><td style="padding:10px 0 4px;font-weight:bold;font-size:17px;">' . esc($tk['totaal']) . '</td>'
        . '<td style="padding:10px 0 4px;text-align:right;font-weight:bold;font-size:17px;">' . bonEuro($g['rekening']['totaal']) . '</td></tr>'
        . '<tr><td colspan="2" style="font-size:12px;color:#786a63;">' . esc($tk['betaald']) . ' ' . esc($tk[$g['methode']] ?? $g['methode']) . ' · ' . esc($tk['btw']) . '</td></tr>'
        . '</table></td></tr>'
        . '<tr><td style="padding:14px 22px 22px;text-align:center;font-size:13px;color:#5f6f2f;">' . esc($tk['bedankt']) . '</td></tr>'
        . '</table></div>';
}

// Platte-tekstversie (voor e-mailprogramma's zonder HTML)
function bonTekst($t, $taal = 'nl') {
    $g = bonGegevens($t, $taal);
    $tk = $g['tk'];
    $r = "LAS TAPAS - Taberna española\n" . strtoupper($tk['titel']) . "\n\n"
       . "{$tk['datum']}: {$g['datum']}\n{$tk['tafel']}: " . intval($t['tafel']) . "\n{$tk['naam']}: {$t['gast_naam']}\n{$tk['arrangement']}: {$t['pakket']}\n\n";
    foreach ($g['rekening']['regels'] as $regel) {
        $r .= str_pad($regel['omschrijving'], 32) . ' ' . bonEuro($regel['bedrag']) . "\n";
    }
    $r .= str_repeat('-', 44) . "\n" . str_pad($tk['totaal'], 32) . ' ' . bonEuro($g['rekening']['totaal']) . "\n\n"
        . $tk['betaald'] . ' ' . ($tk[$g['methode']] ?? $g['methode']) . "\n\n" . $tk['bedankt'] . "\n";
    return $r;
}

// Bon per e-mail naar de gast sturen. Geeft [gelukt, melding] terug.
function stuurBonPerMail($t) {
    $taal = $t['bon_taal'] ?? 'nl';
    $tk = BON_TEKSTEN[$taal] ?? BON_TEKSTEN['nl'];
    return stuurMail($t['bon_email'], $tk['onderwerp'] . ' (' . $tk['tafel'] . ' ' . intval($t['tafel']) . ')',
                     bonHtml($t, $taal), bonTekst($t, $taal));
}
