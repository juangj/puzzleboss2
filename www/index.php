<?php 
require('puzzlebosslib.php');

if (isset($_GET['submit'])) {
  http_response_code(500);
  die('submission not implemented here');
}

// Check for authenticated user
$uid = getauthenticateduser();
$solver = readapi("/solvers/$uid")->solver;
$fullhunt = array_reverse(readapi('/all')->rounds);

if (isset($_GET['data'])) {
  header('Content-Type: application/json; charset=utf-8');
  die(json_encode(array(
    'comparison' => $comparison,
    'solver' => $solver,
    'fullhunt' => $fullhunt,
  )));
}

$use_text = isset($_GET['text_only']);

$username = $solver->name;
$mypuzzle = $solver->puzz;

// https://gist.github.com/tott/7684443
function ip_in_range($ip, $range) {
  if (strpos($range, '/') == false) {
    $range .= '/32';
  }
  // $range is in IP/CIDR format eg 127.0.0.1/24
  list($range, $netmask) = explode('/', $range, 2);
  $range_decimal = ip2long($range);
  $ip_decimal = ip2long($ip);
  $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
  $netmask_decimal = ~ $wildcard_decimal;
  return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
}

function get_user_network() {
  $ipaddr = $_SERVER['REMOTE_ADDR'];
  // https://kb.mit.edu/confluence/pages/46301207
  if (ip_in_range($ipaddr, '192.54.222.0/24')) {
    return 'MIT GUEST';
  }
  if (ip_in_range($ipaddr, '18.29.0.0/16')) {
    return 'MIT / MIT SECURE';
  }
  // https://kb.mit.edu/confluence/display/istcontrib/Eduroam+IP+address+ranges
  // (Maybe a typo, but just to be sure)
  if (ip_in_range($ipaddr, '18.189.0.0/16')) {
    return 'MIT / MIT SECURE';
  }
  return 'Other';
}
$user_network = get_user_network();
if ($user_network === 'MIT GUEST' || isset($_GET['wifi_debug'])) {
  $wifi_warning = <<<HTML
  <style>
    .error {
      background-color: lightpink;
      font-family: 'Lora';
      margin: 20px;
      max-width: 700px;
      padding: 10px;
    }
  </style>
  <div class="error">
    <strong>WARNING:</strong>&nbsp;
    You are on <tt>MIT GUEST</tt> Wifi right now, which does NOT support
    Discord audio calls and is much slower!
    Please <strong>switch to <tt>MIT</tt> / <tt>MIT SECURE</tt></strong>, by either:
    <ul>
      <li><strong>joining directly</strong>, if you have <a href="https://kb.mit.edu/confluence/display/istcontrib/How+to+connect+to+MIT+SECURE+wireless+on+macOS" target="_blank">an active Kerberos</a>,</li>
      <li><strong>generating a password at <a href="https://wifi.mit.edu/" target="_blank">wifi.mit.edu</a></strong>, if you have some MIT affiliation (including alumni), then joining the <tt>MIT</tt> network, or</li>
      <li>connecting directly to the <tt>MIT</tt> network with the <strong>WiFi password in the HQ room</strong> (non-MIT folks use this one).</li>
    </ul>
    Again, <strong>you will have a harder time participating in Hunt</strong> on this WiFi network! Continue at your own peril. <a href="https://importanthuntpoll.org/wiki/index.php/WiFi" target="_blank">See here for more info.</a>
  </div>
HTML;
} else {
  $wifi_warning = '';
}

