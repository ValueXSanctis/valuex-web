<?php
/* =========================================================
   ValueX Makrotest. Auswertung.
   Aufruf: insights.php?k=DEIN_SCHLUESSEL
   ========================================================= */

$SCHLUESSEL = 'valuex2026';   // <<< HIER ANPASSEN

if (!isset($_GET['k']) || !hash_equals($SCHLUESSEL, (string)$_GET['k'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Kein Zugriff.";
    exit;
}

$FRAGEN = [
  ['Wer schafft den grössten Teil des Geldes?','Geldschöpfung',1,1,['Zentralbank druckt','Geschäftsbanken via Kredit','Staat via Anleihen','IWF']],
  ['Bei QE. Wo landet das neue Geld?','QE',1,2,['In der Wirtschaft','Bei den Bürgern','Als Reserve bei Banken','In fremden Währungen']],
  ['Zins 5, Inflation 7. Was heisst das für Bargeld?','Realzins',1,1,['5 Prozent Ertrag','2 Prozent Verlust','Geschützt','12 Prozent real']],
  ['Zinsen steigen. Was passiert mit alten Anleihen?','Anleihen',2,2,['Steigen mit','Nichts','Kurs fällt','Kommt drauf an']],
  ['Was ist ein Repo?','Repo',2,0,['Kredit gegen Sicherheit','Termingeschäft','Staatsanleihe','Währungstausch']],
  ['Zinskurve invertiert. Wann kommt die Rezession?','Zinskurve',2,2,['Sofort','Während der Inversion','Bei Versteilerung','Gar nicht']],
  ['Was sind Eurodollar?','Eurodollar',3,1,['Kurs EUR/USD','Dollar ausserhalb der USA','Der Euro','Zentralbankgeld']],
  ['Finanzkrise aus den USA. Was macht der Dollar?','Dollar',3,1,['Fällt','Steigt gegen alles','Stabil','Koppelt an Gold']],
  ['M2 plus 25 Prozent. Warum kam die Inflation erst später?','Geldmenge',4,1,['Statistik hinkt','Umlaufgeschwindigkeit brach ein','Kein Zusammenhang','Fed korrigierte']],
  ['Wohnkosten im Preisindex. Was ist das Problem?','CPI',4,1,['Nur jährlich','Ein Jahr Verzögerung','Ausgenommen','Basiert auf Verkäufen']],
  ['Wann werden Staatsschulden gefährlich?','Schulden',5,1,['Ab 100 Prozent','Zinslast wächst schneller','Keine Käufer mehr','Herabstufung']],
  ['Was läuft am engsten mit Risikoanlagen?','Liquidität',5,1,['Bilanzsumme','Nettoliquidität','Zinsschritte','Preisindex']],
];
$MODULE = [1=>'Der Preis von Geld',2=>'Bonds und Repos',3=>'Der Dollar',4=>'Inflation',5=>'Makrodaten'];

/* ---------- Daten lesen ---------- */
$kandidaten = [__DIR__ . '/daten/ereignisse.log.php', __DIR__ . '/ereignisse.log.php',
               rtrim(sys_get_temp_dir(),'/') . '/valuex-quiz/ereignisse.log.php'];
$datei = null;
foreach ($kandidaten as $kd) { if (file_exists($kd)) { $datei = $kd; break; } }
$zeilen = [];
if ($datei && file_exists($datei)) {
    $fh = fopen($datei, 'r');
    if ($fh) { fgets($fh); while (($l = fgets($fh)) !== false) { $j = json_decode($l, true); if (is_array($j)) $zeilen[] = $j; } fclose($fh); }
}

$tage = isset($_GET['tage']) ? max(1, min(365, (int)$_GET['tage'])) : 365;
$ab = time() - $tage * 86400;
$zeilen = array_values(array_filter($zeilen, fn($z) => ($z['ts'] ?? 0) >= $ab));

/* ---------- Auswerten ---------- */
$starts = $fertig = $cta = 0;
$scores = []; $selfs = []; $reals = [];
$proFrage = []; $erreicht = array_fill(0, count($FRAGEN), 0);
$proModul = [];
$sids = [];

foreach ($zeilen as $z) {
    $sids[$z['sid']] = true;
    switch ($z['ev']) {
        case 'start': $starts++; break;
        case 'cta':   $cta++;    break;
        case 'done':
            $fertig++;
            if ($z['sc']   !== null) $scores[] = $z['sc'];
            if ($z['self'] !== null) $selfs[]  = $z['self'];
            if ($z['real'] !== null) $reals[]  = $z['real'];
            break;
        case 'answer':
            $q = $z['q'];
            if ($q === null || !isset($FRAGEN[$q])) break;
            $erreicht[$q]++;
            if (!isset($proFrage[$q])) $proFrage[$q] = ['n'=>0,'ok'=>0,'conf'=>0,'picks'=>[0,0,0,0]];
            $proFrage[$q]['n']++;
            $proFrage[$q]['ok']   += (int)$z['ok'];
            $proFrage[$q]['conf'] += (int)$z['conf'];
            if ($z['pick'] !== null) $proFrage[$q]['picks'][$z['pick']]++;
            $m = $z['mod'];
            if ($m !== null) {
                if (!isset($proModul[$m])) $proModul[$m] = ['n'=>0,'ok'=>0];
                $proModul[$m]['n']++; $proModul[$m]['ok'] += (int)$z['ok'];
            }
            break;
    }
}

$mw  = fn($a) => count($a) ? array_sum($a) / count($a) : 0;
$pz  = fn($t, $g) => $g > 0 ? round($t / $g * 100) : 0;
$avgScore = $mw($scores); $avgSelf = $mw($selfs); $avgReal = $mw($reals);
$avgGap = $avgSelf - $avgReal;

$verteilung = array_fill(0, count($FRAGEN) + 1, 0);
foreach ($scores as $s) if ($s >= 0 && $s <= count($FRAGEN)) $verteilung[$s]++;
$maxVert = max(1, max($verteilung));
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Makrotest. Auswertung</title>
<meta name="robots" content="noindex,nofollow">
<style>
:root{--paper:#fff;--ink:#0a0a0a;--ink2:#3d3d3d;--ink3:#767676;--rule:#dcdcda;
--fd:Georgia,"Times New Roman",serif;--fb:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
--fm:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;--pad:clamp(1.1rem,4vw,2.5rem)}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--fb);font-size:16px;line-height:1.55;-webkit-font-smoothing:antialiased}
.shell{max-width:60rem;margin:0 auto;padding:0 var(--pad) 4rem}
.head{display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap;padding:1.4rem 0;border-bottom:2px solid var(--ink);margin-bottom:2.4rem}
.head h1{font-family:var(--fd);font-weight:400;font-size:clamp(1.5rem,1.2rem+1.6vw,2.2rem);margin:0;letter-spacing:-.02em}
.head .meta{font-family:var(--fm);font-size:.62rem;letter-spacing:.18em;text-transform:uppercase;color:var(--ink3)}
.head a{color:var(--ink3);text-decoration:none;margin-left:.9rem}
.head a:hover{color:var(--ink);text-decoration:underline}
.kick{font-family:var(--fm);font-size:.62rem;letter-spacing:.22em;text-transform:uppercase;color:var(--ink3);margin:2.6rem 0 1rem;padding-bottom:.6rem;border-bottom:1px solid var(--rule)}
.kpis{display:grid;grid-template-columns:repeat(2,1fr);border-top:1px solid var(--ink);border-bottom:1px solid var(--ink)}
@media(min-width:44rem){.kpis{grid-template-columns:repeat(4,1fr)}}
.kpi{padding:1.1rem .9rem 1.1rem 0;border-right:1px solid var(--rule)}
.kpi:last-child{border-right:0}
.kpi dt{font-family:var(--fm);font-size:.56rem;letter-spacing:.16em;text-transform:uppercase;color:var(--ink3);margin:0 0 .35rem}
.kpi dd{margin:0;font-family:var(--fd);font-size:1.9rem;line-height:1;font-variant-numeric:tabular-nums}
.kpi .sub{font-size:.74rem;color:var(--ink3);font-family:var(--fb);margin-top:.35rem}
table{width:100%;border-collapse:collapse;font-size:.92rem}
th{font-family:var(--fm);font-size:.56rem;letter-spacing:.16em;text-transform:uppercase;color:var(--ink3);text-align:left;padding:.55rem .5rem .55rem 0;border-bottom:1px solid var(--ink);font-weight:400;white-space:nowrap}
td{padding:.7rem .5rem .7rem 0;border-bottom:1px solid var(--rule);vertical-align:top}
td.num{font-family:var(--fd);font-variant-numeric:tabular-nums;text-align:right;white-space:nowrap;font-size:1.02rem}
tr.weak td{background:#fafaf9}
tr.weak td.num{font-weight:700}
.bar{height:9px;border:1px solid var(--ink);min-width:60px}
.bar i{display:block;height:100%;background:var(--ink)}
.bar.hatch i{background:repeating-linear-gradient(45deg,var(--ink) 0 2px,transparent 2px 5px)}
.dist{display:grid;grid-template-columns:repeat(13,1fr);gap:3px;align-items:end;height:130px;margin:1.2rem 0 .4rem}
.dist .col{background:var(--ink);min-height:2px}
.dist .col.zero{background:var(--rule)}
.distx{display:grid;grid-template-columns:repeat(13,1fr);gap:3px;font-family:var(--fm);font-size:.56rem;color:var(--ink3);text-align:center}
.funnel{border-top:1px solid var(--ink)}
.fr{display:flex;align-items:center;gap:.8rem;padding:.5rem 0;border-bottom:1px solid var(--rule);font-size:.86rem}
.fr .lbl{font-family:var(--fm);font-size:.6rem;color:var(--ink3);width:3.4rem;flex:0 0 auto}
.fr .bx{flex:1;height:12px;border:1px solid var(--ink)}
.fr .bx i{display:block;height:100%;background:var(--ink)}
.fr .vl{font-family:var(--fd);font-variant-numeric:tabular-nums;width:4.5rem;text-align:right;flex:0 0 auto}
.empty{border:2px solid var(--ink);padding:2rem 1.5rem;text-align:center}
.empty b{font-family:var(--fd);font-size:1.4rem;display:block;margin-bottom:.6rem;font-weight:400}
.note{font-size:.8rem;color:var(--ink3);margin-top:1rem;line-height:1.5}
.foot{margin-top:3rem;padding-top:1.2rem;border-top:1px solid var(--rule);font-size:.76rem;color:var(--ink3)}
</style>
</head>
<body>
<div class="shell">

<div class="head">
  <h1>Makrotest. Auswertung</h1>
  <div class="meta">
    <?= number_format(count($sids), 0, '.', "'") ?> Sitzungen
    <a href="?k=<?= urlencode($SCHLUESSEL) ?>&tage=7">7 Tage</a>
    <a href="?k=<?= urlencode($SCHLUESSEL) ?>&tage=30">30 Tage</a>
    <a href="?k=<?= urlencode($SCHLUESSEL) ?>&tage=365">Alles</a>
  </div>
</div>

<?php if ($starts === 0 && $fertig === 0): ?>
  <div class="empty">
    <b>Noch keine Daten.</b>
    Sobald der erste Besucher den Test startet, erscheinen hier die Zahlen.
    Prüfe, ob <code>track.php</code> im selben Ordner liegt und der Ordner <code>daten</code> beschreibbar ist.
  </div>
<?php else: ?>

<dl class="kpis">
  <div class="kpi"><dt>Gestartet</dt><dd><?= $starts ?></dd></div>
  <div class="kpi"><dt>Abgeschlossen</dt><dd><?= $fertig ?></dd>
      <div class="sub"><?= $pz($fertig, $starts) ?> Prozent der Starter</div></div>
  <div class="kpi"><dt>Punkte im Mittel</dt><dd><?= number_format($avgScore, 1, '.', '') ?></dd>
      <div class="sub">von <?= count($FRAGEN) ?></div></div>
  <div class="kpi"><dt>Klick auf Angebot</dt><dd><?= $cta ?></dd>
      <div class="sub"><?= $pz($cta, $fertig) ?> Prozent der Fertigen</div></div>
</dl>

<p class="kick">Gefühl gegen Realität, über alle Teilnehmer</p>
<table>
  <tr>
    <td style="width:14rem">So sicher waren sie</td>
    <td><div class="bar hatch"><i style="width:<?= round($avgSelf) ?>%"></i></div></td>
    <td class="num" style="width:5rem"><?= round($avgSelf) ?> %</td>
  </tr>
  <tr>
    <td>So oft lagen sie richtig</td>
    <td><div class="bar"><i style="width:<?= round($avgReal) ?>%"></i></div></td>
    <td class="num"><?= round($avgReal) ?> %</td>
  </tr>
  <tr>
    <td><b>Durchschnittliche Lücke</b></td>
    <td></td>
    <td class="num"><b><?= ($avgGap >= 0 ? '+' : '') . round($avgGap) ?></b></td>
  </tr>
</table>
<p class="note">Je grösser die Lücke, desto stärker wirkt das Ergebnis als Verkaufsargument.
Ab etwa 20 Punkten trifft es die meisten Leser spürbar.</p>

<p class="kick">Punkteverteilung</p>
<div class="dist">
  <?php for ($s = 0; $s <= count($FRAGEN); $s++): ?>
    <div class="col<?= $verteilung[$s] ? '' : ' zero' ?>"
         style="height:<?= $verteilung[$s] ? max(3, round($verteilung[$s] / $maxVert * 100)) : 1 ?>%"
         title="<?= $s ?> Punkte: <?= $verteilung[$s] ?>"></div>
  <?php endfor; ?>
</div>
<div class="distx">
  <?php for ($s = 0; $s <= count($FRAGEN); $s++): ?><span><?= $s ?></span><?php endfor; ?>
</div>

<p class="kick">Jede Frage einzeln</p>
<table>
  <thead><tr>
    <th style="width:2rem">Nr</th><th>Frage</th><th style="width:6rem">Richtig</th>
    <th style="width:6rem">Sicherheit</th><th>Häufigster Fehlgriff</th>
  </tr></thead>
  <tbody>
  <?php foreach ($FRAGEN as $i => $f):
      $d = $proFrage[$i] ?? ['n'=>0,'ok'=>0,'conf'=>0,'picks'=>[0,0,0,0]];
      $quote = $d['n'] ? round($d['ok'] / $d['n'] * 100) : null;
      $sich  = $d['n'] ? round($d['conf'] / $d['n']) : null;
      $best = null; $bestN = 0;
      foreach ($d['picks'] as $pi => $pn) { if ($pi !== $f[3] && $pn > $bestN) { $bestN = $pn; $best = $pi; } }
  ?>
    <tr class="<?= ($quote !== null && $quote < 50) ? 'weak' : '' ?>">
      <td class="num" style="text-align:left"><?= $i + 1 ?></td>
      <td><?= htmlspecialchars($f[0]) ?><br>
          <span style="font-family:var(--fm);font-size:.56rem;letter-spacing:.14em;text-transform:uppercase;color:var(--ink3)">
            <?= htmlspecialchars($f[1]) ?> · Modul <?= $f[2] ?> · <?= $d['n'] ?> Antworten</span></td>
      <td class="num"><?= $quote === null ? '.' : $quote . ' %' ?></td>
      <td class="num"><?= $sich === null ? '.' : $sich . ' %' ?></td>
      <td><?= $best === null ? '.' : htmlspecialchars($f[4][$best]) . ' <span style="color:var(--ink3)">(' . $bestN . ')</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<p class="kick">Wo abgebrochen wird</p>
<div class="funnel">
  <?php $max = max(1, $erreicht[0] ?: 1);
  foreach ($erreicht as $i => $n): ?>
    <div class="fr">
      <span class="lbl">Frage <?= $i + 1 ?></span>
      <span class="bx"><i style="width:<?= round($n / $max * 100) ?>%"></i></span>
      <span class="vl"><?= $n ?></span>
    </div>
  <?php endforeach; ?>
</div>
<p class="note">Ein deutlicher Absacker zeigt, wo die Leute aussteigen.
Meist ist die Frage davor zu schwer oder zu lang.</p>

<p class="kick">Nach Modul</p>
<table>
  <thead><tr><th>Modul</th><th style="width:8rem">Trefferquote</th><th style="width:6rem">Antworten</th></tr></thead>
  <tbody>
  <?php foreach ($MODULE as $m => $name):
      $d = $proModul[$m] ?? ['n'=>0,'ok'=>0];
      $q = $d['n'] ? round($d['ok'] / $d['n'] * 100) : null; ?>
    <tr class="<?= ($q !== null && $q < 50) ? 'weak' : '' ?>">
      <td>Modul <?= $m ?>. <?= htmlspecialchars($name) ?></td>
      <td class="num"><?= $q === null ? '.' : $q . ' %' ?></td>
      <td class="num"><?= $d['n'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="note">Das schwächste Modul ist dein bestes Verkaufsargument.
Genau darüber kannst du in der nächsten Mail schreiben.</p>

<?php endif; ?>

<p class="foot">
  Erfasst werden ausschliesslich anonyme Zahlen. Keine Namen, keine E-Mail, keine IP, keine Cookies.
  Ein Durchlauf wird über eine im Browser erzeugte Zufallskennung zusammengefasst, die nirgends
  gespeicherten Personen zugeordnet werden kann.<br>
  ValueX · <?= date('d.m.Y H:i') ?>
</p>

</div>
</body>
</html>
