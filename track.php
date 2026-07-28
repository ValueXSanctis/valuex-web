<?php
/* =========================================================
   ValueX Makrotest. Ereignissammler.
   Speichert ausschliesslich anonyme Zahlen.
   Keine Namen, keine E-Mail, keine IP, keine Cookies.

   Diagnose: track.php?diag=valuex2026
   ========================================================= */

$SCHLUESSEL = 'valuex2026';   // gleicher Schluessel wie insights.php

/* ---------- Speicherort finden ----------
   Probiert mehrere Orte, nimmt den ersten beschreibbaren.
   Frueher lag die Datei fest in daten/ und scheiterte still,
   wenn der Ordner nicht angelegt werden konnte.                */
function speicherorte() {
    $o = [];
    $o[] = __DIR__ . '/daten';
    $o[] = __DIR__;
    $tmp = sys_get_temp_dir();
    if ($tmp) { $o[] = rtrim($tmp, '/') . '/valuex-quiz'; }
    return $o;
}

function logdatei(&$grund = null) {
    foreach (speicherorte() as $ordner) {
        if (!is_dir($ordner)) { @mkdir($ordner, 0755, true); }
        if (!is_dir($ordner) || !is_writable($ordner)) { continue; }
        $datei = $ordner . '/ereignisse.log.php';
        if (!file_exists($datei)) {
            /* Erste Zeile beendet PHP sofort. Direktaufruf liefert nichts. */
            if (@file_put_contents($datei, "<?php exit; ?>\n") === false) { continue; }
        }
        if (!is_writable($datei)) { continue; }
        $grund = $ordner;
        return $datei;
    }
    $grund = null;
    return null;
}

/* ---------- Diagnose ---------- */
if (isset($_GET['diag'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals($SCHLUESSEL, (string)$_GET['diag'])) { http_response_code(403); echo '{"ok":false}'; exit; }
    $pruef = [];
    foreach (speicherorte() as $o) {
        $pruef[] = [
            'pfad'         => $o,
            'existiert'    => is_dir($o),
            'beschreibbar' => is_dir($o) ? is_writable($o) : false,
        ];
    }
    $d = logdatei($grund);
    echo json_encode([
        'ok'          => $d !== null,
        'benutzt'     => $grund,
        'datei'       => $d,
        'zeilen'      => ($d && file_exists($d)) ? max(0, count(file($d)) - 1) : 0,
        'orte'        => $pruef,
        'php'         => PHP_VERSION,
        'user'        => function_exists('get_current_user') ? get_current_user() : '?',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/* ---------- Erfassung ---------- */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo '{"ok":false,"fehler":"nur POST"}'; exit; }

$raw = file_get_contents('php://input', false, null, 0, 4096);
$in  = json_decode($raw, true);
if (!is_array($in)) { http_response_code(400); echo '{"ok":false,"fehler":"kein JSON"}'; exit; }

$erlaubt = ['start','answer','done','cta'];
$ev = isset($in['ev']) ? (string)$in['ev'] : '';
if (!in_array($ev, $erlaubt, true)) { http_response_code(400); echo '{"ok":false,"fehler":"unbekanntes Ereignis"}'; exit; }

$sid = isset($in['sid']) ? substr(preg_replace('/[^a-z0-9]/i', '', (string)$in['sid']), 0, 24) : '';
if ($sid === '') { http_response_code(400); echo '{"ok":false,"fehler":"keine Sitzung"}'; exit; }

$d = isset($in['d']) && is_array($in['d']) ? $in['d'] : [];
$z = function ($k, $min, $max) use ($d) {
    if (!isset($d[$k]) || !is_numeric($d[$k])) return null;
    $v = (int)$d[$k];
    return ($v >= $min && $v <= $max) ? $v : null;
};

$zeile = [
    'ts'   => time(),
    'sid'  => $sid,
    'ev'   => $ev,
    'ms'   => (isset($in['t']) && is_numeric($in['t'])) ? min((int)$in['t'], 86400000) : null,
    'q'    => $z('q', 0, 30),
    'pick' => $z('pick', 0, 3),
    'ok'   => $z('ok', 0, 1),
    'conf' => $z('conf', 0, 100),
    'mod'  => $z('mod', 1, 12),
    'sc'   => $z('score', 0, 30),
    'self' => $z('self', 0, 100),
    'real' => $z('real', 0, 100),
    'gap'  => $z('gap', -100, 100),
    'weak' => isset($d['weak']) ? substr(preg_replace('/[^0-9,]/', '', (string)$d['weak']), 0, 30) : null,
];

$datei = logdatei($grund);
if ($datei === null) {
    /* Nicht mehr still scheitern. */
    http_response_code(500);
    echo '{"ok":false,"fehler":"kein beschreibbarer Speicherort"}';
    exit;
}

$n = @file_put_contents($datei, json_encode($zeile, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
if ($n === false) {
    http_response_code(500);
    echo '{"ok":false,"fehler":"Schreiben fehlgeschlagen"}';
    exit;
}

echo '{"ok":true}';
