<?php

namespace Classes;

use Exception;
use mysqli;

class DatabaseHandler
{
    private mysqli $conn;
    private string $hostname = 'localhost';
    private string $username = 'root';
    private string $password = 'root';
    private string $database = 'hive';

    /**
     * Executes a move action in the Hive game.
     *
     * @param int    $gameId The ID of the game.
     * @param string $fromPos The position from which the move is made.
     * @param string $toPos The position to which the move is made.
     * @param int    $prevId The ID of the previous move.
     * @param string $state The state of the game.
     *
     * @return int The ID of the newly inserted move.
     */
    public function doMove(int $gameId, string $fromPos, string $toPos, int $prevId, string $state): int {
        return $this->doAction($gameId, "move", $fromPos, $toPos, $prevId, $state);
    }

    /**
     * Restarts the Hive game by creating a new game instance in the database.
     *
     * @return int The ID of the newly created game instance.
     */
    public function restartGame(): int {
        $db = $this->getConnection();
        $stmt = $db->prepare("INSERT INTO games () VALUES ();");
        $stmt->execute();

        return $db->insert_id;
    }

    /**
     * Executes a generic action in the Hive game.
     *
     * @param int         $gameId The ID of the game.
     * @param string      $action The type of action (e.g., "move", "pass").
     * @param string|null $fromPos The position from which the move is made (null for pass action).
     * @param string|null $toPos The position to which the move is made (null for pass action).
     * @param int | null         $prevId The ID of the previous move.
     * @param string      $state The state of the game.
     *
     * @return int The ID of the newly inserted move.
     */
    private function doAction(
        int $gameId,
        string $action,
        string | null $fromPos,
        string | null $toPos,
        int | null $prevId,
        string $state
    ): int {
        $db = $this->getConnection();
        $cmd = "INSERT INTO moves (game_id, type, move_from, move_to, previous_id, state) VALUES (?, ?, ?, ?, ?, ?);";
        $stmt = $db->prepare($cmd);
        $stmt->bind_param("issiis", $gameId, $action, $fromPos, $toPos, $prevId, $state);
        $stmt->execute();

        return $db->insert_id;
    }

    /**
     * Executes a pass action in the Hive game.
     *
     * @param int $gameId The ID of the game.
     * @param int    $prevId The ID of the previous move.
     * @param string $state The state of the game.
     *
     * @return int The ID of the newly inserted move.
     */
    public function doPass(int $gameId, int $prevId, string $state): int {
        return $this->doAction($gameId, "pass", null, null, $prevId, $state);
    }

    /**
     * Adds a play move action in the Hive game.
     *
     * @param int    $gameId The ID of the game.
     * @param string $piece  The piece being played.
     * @param string $toPos  The position to which the piece is played.
     * @param int | null    $prevId The ID of the previous move.
     * @param string $state  The state of the game.
     *
     * @return int The ID of the newly inserted move.
     */
    public function addMove(int $gameId, string $piece, string $toPos, int | null $prevId, string $state): int {
        return $this->doAction($gameId, "play", $piece, $toPos, $prevId, $state);
    }

    /**
     * Undoes a specific move in the Hive game based on the provided move ID.
     *
     * @param int $moveId The identifier of the move to be undone.
     *
     * @return string The serialized state representing the game state before the move.
     *
     * @throws Exception If the undo operation fails.
     */
    public function undoMove(int $moveId): string {
        $db = $this->getConnection();

        // Prepare the SELECT statement
        $selectCmd = "SELECT * FROM moves WHERE id = ?";
        $stmt = $db->prepare($selectCmd);
        $stmt->bind_param("i", $moveId);

        // Execute the SELECT statement
        if ($stmt->execute()) {
            $result = $stmt->get_result();

            $data = $result->fetch_assoc();

            $result->close();

            $deleteCmd = "DELETE FROM moves WHERE id = ?";
            $deleteStmt = $db->prepare($deleteCmd);
            $deleteStmt->bind_param("i", $moveId);
            $deleteStmt->execute();

            return $data["state"];
        }
        throw new Exception("Cold not undo move");
    }


    /**
     * Gets the database connection for the Hive game.
     *
     * @return mysqli The MySQLi database connection.
     */
    private function getConnection(): mysqli {
        if (!isset($this->conn)) {
            $conn = new mysqli($this->hostname, $this->username, $this->password, $this->database);

            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            $this->conn = $conn;
        }
        return $this->conn;
    }
}