function print_rounds_table($rounds) {
  global $use_text, $username, $mypuzzle;
  echo '<table border=4 style="vertical-align:top;"><tr>';
  foreach ($rounds as $round) {
    echo '<th>' . $round->name . '</th>';
  }
  echo '</tr><tr>';
  foreach ($rounds as $round) {
    echo '<td>';
    $puzzlearray = $round->puzzles;
    $metapuzzle = $round->meta_id;

    echo '<table>';
    foreach ($puzzlearray as $puzzle) {
      if ($puzzle->status == '[hidden]') {
        continue;
      }
      $puzzleid = $puzzle->id;
      $puzzlename = $puzzle->name;
      $styleinsert = "";
      if ($puzzleid == $metapuzzle && $puzzle->status != "Critical") {
        $styleinsert .= " bgcolor='Gainsboro' ";
      }
      if ($puzzlename == $mypuzzle) {
        $styleinsert .= ' style="text-decoration:underline overline wavy" ';
      }
      if ($puzzle->status == "New" && $puzzleid != $metapuzzle) {
        $styleinsert .= " bgcolor='aquamarine' ";
      }
      if ($puzzle->status == "Critical") {
        $styleinsert .= " bgcolor='HotPink' ";
      }
      // Not sure what to do here for style for solved/unnecc puzzles
      //if ($puzzle->status == "Solved" || $val->puzzle->status == "Unnecessary") {
      //  $styleinsert .= ' style="text-decoration:line-through" ';
      //}
      echo '<tr ' . $styleinsert . '>';
      echo '<td><a href="editpuzzle.php?pid=' . $puzzle->id . '&assumedid=' . $username . '" target="_blank">';
      switch ($puzzle->status) {
        case "New":
          echo $use_text ? '.' : '🆕';
          break;
        case "Being worked":
          echo $use_text ? 'O' : '🙇';
          break;
        case "Needs eyes":
          echo $use_text ? 'E' : '👀';
          break;
        case "WTF":
          echo $use_text ? '?' : '☢️';
          break;
        case "Critical":
          echo $use_text ? '!' : '⚠️';
          break;
        case "Solved":
          echo $use_text ? '*' : '✅';
          break;
        case "Unnecessary":
          echo $use_text ? 'X' : '😶‍🌫️';
          break;
      }
      echo '</a></td>';
      echo '<td><a href="' . $puzzle->puzzle_uri . '" target="_blank">'. $puzzlename . '</a></td>';
      echo '<td><a href="' . $puzzle->drive_uri . '" title="Spreadsheet" target="_blank">'. ($use_text ? 'D' : '🗒️') .'</a></td>';
      echo '<td><a href="' . $puzzle->chat_channel_link  . '" title="Discord" target="_blank">'. ($use_text ? 'C' : '🗣️') .'</a></td>';
      echo '<td style="font-family:monospace;font-style:bold">' . $puzzle->answer .'</td>';
      echo '<td><a href="editpuzzle.php?pid=' . $puzzle->id . '&assumedid=' . $username . '" target="_blank" title="Edit puzzle in PB">'. ($use_text ? '±' : '⚙️') . '</a></td>';

      echo '</tr>';

    }
    echo '</table>';
    echo '</td>';
  }
  echo '</tr></table>';
}

?>
<html>
<head>
  <meta http-equiv="refresh" content=30>
  <title>Puzzleboss Interface</title>
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;700&amp;family=Open+Sans:wght@400;700&amp;display=swap" rel="stylesheet">
  <style>
  body {
    background-color: aliceblue;
  }
  .error {
    background-color: lightpink;
    padding: 10px;
  }
  .success {
    background-color: lightgreen;
    padding: 10px;
  }
  </style>
</head>
<body>
<?php


