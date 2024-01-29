<?php

namespace Classes;

$GLOBALS['OFFSETS'] = [[0, 1], [0, -1], [1, 0], [-1, 0], [-1, 1], [1, -1]];

class Game
{
    private DatabaseHandler $databaseHandler;

    private int $gameId;
    private int $player;
    private array $hand;
    private array $board;
    private int | null $prevMoveId;
    private int $turnCounter;
    private string | null $error;
    private int $gameStatus = 0;

    public function __construct(DatabaseHandler $databaseHandler = null) {
        $this->databaseHandler = $databaseHandler ?? new DatabaseHandler();
    }

    public function executeAction(): void {
        if (!isset($_POST["action"])) {
            return;
        }
        $this->initializeGame();

        $this->error = null;

        switch ($_POST["action"]) {
            case "play":
                $pos = $_POST["pos"];
                $piece = $_POST["piece"];
                $this->play($pos, $piece);
                break;
            case "move":
                $fromPos = $_POST["from"];
                $toPos = $_POST["to"];
                $this->move($fromPos, $toPos);
                break;
            case "pass":
                $this->pass();
                break;
            case "undo":
                $this->undo();
                break;
            case "restart":
                $this->restart();
                $this->reloadPage();
                return;
            case "ai_play":
                $this->aiPlay();
                break;
            default:
                $this->reloadPage();
                return;
        }

        $this->updateSession();
        $this->reloadPage();
    }

    /**
     * Plays a piece on the specified position on the board.
     *
     * @param string $pos   Coordinates of the board position where the piece is to be played (in the format "x,y").
     * @param string $piece The piece to be played.
     * @return void
     */
    public function play(string $pos, string $piece): void {
        $hand = $this->getHand();
        if (isset($this->board[$pos])) {
            $this->setError("The board position is already in use.");
        } elseif (!isset($hand[$piece]) || $hand[$piece] <= 0) {
            $this->setError("You don't have this piece available.");
        } elseif (count($this->board) > 0 && !$this->hasNeighbour($pos)) {
            $this->setError("The board position has no neighboring cells.");
        } elseif ($piece != "Q" && $this->turnCounter >= 6 && (($hand["Q"] ?? 0) != 0)) {
            $this->setError("The queen bee has to be played this turn.");
        } elseif (array_sum($hand) < 11 && !$this->neighboursAreSameColor($this->player, $pos)) {
            $this->setError("The board position is adjacent to an opposing piece, this is not possible.");
        } else {
            $this->hand[$this->player][$piece]--;
            $this->board[$pos] = [[$this->player, $piece]];
            $this->prevMoveId = $this->databaseHandler->
                addMove($this->gameId, $piece, $pos, $this->prevMoveId, $this->getSerializedState());
            $this->turnCounter++;
            $this->gameStatus = $this->getGameStatus();
            $this->player = ($this->player + 1) % 2;
        }
    }

