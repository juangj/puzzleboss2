<html>
<head><title>Add Puzzle</title></head><body>
<?php

require('puzzlebosslib.php');

if (isset($_GET['submit'])) {
    $name = $_GET['name'];
    $round_id = $_GET['round_id'];
    $puzzle_uri = $_GET['puzzle_uri'];
    
    echo 'Attempting to add puzzle.<br>';
    echo "name: $name<br>";
    echo "round_id: $round_id<br>";
    echo "puzzle_uri: $puzzle_uri<br>";
    
    
    $apiurl = "/puzzles";
    $data = json_encode(array(
        'name' => $name,
        'round_id' => $round_id,
        'puzzle_uri' => $puzzle_uri,
    ));
    
    echo "<br> Submitting API request to add puzzle.  May take a few seconds.<br>";
    $resp = postapi($apiurl, $data);
    $responseobj = json_decode($resp);
    
    echo '<br>';
    if (is_string($responseobj)){
        echo 'ERROR: Response from API is ' . var_dump($resp);
        echo '</body></html>';
    }
    foreach($responseobj as $key => $value){
        if ($key == "status") {
            if ($responseobj->status == "ok") {
                echo 'OK.  Puzzle created with ID of ' . $responseobj->puzzle->id;
            }
            else {
                echo 'ERROR: Response from API is ' . var_dump($resp);
            }
        }
        if ($key == "error") {
            echo 'ERROR: ' . $value; 
        }
    }
    die();
}

$puzzurl = "";
$puzzid = "";
if (isset($_GET['puzzurl'])) {
    $puzzurl = $_GET['puzzurl'];
}
if (isset($_GET['puzzid'])) {
    $puzzid = $_GET['puzzid'];
}

$resp = readapi("/rounds");
$rounds = json_decode($resp)->rounds;
?>

Add a puzzle!
<form action="addpuzzle.php" method="get">
Name:<input type="text" name="name" required value="<?= $puzzid ?>" />
<br>

Round:<select id="round_id" name="round_id"/>
<?
foreach ($rounds as $round) {
    echo '<option value="' . $round->id . '">' . $round->name . '</option>';
}
?>
</select><br>

Puzzle URI:
<input type="text" name="puzzle_uri" required value="<?= $puzzurl ?>" />
<br>

<input type="submit" name="submit" />
</form>
</body>
</html>
