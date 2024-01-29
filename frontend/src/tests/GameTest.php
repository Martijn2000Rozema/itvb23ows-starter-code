<?php
use Classes\DatabaseHandler;
use Classes\Game;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Stub;

class GameTest extends TestCase
{
    private Game $hive;
    private Stub $dbStub;

    protected function setUp(): void {
        $dbStub = self::createStub(DatabaseHandler::class);
        $dbStub->method("restartGame")->willReturn(0);
        $this->dbStub = $dbStub;

        $this->hive = new Game($this->dbStub);
    }

    public function test_GetAvailablePieces_StartOfGame_ShouldReturnAllPieces(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $availablePieces = $this->hive->getAvailablePieces();

        # Assert
        self::assertEquals(["A", "B", "G", "Q", "S"], $availablePieces);
    }

    public function test_GetAvailablePieces_AfterPlacingAllSpiders_ShouldReturnAllPiecesButSpider(): void {
        # Arrange
        $this->hive->restart();

        # Act
        //Player 1 plays first turn.
        $this->hive->play("0,0", "S");
        //Player 2 plays first turn.
        $this->hive->play("0,1", "S");
        //Player 1 plays second turn.
        $this->hive->play("-1,0", "S");
        //Player 2 plays second turn.
        $this->hive->play("0,2", "S");
        //Get all available pieces from player 1.
        $playerOnePieces = $this->hive->getAvailablePieces();
        $this->hive->play("-1,-1", "Q");
        //Get all available pieces from player 1.
        $playerTwoPieces = $this->hive->getAvailablePieces();

        # Assert
        self::assertEquals(["A", "B", "G", "Q"], $playerOnePieces);
        self::assertEquals(["A", "B", "G", "Q"], $playerTwoPieces);
    }

    public function test_GetValidMoves_FirstTurnPlayerOne_ShouldReturnZeroZeroOnly(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $validMoves = $this->hive->getValidPlayMoves();

        # Assert
        self::assertEquals(["0,0"], $validMoves);
    }

    public function test_GetValidMoves_FirstTurnPlayerTwo_ShouldReturnPositionsAroundZeroZero(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $this->hive->play("0,0", "S");
        $this->hive->updateSession();
        $validMoves = $this->hive->getValidPlayMoves();

        # Assert
        self::assertEquals(["0,1", "0,-1", "1,0", "-1,0"], $validMoves);
    }

    public function test_GetOccupiedPositions_NoPlaysDoneByPlayer_ShouldReturnEmptyArray(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $positionPlayerOne = $this->hive->getOccupiedPositions();
        $this->hive->play("0,0", "S");
        $this->hive->updateSession();
        $positionPlayerTwo = $this->hive->getOccupiedPositions();

        # Assert
        self::assertEquals([], $positionPlayerOne);
        self::assertEquals([], $positionPlayerTwo);
    }

    public function test_GetOccupiedPositions_AfterMultiplePlays_ShouldReturnOwnedPositionsOnly(): void {
        # Arrange
        $this->hive->restart();

        # Act
        //Player 1 plays first turn.
        $this->hive->play("0,0", "S");
        //Player 2 plays first turn.
        $this->hive->play("0,1", "S");
        //Player 1 plays second turn.
        $this->hive->play("-1,0", "S");
        //Player 2 plays second turn.
        $this->hive->play("0,2", "S");
        $this->hive->updateSession();
        $positionPlayerOne = $this->hive->getOccupiedPositions();

        $this->hive->play("-1,-1", "Q");
        $this->hive->updateSession();
        $positionPlayerTwo = $this->hive->getOccupiedPositions();

        # Assert
        self::assertEquals(["0,0", "-1,0"], $positionPlayerOne);
        self::assertEquals(["0,1", "0,2"], $positionPlayerTwo);
    }

    public function test_Undo_AfterMultiplePlays_ShouldReturnPreviousBoard(): void {
        # Arrange
        $this->hive->restart();

        # Act
        //Player 1 plays first turn.
        $this->hive->play("0,0", "S");
        //Player 2 plays first turn.
        $this->hive->play("0,1", "S");
        //Player 1 plays second turn.
        $this->hive->play("-1,0", "S");
        $board1 = $_SESSION["board"];
        $player1 = $_SESSION["player"];
        //Player 2 plays second turn.
        $this->hive->play("0,2", "S");

        $this->hive->undo();

        $board2 = $_SESSION["board"];
        $player2 = $_SESSION["player"];

        # Assert
        self::assertEquals($board1, $board2);
        self::assertEquals($player1, $player2);
    }

    public function test_Undo_OnFirstTurn_WillSetError(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $this->hive->undo();
        $this->hive->updateSession();

        # Assert
        self::assertEquals("You have not yet played a move.", $_SESSION["error"]);
    }