    /**
     * Check if a position has a neighboring piece on the board.
     *
     * @param string $a     The position to check for neighbors.
     *
     * @return bool True if the position has at least one neighbor; false otherwise.
     */
    private function hasNeighbour(string $a): bool {
        foreach (array_keys($this->board) as $b) {
            if ($this->isNeighbour($a, $b)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a given position has a neighbor of the same color on the provided board.
     *
     * @param string $fromPos The position to check for same-color neighbors.
     * @param array $board The board representing the goal state of the move.
     * @return bool Returns true if a same-color neighbor is found, false otherwise.
     */
    private function hasSameColorNeighbour(string $fromPos, array $board): bool {
        foreach ($board as $pos => $pieces) {
            if (!$pieces) {
                continue;
            }

            $player = $pieces[count($pieces) - 1][0];

            if ($player == $this->player && $this->isNeighbour($pos, $fromPos)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if two given board positions are neighbors on the hive board.
     *
     * @param string $a The coordinates of the first board position (in the format "x,y").
     * @param string $b The coordinates of the second board position (in the format "x,y").
     * @return bool Returns true if the two board positions are neighbors, false otherwise.
     */
    private function isNeighbour(string $a, string $b): bool {
        [$x1, $y1] = explode(",", $a);
        [$x2, $y2] = explode(",", $b);

        return ($x1 == $x2 && abs($y1 - $y2) == 1) ||
            ($y1 == $y2 && abs($x1 - $x2) == 1) ||
            ((int)$x1 + (int)$x2 == (int)$y1 + (int)$y2);
    }

    /**
     * Checks if the neighbors of a specified position on the board have the same color as the given player.
     *
     * @param int         $player The player's color to compare with neighbors.
     * @param string      $a      Coordinates of the specified position (in the format "x,y").
     * @param array|null  $board  The game board (optional, defaults to the class property $this->board).
     *
     * @return bool Returns true if neighbors have the same color as the player, false otherwise.
     */
    private function neighboursAreSameColor(int $player, string $a, array | null $board = null): bool {
        $board = $board ?? $this->board;
        foreach ($board as $b => $st) {
            if (!$st) {
                //No piece placed in this position
                continue;
            }

            //Check top of the stack for user
            $c = $st[count($st) - 1][0];

            if ($c != $player && $this->isNeighbour($a, $b)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Retrieves the hand of the specified player or the current player if no player is specified.
     *
     * @param int|null $player (Optional) The player whose hand is to be retrieved.
     * If not provided, the current player's hand is returned.
     * @return array Returns an associative array representing the hand of the specified player or
     * the current player.
     */
    public function getHand(int | null $player = null): array {
        if (!isset($this->hand) || (!isset($this->player) && is_null($player))) {
            $this->initializeGame();
        }

        return $this->hand[$player ?? $this->player];
    }

    /**
     * Sets an error message for the current game instance.
     *
     * @param string $msg The error message to be set.
     *
     * @return void
     */
    private function setError(string $msg): void {
        $this->error = $msg;
    }

    /**
     * Passes the current turn in the Hive game.
     *
     * @return void
     */
    private function pass(): void {
        if (!$this->canPass()) {
            $this->setError("You can not pass this turn.");
            return;
        }

        $this->prevMoveId = $this->databaseHandler->
            doPass($this->gameId, $this->prevMoveId, $this->getSerializedState());
        $this->turnCounter++;
        $this->player = ($this->player + 1) % 2;
    }

    /**
     * Builds HTML code for a tile based on its position and attributes.
     *
     * @param string $pos    The position of the tile.
     * @param array  $tiles  An array representing the layers of the tile.
     * @param int    $minP   The minimum value for the x-coordinate.
     * @param int    $minQ   The minimum value for the y-coordinate.
     *
     * @return string HTML code for the tile.
     */
    public function buildTile(string $pos, array $tiles, int $minP, int $minQ): string {
        $pq = explode(',', $pos);
        $tileCount = count($tiles);
        $topTile = $tiles[$tileCount-1];
        $player = $topTile[0];
        $piece = $topTile[1];

        $class = $tileCount > 1 ? "tile player$player stacked" : "tile player$player";
        $left = (($pq[0] - $minP) * 4 + ($pq[1] - $minQ) * 2)."em;";
        $top = (($pq[1] - $minQ) * 4)."em;";

        return "<div class='$class' style='left: $left top: $top'>
                    $pos
                    <span>
                        $piece
                    </span>
                </div>";
    }

    /**
     * Retrieves an array of available pieces for the current player from their hand.
     *
     * @return array An array containing the keys of available pieces (pieces with a count greater than 0).
     */
    public function getAvailablePieces(): array {
        $availablePieces = [];
        foreach ($this->getHand() as $key => $value) {
            if ($value > 0) {
                $availablePieces[] = $key;
            }
        }

        sort($availablePieces);

        return $availablePieces;
    }

    /**
     * Retrieves the valid play moves for the current player based on the Hive game rules.
     *
     * @return array Returns an array containing the valid positions on the board where the current
     * player can place a piece.
     */
    public function getValidPlayMoves(): array {
        $this->initializeGame();

        $validMoves = [];
        foreach ($GLOBALS["OFFSETS"] as $offset) {
            foreach (array_keys($this->board) as $pos) {
                [$x1, $y1] = [$offset[0], $offset[1]];
                [$x2, $y2] = explode(",", $pos);

                $newPos = ($x1 + (int)$x2) . "," . ($y1 + (int)$y2);

                if ($this->turnCounter === 0 ||
                    (!array_key_exists($newPos, $this->board) &&
                        $this->hasNeighbour($newPos) &&
                        ($this->turnCounter === 1 ||
                            $this->neighboursAreSameColor($this->player, $newPos)))) {
                    $validMoves[] = $newPos;
                }
            }
        }

        return !empty($validMoves) ? array_unique($validMoves) : ["0,0"];
    }

    /**
     * Retrieves the positions on the board that are occupied by pieces of the current player.
     *
     * @return array Returns an array containing the positions on the board where pieces of the
     * current player are placed.
     */
    public function getOccupiedPositions(): array {
        $this->initializeGame();

        $positions = [];

        foreach ($this->board as $pos => $move) {
            if (!$move) {
                //No piece placed in this position
                continue;
            }

            $player = $move[count($move) - 1][0];

            if ($player === $this->player) {
                $positions[] = $pos;
            }
        }
        return $positions;
    }

    /**
     * Retrieves an array of all positions within the boundaries of the current game board.
     *
     * @return array An array containing all positions within the boundaries.
     */
    public function getBoundaries(): array {
        if (!isset($this->board)) {
            $this->initializeGame();
        }

        $minX = PHP_INT_MAX;
        $maxX = PHP_INT_MIN;
        $minY = PHP_INT_MAX;
        $maxY = PHP_INT_MIN;

        foreach ($this->board as $position => $values) {
            [$x, $y] = array_map('intval', explode(',', $position));

            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
        }

        $positions = [];

        for ($i = $minX - 1; $i <= $maxX + 1; $i++) {
            for ($j = $minY - 1; $j <= $maxY + 1; $j++) {
                $positions[] = "$i,$j";
            }
        }

        return $positions;
    }

    /**
     * Reloads the current page by sending a Location header to the client, redirecting to the same page.
     * This method is used for refreshing the user interface after certain game actions or updates.
     */
    private function reloadPage(): void {
        header("Location: index.php");
    }

    /**
     * Undoes the last move in the Hive game.
     *
     * @return void
     */
    public function undo(): void {
        if ($this->prevMoveId == null) {
            $this->setError("You have not yet played a move.");
            return;
        }

        try {
            $this->resetPrevState($this->databaseHandler->undoMove($this->prevMoveId));
        } catch (\Exception $exception) {
            $this->setError($exception->getMessage());
        }
    }

    /**
     * Moves a piece on the Hive game board from one position to another.
     *
     * @param string $fromPos The starting position of the piece.
     * @param string $toPos   The target position to move the piece to.
     *
     * @return void
     */
    public function move(string $fromPos, string $toPos): void {
        $hand = $this->getHand();

        if (!isset($this->board[$fromPos])) {
            $this->setError("The board position does not have a piece.");
        } elseif ($this->turnCounter >= 6 && (($hand["Q"] ?? 0) != 0)) {
            $this->setError("The queen bee has to be played this turn.");
        } elseif ($this->board[$fromPos][count($this->board[$fromPos])-1][0] != $this->player) {
            $this->setError("This piece is not owned by you.");
        } else {
            $currentEntries = $this->board[$fromPos];
            $piece = $currentEntries[count($currentEntries)-1][1];

            switch ($piece) {
                case "Q":
                    if (!$this->canQueenMove($fromPos, $toPos)) {
                        $this->setError("Queen can not be moved.");
                        return;
                    }
                    break;
                case "B":
                    if (!$this->canBeetleMove($fromPos, $toPos)) {
                        $this->setError("Beetle can not be moved.");
                        return;
                    }
                    break;
                case "G":
                    if (!$this->canGrasshopperMove($fromPos, $toPos)) {
                        $this->setError("Grasshopper can not be moved.");
                        return;
                    }
                    break;
                case "S":
                    if (!$this->canSpiderMove($fromPos, $toPos)) {
                        $this->setError("Spider can not be moved.");
                        return;
                    }
                    break;
                case "A":
                    if (!$this->canAntMove($fromPos, $toPos)) {
                        $this->setError("Soldier ant can not be moved.");
                        return;
                    }
                    break;
                default:
                    $this->setError("Unknown piece played");
                    return;
            }

            if (!isset($this->board[$toPos])) {
                $this->board[$toPos] = [[$this->player, $piece]];
            } else {
                $target = $this->board[$toPos];
                $target[] = [$this->player, $piece];
                $this->board[$toPos] = $target;
            }

            if (count($currentEntries) == 1) {
                unset($this->board[$fromPos]);
            } else {
                $this->board[$fromPos] = array_slice($currentEntries, -1);
            }

            $this->prevMoveId = $this->databaseHandler->
                doMove($this->gameId, $fromPos, $toPos, $this->prevMoveId, $this->getSerializedState());
            $this->turnCounter++;
            $this->gameStatus = $this->getGameStatus();
            $this->player = ($this->player + 1) % 2;
        }
    }

    private function aiPlay(): void {
        $url = "http://hiveai:80";

        $options = [
            "http" => [
                "header" => "Content-Type: application/json\r\n",
                "method" => "POST",
                "content" => json_encode([
                    "move_number" => $this->turnCounter+1,
                    "hand" => $this->hand,
                    "board" => $this->board
                ]),
            ]
        ];

        $response = json_decode(file_get_contents($url, false, stream_context_create($options)));

        switch ($response[0]) {
            case "play":
                $this->play($response[1], $response[2]);
                break;
            case "move":
                $this->move($response[1], $response[2]);
                break;
            case "pass":
                $this->pass();
                break;
            default:
                $this->setError("AI played an unknown move.");
        }
    }

    /**
     * Checks if a movement is allowed for a Queen piece in the Hive game.
     *
     * @param string $fromPos The starting position of the Queen (in the format "x,y").
     * @param string $toPos   The target position for the Queen (in the format "x,y").
     *
     * @return bool Returns true if the movement is allowed, false otherwise.
     */
    private function canQueenMove(string $fromPos, string $toPos): bool {
        if (!$this->hasBeetleOnTop($fromPos) &&
            !isset($this->board[$toPos]) &&
            $this->isNeighbour($fromPos, $toPos)) {
            $board = $this->copyArray($this->board);
            unset($board[$fromPos]);
            $board[$toPos] = $this->board[$fromPos];

            return $this->canMove($board, $fromPos);
        }

        return false;
    }

    /**
     * Checks if a movement is allowed for a Beetle piece in the Hive game.
     *
     * @param string $fromPos The starting position of the Beetle (in the format "x,y").
     * @param string $toPos   The target position for the Beetle (in the format "x,y").
     *
     * @return bool Returns true if the movement is allowed, false otherwise.
     */
    private function canBeetleMove(string $fromPos, string $toPos): bool {
        if ($this->hasBeetleOnTop($fromPos) || !$this->isNeighbour($fromPos, $toPos)) {
            return false;
        }

        // Create a copy of the board
        $board = $this->copyArray($this->board);
        unset($board[$fromPos]);
        $board[$toPos] = $this->board[$fromPos];

        return $this->canMove($board, $fromPos);
    }

    /**
     * Checks if a Grasshopper piece can make a valid move from one position to another in the Hive game.
     *
     * @param string $fromPos The starting position of the Grasshopper.
     * @param string $toPos   The target position to move the Grasshopper to.
     *
     * @return bool Returns true if the move is valid, false otherwise.
     */
    private function canGrasshopperMove(string $fromPos, string $toPos): bool {
        if ($fromPos == $toPos) {
            return false;
        }

        [$x1, $y1] = (int)explode(',', $fromPos);
        [$x2, $y2] = (int)explode(',', $toPos);

        // Check if the grasshopper is jumping in a straight line
        if (!($x1 == $x2 || $y1 == $y2 || ($x1 + $y1) == ($x2 + $y2))) {
            return false;
        }

        // Check the entire jump path for obstacles or empty spaces
        $dx = ($x2 - $x1) <=> 0;
        $dy = ($y2 - $y1) <=> 0;

        for ($i = 1; $i < max(abs($x2 - $x1), abs($y2 - $y1)); $i++) {
            $x = $x1 + $i * $dx;
            $y = $y1 + $i * $dy;
            $pos = "$x,$y";

            if (isset($this->board[$pos])) {
                return false;
            }

            if (!isset($this->board[$pos]) && $pos !== $toPos) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a spider can move from the starting position to the target position within a specified number of steps,
     * considering a provided board configuration.
     *
     * @param string $fromPos The starting position of the spider (in the format "x,y").
     * @param string $toPos The target position the spider wants to reach (in the format "x,y").
     * @param int $steps The maximum number of steps the spider can take to reach the target position.
     * @param array|null $visited An array to keep track of visited positions during the recursive exploration.
     * @param array|null $board The board configuration to consider for the movement check. If not provided, the main board is used.
     * @return bool Returns true if the spider can reach the target position within the specified steps, false otherwise.
     */
    private function canSpiderMove(
        string $fromPos,
        string $toPos,
        int $steps = 2,
        array $visited = null,
        array $board = null
    ): bool {
        if ($steps === 0 && $fromPos == $toPos) {
            return true;
        }

        if ($fromPos == $toPos || $this->hasBeetleOnTop($fromPos)) {
            return false;
        }

        $board = $board ?? $this->board;

        $visited = $visited ?? [];
        $visited[$fromPos] = true;

        $directions = $GLOBALS['OFFSETS'];

        [$x, $y] = explode(',', $fromPos);

        foreach ($directions as [$dx, $dy]) {
            $newX = (int)$x + $dx;
            $newY = (int)$y + $dy;
            $newPos = "$newX,$newY";

            if (!$this->isNeighbour($fromPos, $newPos)) {
                continue;
            }

            if (!isset($board[$toPos])) {
                $board = $this->copyArray($this->board);

                unset($board[$fromPos]);

                // Check if neighbors of the target position have the same color as the player
                if (!$this->canMove($board, $fromPos)) {
                    continue;
                }
            }


            if (!isset($visited[$newPos]) && $steps > 0) {
                if ($this->canSpiderMove($newPos, $toPos, $steps - 1, $visited, $board)) {
                    return true;
                }
            }
        }

        unset($visited[$fromPos]);

        return false;
    }

    /**
     * Check if a soldier ant can move from one position to another on the Hive board.
     *
     * @param string         $fromPos The current position of the soldier ant.
     * @param string         $toPos   The target position for the soldier ant to move to.
     * @param array | null   $board   The current state of the Hive board (optional).
     *
     * @return bool True if the soldier ant can move to the target position, false otherwise.
     */
    private function canAntMove(string $fromPos, string $toPos, array | null $board = null): bool {
        if ($fromPos == $toPos ||
            !$this->isNeighbour($fromPos, $toPos) ||
            isset($this->board[$toPos])) {
            return false;
        }

        $directions = $GLOBALS["OFFSETS"];

        [$x, $y] = (int)explode(",", $fromPos);

        $boundaries = $this->getBoundaries();

        $board = $board ?? $this->board;

        foreach ($directions as [$dx, $dy]) {
            $newX = $x + $dx;
            $newY = $y + $dy;
            $newPos = "$newX,$newY";

            if (!in_array($newPos, $boundaries)) {
                continue;
            }

            $value = $board[$fromPos];

            unset($board[$fromPos]);

            if ($this->neighboursAreSameColor($this->player, $newPos, $board)) {
                if ($toPos == $newPos) {
                    return true;
                }

                $board[$newPos] = $value;
                return $this->canAntMove($newPos, $toPos, $board);
            }

            $board[$fromPos] = $value;
        }

        return false;
    }


    /**
     * Checks if a move is valid on the given board based on connectivity to existing pieces.
     *
     * @param array $board The board representing the goal state of the move.
     * @param string $fromPos The starting position of the move.
     * @return bool Returns true if the move is valid, false otherwise.
     */
    private function canMove(array $board, string $fromPos): bool {
        if (!$this->hasSameColorNeighbour($fromPos, $board)) {
            return false;
        }

        $all = array_keys($this->board);
        $queue = [array_shift($all)];
        while ($queue) {
            $next = explode(',', array_shift($queue));
            foreach ($GLOBALS['OFFSETS'] as $pq) {
                list($p, $q) = $pq;
                $p += $next[0];
                $q += $next[1];
                if (in_array("$p,$q", $all)) {
                    $queue[] = "$p,$q";
                    $all = array_diff($all, ["$p,$q"]);
                }
            }
        }

        if ($all) {
            return false;
        }
        return true;
    }

    /**
     * Checks if the current player can pass the turn in the Hive game.
     *
     * @return bool Returns true if the player can pass, false otherwise.
     */
    private function canPass(): bool {
        if (count($this->getHand()) > 0 ||
            count($this->getValidPlayMoves()) > 0) {
            return false;
        }

        foreach ($this->board as $fromPos => $items) {
            $player = $items[count($items)][0];

            if ($player != $this->player) {
                continue;
            }

            $piece = $items[count($items)][1];

            foreach ($this->getBoundaries() as $toPos) {
                switch ($piece) {
                    case "Q":
                        if ($this->canQueenMove($fromPos, $toPos)) {
                            return false;
                        }
                        break;
                    case "B":
                        if ($this->canBeetleMove($fromPos, $toPos)) {
                            return false;
                        }
                        break;
                    case "G":
                        if ($this->canGrasshopperMove($fromPos, $toPos)) {
                            return false;
                        }
                        break;
                    case "S":
                        if ($this->canSpiderMove($fromPos, $toPos)) {
                            return false;
                        }
                        break;
                    case "A":
                        if ($this->canAntMove($fromPos, $toPos)) {
                            return false;
                        }
                        break;
                    default:
                        return false;
                }
            }
        }
        return true;
    }

    /**
     * Checks if there is a beetle on top of a specified position on the board.
     *
     * @param string $pos Coordinates of the specified position (in the format "x,y").
     * @return bool Returns true if there is a beetle on top, false otherwise.
     */
    private function hasBeetleOnTop(string $pos): bool {
        if (!isset($this->board[$pos])) {
            return false;
        }

        $posValue = $this->board[$pos];

        $topOfStack = $posValue[count($posValue) - 1];



        if ($topOfStack[0] == $this->player) {
            return false;
        }

        return true;
    }


    /**
     * Restarts the game by resetting the board, player hands, and other relevant game state variables.
     * This method initializes a new game and updates the session with the updated state.
     */
    public function restart(): void {
        $this->board = [];
        $this->hand = [
            0 => ["Q" => 1, "B" => 2, "S" => 2, "A" => 3, "G" => 3],
            1 => ["Q" => 1, "B" => 2, "S" => 2, "A" => 3, "G" => 3]
        ];
        $this->player = 0;
        $this->gameId = $this->databaseHandler->restartGame();
        $this->error = null;
        $this->prevMoveId = null;
        $this->turnCounter = 0;
        $this->gameStatus = 0;

        $this->updateSession();
    }

    /**
     * Get the current status of the Hive game.
     *
     * @return int The game status code:
     *   - 0: Game in play
     *   - 1: Player 0 wins
     *   - 2: Player 1 wins
     *   - 3: Draw
     */
    public function getGameStatus(): int {
        $currentPlayer = $this->isGameOver($this->player);
        $opponent = $this->isGameOver(($this->player + 1) % 2);

        if ($currentPlayer && $opponent) {
            return 3;
        }

        if ($currentPlayer) {
            return 1;
        }

        if ($opponent) {
            return 2;
        }

        return 0;
    }

    /**
     * Check if the game is over for the specified player.
     *
     * @param int $player The player to check for game over (0 or 1).
     *
     * @return bool True if the player's queen is blocked in, indicating game over; otherwise, false.
     */
    private function isGameOver(int $player): bool {
        $queenPos = null;

        foreach ($this->board as $pos => $items) {
            foreach ($items as $item) {
                if ($item[0] == $player && $item[1] == "Q") {
                    $queenPos = $pos;
                    break;
                }
            }

            if ($queenPos == null) {
                return false;
            }
        }

        [$x1, $y1] = (int)explode(",", $queenPos);

        $directions = $GLOBALS["OFFSETS"];

        foreach ($directions as [$x2, $y2]) {
            $p = $x1 + $x2;
            $q = $y1 + $y2;

            $newPos = $p.",".$q;

            if (!array_key_exists($newPos, $this->board)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Initializes the game state by retrieving necessary information from the session.
     * This method should be called to set up the game state at the beginning of each request.
     */
    public function initializeGame(): void {
        $this->gameId = $_SESSION["game_id"];
        $this->player = $_SESSION["player"];
        $this->hand = $_SESSION["hand"];
        $this->board = $_SESSION["board"];
        $this->prevMoveId = $_SESSION["last_move"] ?? null;
        $this->turnCounter = $_SESSION["turn_counter"] ?? 0;
        $this->error = $_SESSION["error"] ?? null;
        $this->gameStatus = $_SESSION["game_status"] ?? 0;
    }

    /**
     * Updates the session with the current game state.
     * This method should be called to persist the game state in the session after each move.
     */
    public function updateSession(): void {
        $_SESSION["game_id"] = $this->gameId;
        $_SESSION["player"] = $this->player;
        $_SESSION["hand"] = $this->hand;
        $_SESSION["board"] = $this->board;
        $_SESSION["last_move"] = $this->prevMoveId;
        $_SESSION["turn_counter"] = $this->turnCounter;
        $_SESSION["error"] = $this->error;
        $_SESSION["game_status"] = $this->gameStatus;
    }

    /**
     * Creates a shallow copy of an array.
     *
     * @param array $array The array to be copied.
     *
     * @return array A shallow copy of the original array.
     */
    private function copyArray(array $array): array {
        return array_merge([], $array);
    }

    /**
     * Resets the game state to a previous state.
     *
     * @param string $state The serialized representation of the previous game state.
     *
     * @return void
     */
    private function resetPrevState(string $state): void {
        [$this->hand, $this->board, $this->player, $this->prevMoveId, $this->turnCounter] = unserialize($state);
    }


    /**
     * Returns the serialized state of the game.
     *
     * @return string The serialized state of the game, including hand, board, player,
     * previous move ID, and turn counter.
     */
    private function getSerializedState(): string {
        return serialize([$this->hand, $this->board, $this->player, $this->prevMoveId, $this->turnCounter]);
    }
}
