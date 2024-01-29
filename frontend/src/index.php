<?php
require_once __DIR__ . '/vendor/autoload.php';

use Classes\Game;

session_start();

$hiveGame = new Game();

if (!isset($_SESSION['board'])) {
    $hiveGame->restart();
}

$hiveGame->executeAction();

$board = $_SESSION['board'];

$player = $_SESSION['player'];

$playerOne = $hiveGame->getHand(0);
$playerTwo = $hiveGame->getHand(1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Hive</title>
    <link rel="stylesheet" href="styling/hive.css"
</head>
<body>
    <div class="board">
        <?php
        $minP = 1000;
        $minQ = 1000;
        foreach ($board as $pos => $tile) {
            [$p, $q] = explode(',', $pos);
            $minP = min($p, $minP);
            $minQ = min($p, $minQ);
        }
        foreach (array_filter($board) as $pos => $tile) {
            echo $hiveGame->buildTile($pos, $tile, $minP, $minQ);
        }
        ?>
    </div>
    <div class="hand">
        White:
        <?php
        foreach ($playerOne as $tile => $ct) {
            for ($i = 0; $i < $ct; $i++) {
                echo "<div class='tile player0'><span>$tile</span></div>&nbsp";
            }
        }
        ?>
    </div>
    <div class="hand">
        Black:
        <?php
        foreach ($playerTwo as $tile => $ct) {
            for ($i = 0; $i < $ct; $i++) {
                echo "<div class='tile player1'><span>$tile</span></div>&nbsp";
            }
        }
        ?>
    </div>
    <div class="turn">
        Turn:
        <?= $player === 0 ? "White" : "Black"; ?>
    </div>
    <?php if ($_SESSION["game_status"] == 0) { ?>
        <form method="post">
            <select name="piece">
                <?php
                foreach ($hiveGame->getAvailablePieces() as $tile) {
                    echo "<option value='$tile'>$tile</option>";
                }
                ?>
            </select>
            <select name="pos">
                <?php
                foreach ($hiveGame->getValidPlayMoves() as $pos) {
                    echo "<option value='$pos'>$pos</option>";
                }
                ?>
            </select>
            <button type="submit" name="action" value="play">Play</button>
        </form>
        <?php if ($_SESSION["turn_counter"] > 1) { ?>
            <form method="post">
                <select name="from">
                    <?php
                    foreach ($hiveGame->getOccupiedPositions() as $pos) {
                        echo "<option value='$pos'>$pos</option>";
                    }
                    ?>
                </select>
                <select name="to">
                    <?php
                    foreach ($hiveGame->getBoundaries() as $pos) {
                        echo "<option value=\"$pos\">$pos</option>";
                    }
                    ?>
                </select>
                <button type="submit" name="action" value="move">Move</button>
            </form>
            <form method="post">
                <button type="submit" name="action" value="pass">Pass</button>
            </form>
        <?php } ?>
        <form method="post">
            <button type="submit" name="action" value="undo">Undo</button>
        </form>
    <?php } ?>
    <form method="post">
        <button type="submit" name="action" value="ai_play">AI Play</button>
    </form>
    <form method="post">
        <button type="submit" name="action" value="restart">Restart</button>
    </form>
    <strong class="error">
        <?= $_SESSION["error"] ?>
    </strong>
    <strong>
        <?php
        if ($_SESSION["game_status"] == 1) {
            echo "White has won!";
        } elseif ($_SESSION["game_status"] == 2) {
            echo "Black has won!";
        } elseif ($_SESSION["game_status"] == 3) {
            echo "The game ended in a draw!";
        }
        ?>
    </strong>
</body>
</html>