    public function test_Play_AfterMovementHasHappened_AllowPlacingInEmptyPosition(): void {
        # Arrange
        $this->hive->restart();

        # Act
        //Player 1 plays first turn.
        $this->hive->play("0,0", "B");
        //Player 2 plays first turn.
        $this->hive->play("1,0", "B");
        //Player 1 moves their piece.
        $this->hive->move("0,0", "-1,1");

        //Player 2 plays a stone in the original position of player 1 turn 1.
        $this->hive->play("0,0", "B");

        # Assert
        self::assertEquals(null, $_SESSION["error"]);
    }

    public function test_Play_QueensPlayedOnFirstTurnAndTouch_ShouldBeAllowed(): void {
        # Arrange
        $this->hive->restart();

        # Act
        //Player 1 plays first turn.
        $this->hive->play("0,0", "Q");
        //Player 2 plays first turn.
        $this->hive->play("1,0", "Q");

        $this->hive->updateSession();


        # Assert
        $expected = array(
            "0,0" => array(
                array(
                    0,
                    "Q"
                )
            ),
            "1,0" => array(
                array(
                    1,
                    "Q"
                )
            )
        );

        self::assertEquals($expected, $_SESSION["board"]);
        self::assertEquals(null, $_SESSION["error"]);
    }

    public function test_Play_DoThreeTurnsForEachUserWithoutPlayingQueen_GetError(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $this->hive->play("0,0", "A");
        $this->hive->play("0,1", "A");
        $this->hive->play("-1,0", "A");
        $this->hive->play("0,2", "A");
        $this->hive->play("-1,-1", "A");
        $this->hive->play("0,3", "A");
        $this->hive->play("-2,-1", "B");

        $this->hive->updateSession();


        # Assert
        self::assertEquals("The queen bee has to be played this turn.", $_SESSION["error"]);
    }

    public function test_Play_DoThreeTurnsForEachUserWhilePlayingQueen_DoesntGetError(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $this->hive->play("0,0", "A");
        $this->hive->play("0,1", "A");
        $this->hive->play("-1,0", "A");
        $this->hive->play("0,2", "A");
        $this->hive->play("-1,-1", "A");
        $this->hive->play("0,3", "A");
        $this->hive->play("-2,-1", "Q");
        $this->hive->updateSession();

        # Assert
        self::assertEquals(null, $_SESSION["error"]);
    }

    public function test_Move_MoveSpider_DoesntGetError(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $this->hive->play("0,0", "A");
        $this->hive->play("1,0", "A");
        $this->hive->play("0,-1", "A");
        $this->hive->play("2,0", "A");
        $this->hive->play("0,-2", "A");
        $this->hive->play("3,0", "S");
        $this->hive->play("0,-3", "Q");
        $this->hive->play("2,1", "Q");
        $this->hive->play("0,-4", "B");
        $this->hive->move("3,0", "-1,0");

        # Assert
        self::assertEquals(null, $_SESSION["error"]);
    }

    public function test_Move_AntCanMoveAroundHive_DoesntGetError(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $this->hive->play("0,0", "A");
        $this->hive->play("0,1", "A");
        $this->hive->play("-1,0", "A");
        $this->hive->play("0,2", "A");
        $this->hive->play("-1,-1", "A");
        $this->hive->play("0,3", "A");
        $this->hive->play("-2,0", "Q");
        $this->hive->play("0,4", "Q");
        $this->hive->move("-1,-1", "-1,0");

        # Assert
        self::assertEquals(null, $_SESSION["error"]);
    }

    public function test_IsGameOver_QueenNotSurrounded_ShouldReturnFalse(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $this->hive->play("0,0", "Q");
        $this->hive->play("0,1", "A");
        $this->hive->play("-1,0", "A");
        $this->hive->play("0,2", "A");
        $this->hive->updateSession();
        $gameStatus = $this->hive->getGameStatus();

        self::assertEquals(0, $gameStatus);
    }

    public function test_IsGameOver_QueenSurrounded_ShouldReturnTrue(): void {
        # Arrange
        $this->hive->restart();

        # Act
        $this->hive->play("0,0", "Q");
        $_SESSION["board"] = [
            "0,1" => [[1, "S"]],
            "0,-1" => [[1, "S"]],
            "-1,1" => [[1, "A"]],
            "-1,-1" => [[1, "A"]],
            "1,1" => [[1, "A"]],
        ];
        $this->hive->initializeGame();
        $this->hive->play("1,-1", "G");
        $this->hive->updateSession();

        # Assert
        self::assertEquals(2, $this->hive->getGameStatus());
    }
}
