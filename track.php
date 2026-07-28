<?php
/* =========================================================
   ValueX Makrotest. Ereignissammler.
   Speichert ausschliesslich anonyme Zahlen.
   Keine Namen, keine E-Mail, keine IP, keine Cookies.
   ========================================================= */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo '{"ok":false}'; exit; }

$raw = file_get_contents('php://input', false, null, 0, 4096);
$in  = json_decode($raw, true);
if (!is_array($in)) { http_response_code(400); echo '{"ok":false}'; exit; }

$erlaubt = ['start','answer','done','cta'];
$ev = isset($in['ev']) ? (string)$in['ev'] : '';
if (!in_array($ev, $erlaubt, true)) { http_response_code(400); echo '{"ok":false}'; exit; }

/* Sitzungskennung nur zum Zusammenfassen eines Durchlaufs.
   Zufallswert aus dem Browser, keinerlei Personenbezug. */
$sid = isset($in['sid']) ? preg_replace('/[^a-z0-9]/i', '', (string)$in['sid']) : '';
$sid = substr($sid, 0, 24);
if ($sid === '') { http_response_code(400); echo '{"ok":false}'; exit; }

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

$ordner = __DIR__ . '/daten';
if (!is_dir($ordner)) { @mkdir($ordner, 0755, true); }

$datei = $ordner . '/ereignisse.log.php';
if (!file_exists($datei)) {
    /* Erste Zeile beendet PHP sofort. Direktaufruf liefert damit nichts. */
    @file_put_contents($datei, "<?php exit; ?>\n");
}

@file_put_contents($datei, json_encode($zeile, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

echo '{"ok":true}';