if (isset($_GET['r']) && is_array($_GET['r'])) {
  $comparison = array();
  foreach ($_GET['r'] as $round_name => $round_data) {
    $round_data = array_chunk(explode(',', $round_data), 3);
    foreach ($round_data as $puzzle_data) {
      $slug = strtolower(str_replace('-', '', $puzzle_data[0]));
      $comparison[$slug] = array(
        'slug' => $puzzle_data[0],
        'round' => $round_name,
        'solved' => $puzzle_data[1] !== '',
        'answer' => $puzzle_data[1],
        'is_meta' => $puzzle_data[2] === '1',
      );
    }
  }

  $discrepancies = array();
  foreach ($fullhunt as $round) {
    if ($round->name == 'Events') {
      continue;
    }
    foreach ($round->puzzles as $puzzle) {
      if ($puzzle->status == '[hidden]') {
        continue;
      }
      $slug = strtolower($puzzle->name);
      $prefix = 'Puzzle '.$puzzle->name.':';
      if (!array_key_exists($slug, $comparison)) {
        $discrepancies[] = sprintf(
          '%s Could not find by URL exactly from the /puzzles name',
          $prefix,
        );
        continue;
      }
      $official_puzzle = $comparison[$slug];
      if ($official_puzzle['round'] != $round->name) {
        $discrepancies[] = sprintf(
          '%s Round mismatch, <tt>%s</tt> (MH) vs. <tt>%s</tt> (PB)',
          $prefix,
          $official_puzzle['round'],
          $round->name,
        );
      }
      if ($official_puzzle['solved'] != ($puzzle->status == 'Solved')) {
        $discrepancies[] = sprintf(
          '%s Solved mismatch, <tt>%s</tt> (MH) vs. <tt>%s</tt> (PB)',
          $prefix,
          $official_puzzle['solved'] ? 'true' : 'false',
          $puzzle->status,
        );
      }
      if (str_replace(' ', '', $official_puzzle['answer']) != str_replace(' ', '', $puzzle->answer)) {
        $discrepancies[] = sprintf(
          '%s Answer mismatch, <tt>%s</tt> (MH) vs. <tt>%s</tt> (PB)',
          $prefix,
          $official_puzzle['answer'],
          $puzzle->answer,
        );
      }
      if ($official_puzzle['is_meta'] != ($round->meta_id == $puzzle->id)) {
        $discrepancies[] = sprintf(
          '%s IsMeta mismatch, <tt>%s</tt> (MH) vs. <tt>%s</tt> (PB)',
          $prefix,
          $official_puzzle['is_meta'] ? 'true' : 'false',
          $round->meta_id == $puzzle->id ? 'true' : 'false',
        );
      }
      unset($comparison[$slug]);
    }
  }
  // Iterate over leftover puzzles
  foreach ($comparison as $official_puzzle) {
    $discrepancies[] = sprintf(
      '[MISSING] Puzzle %s not found in PB! Make sure it\'s added to round %s.',
      $official_puzzle['slug'],
      $official_puzzle['round'],
    );
  }
  if (count($discrepancies) === 0) {
    echo '<div class="success">No issues found! PB is up to date.</div>';
  } else {
    echo '<div class="error"><h3>Discrepancies between Puzzleboss and Mystery Hunt:</h3><ul>';
    foreach ($discrepancies as $discrepancy) {
      echo "<li>$discrepancy</li>";
    }
    echo '</ul></div>';
  }
}
?>
<?= $wifi_warning ?>
You are: <?= $username ?><br>
<a href="status.php">Hunt Status Overview / Puzzle Suggester</a><br>
<?php
$unsolved_rounds = array();
$solved_rounds = array();
foreach ($fullhunt as $round) {
  if (str_ends_with($round->round_uri, '#solved')) {
    $solved_rounds[] = $round;
  } else {
    $unsolved_rounds[] = $round;
  }
}
print_rounds_table($unsolved_rounds);

if (count($solved_rounds) > 0) {
  echo '<details><summary>Show solved rounds:</summary>';
  print_rounds_table($solved_rounds);
  echo '</details>';
}
?>
<br>
<a href="pbtools.php">Puzzleboss Admin Tools (e.g. add new round)</a>
<br><h3>Legend:</h3>
<table>
  <tr bgcolor="Gainsboro"><td><?= $use_text ? '.' : '🆕' ?></td><td>Meta Puzzle</td></tr>
  <tr bgcolor="aquamarine"><td><?= $use_text ? '.' : '🆕' ?></td><td>Open Puzzle</td></tr>
  <tr bgcolor="HotPink"><td><?= $use_text ? '!' : '⚠️' ?></td><td>Critical Puzzle</td></tr>
  <tr><td><?= $use_text ? 'O' : '🙇' ?></td><td>Puzzle Being Worked On</td></tr>
  <tr><td><?= $use_text ? '*' : '✅' ?></td><td>Solved Puzzle</td></tr>
  <tr><td><?= $use_text ? '?' : '☢️' ?></td><td>WTF Puzzle</td></tr>
  <tr><td><?= $use_text ? 'E' : '👀' ?></td><td>Puzzle Needs Eyes</td></tr>
  <tr><td><?= $use_text ? 'X' : '😶‍🌫️' ?></td><td>Puzzle Not Needed</td></tr>
  <tr style="text-decoration:underline overline wavy;"><td>&nbsp</td><td>My Current Puzzle</td></tr>
</table>
<br>
<br>
<a href="?text_only=1">Text-only (no emoji) mode</a>
</body>
