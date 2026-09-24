<?php
/*
 * Las Tapas - mailer.php
 * Verstuurt e-mail via een echte mailserver (SMTP), bijvoorbeeld Gmail.
 *
 * Waarom niet de PHP-functie mail()? Die heeft een mailserver op je eigen pc nodig,
 * en die zit niet in WampServer. Deze module maakt zelf een beveiligde verbinding
 * met de mailserver, logt in en verstuurt het bericht.
 *
 * De gegevens (server, gebruiker, app-wachtwoord) vult de baas in bij
 * Beheer > Menu & prijzen > Instellingen.
 */
require_once __DIR__ . '/gedeeld.php';

// Geeft [true, 'melding'] of [false, 'foutmelding'] terug
function stuurMail($naar, $onderwerp, $html, $tekst) {
    $host        = trim(instelling('smtp_host', 'smtp.gmail.com'));
    $poort       = intval(instelling('smtp_poort', 587));
    $beveiliging = instelling('smtp_beveiliging', 'tls');     // 'tls' (poort 587) of 'ssl' (poort 465)
    $gebruiker   = trim(instelling('smtp_gebruiker', ''));
    $wachtwoord  = str_replace(' ', '', instelling('smtp_wachtwoord', ''));   // Gmail toont app-wachtwoorden met spaties
    $afzender    = instelling('mail_afzender_naam', 'Las Tapas');
    $controleUit = instelling('smtp_controle_uit', '0') === '1';

    if ($gebruiker === '' || $wachtwoord === '') {
        return [false, 'E-mail is nog niet ingesteld. De baas kan dit doen bij Menu & prijzen > Instellingen.'];
    }
    if (!filter_var($naar, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Ongeldig e-mailadres.'];
    }
    if (!extension_loaded('openssl')) {
        return [false, 'De PHP-extensie "openssl" staat uit. Zet hem aan via WampServer > PHP > PHP extensions > php_openssl.'];
    }

    $ssl = ['verify_peer' => !$controleUit, 'verify_peer_name' => !$controleUit, 'allow_self_signed' => $controleUit];
    $context = stream_context_create(['ssl' => $ssl]);
    $adres = ($beveiliging === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $poort;

    $fp = @stream_socket_client($adres, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return [false, "Kan geen verbinding maken met $host:$poort ($errstr). Controleer server en poort, of je internet/firewall."];
    }
    stream_set_timeout($fp, 20);

    // Eén antwoord van de server lezen (kan uit meerdere regels bestaan)
    $lees = function () use ($fp) {
        $antwoord = '';
        while (($regel = fgets($fp, 1024)) !== false) {
            $antwoord .= $regel;
            if (strlen($regel) < 4 || $regel[3] === ' ') break;
        }
        return $antwoord;
    };
    // Opdracht sturen en controleren of het antwoord de verwachte code heeft
    $opdracht = function ($cmd, $verwacht, $geheim = false) use ($fp, $lees) {
        if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
        $antwoord = $lees();
        $code = intval(substr($antwoord, 0, 3));
        if (!in_array($code, (array)$verwacht, true)) {
            $wat = $geheim ? '(inloggen)' : strtok((string)$cmd, ' ');
            throw new RuntimeException(trim($antwoord) !== '' ? "$wat: " . trim($antwoord) : 'Geen antwoord van de mailserver.');
        }
        return $antwoord;
    };

    try {
        $opdracht(null, 220);
        $opdracht('EHLO lastapas.local', 250);
        if ($beveiliging === 'tls') {
            $opdracht('STARTTLS', 220);
            $methode = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $methode |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (!@stream_socket_enable_crypto($fp, true, $methode)) {
                throw new RuntimeException('Beveiligde verbinding (TLS) mislukt. Tip: zet bij Instellingen "Certificaat niet controleren" aan als je op WampServer test.');
            }
            $opdracht('EHLO lastapas.local', 250);
        }
        $opdracht('AUTH LOGIN', 334);
        $opdracht(base64_encode($gebruiker), 334, true);
        $opdracht(base64_encode($wachtwoord), 235, true);
        $opdracht("MAIL FROM:<$gebruiker>", 250);
        $opdracht("RCPT TO:<$naar>", [250, 251]);
        $opdracht('DATA', 354);

        // Bericht opbouwen: HTML-versie + gewone-tekstversie
        $grens = 'lastapas_' . bin2hex(random_bytes(8));
        $koppen = [
            'Date: ' . date('r'),
            'From: =?UTF-8?B?' . base64_encode($afzender) . "?= <$gebruiker>",
            "To: <$naar>",
            'Subject: =?UTF-8?B?' . base64_encode($onderwerp) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@lastapas.local>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"$grens\"",
        ];
        $bericht = implode("\r\n", $koppen) . "\r\n\r\n"
            . "--$grens\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($tekst))
            . "--$grens\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$grens--";

        $opdracht($bericht . "\r\n.", 250);
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return [true, "E-mail verstuurd naar $naar."];
    } catch (Throwable $e) {
        @fclose($fp);
        $melding = $e->getMessage();
        if (strpos($melding, '535') !== false || strpos($melding, '534') !== false) {
            $melding .= ' Tip: gebruik bij Gmail een app-wachtwoord, niet je gewone wachtwoord.';
        }
        return [false, 'Versturen mislukt: ' . $melding];
    }
}
