<?php

namespace App\Games\Chess\Engine;

/**
 * Chess positions and legal moves. Pure functions; no game flow or draw bookkeeping (see ChessEngine).
 *
 * A position is ['board' => 64 squares, 'turn' => 0 white | 1 black, 'castling' => 'KQkq' subset,
 * 'ep' => en passant target square or null, 'halfmove' => int, 'fullmove' => int]. Square index =
 * row * 8 + col with row 0 = rank 8, so a8 = 0 and h1 = 63. Pieces use FEN letters (white upper case).
 * A move is ['from' => int, 'to' => int] plus 'promotion' => 'q'|'r'|'b'|'n' for promotions; castling
 * is the king's two-square move.
 */
final class ChessRules
{
    public const START = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    public const KNIGHT = [[-2, -1], [-2, 1], [-1, -2], [-1, 2], [1, -2], [1, 2], [2, -1], [2, 1]];

    public const KING = [[-1, -1], [-1, 0], [-1, 1], [0, -1], [0, 1], [1, -1], [1, 0], [1, 1]];

    public const DIAGONAL = [[-1, -1], [-1, 1], [1, -1], [1, 1]];

    public const STRAIGHT = [[-1, 0], [1, 0], [0, -1], [0, 1]];

    /** Castling: right => [king from, king to, rook from, rook to, squares that must be empty, squares the king crosses]. */
    private const CASTLING = [
        'K' => [60, 62, 63, 61, [61, 62], [60, 61, 62]],
        'Q' => [60, 58, 56, 59, [57, 58, 59], [60, 59, 58]],
        'k' => [4, 6, 7, 5, [5, 6], [4, 5, 6]],
        'q' => [4, 2, 0, 3, [1, 2, 3], [4, 3, 2]],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function fromFen(string $fen): array
    {
        [$placement, $turn, $castling, $ep, $halfmove, $fullmove] = array_pad(explode(' ', trim($fen)), 6, null);
        $board = [];
        foreach (str_split((string) $placement) as $char) {
            if ($char === '/') {
                continue;
            }
            if (ctype_digit($char)) {
                array_push($board, ...array_fill(0, (int) $char, null));
            } else {
                $board[] = $char;
            }
        }

        return [
            'board' => $board,
            'turn' => $turn === 'b' ? 1 : 0,
            'castling' => $castling === '-' || $castling === null ? '' : $castling,
            'ep' => $ep === '-' || $ep === null ? null : self::square($ep),
            'halfmove' => (int) ($halfmove ?? 0),
            'fullmove' => (int) ($fullmove ?? 1),
        ];
    }

    public static function square(string $name): int
    {
        return (8 - (int) $name[1]) * 8 + ord($name[0]) - ord('a');
    }

    public static function name(int $square): string
    {
        return chr(ord('a') + $square % 8).(8 - intdiv($square, 8));
    }

    public static function owner(?string $piece): ?int
    {
        return $piece === null ? null : (ctype_upper($piece) ? 0 : 1);
    }

    /**
     * @param  array<string, mixed>  $position
     * @return list<array{from: int, to: int, promotion?: string}>
     */
    public static function legalMoves(array $position): array
    {
        $moves = [];
        foreach (self::pseudoMoves($position) as $move) {
            if (! self::inCheck(self::movePieces($position['board'], $move, $position['ep']), $position['turn'])) {
                $moves[] = $move;
            }
        }

        return $moves;
    }

    /**
     * Moves that follow the pieces' movement rules but may leave the own king in check.
     *
     * @param  array<string, mixed>  $position
     * @return list<array{from: int, to: int, promotion?: string}>
     */
    public static function pseudoMoves(array $position): array
    {
        $board = $position['board'];
        $seat = $position['turn'];
        $moves = [];

        foreach ($board as $from => $piece) {
            if ($piece === null || self::owner($piece) !== $seat) {
                continue;
            }
            $row = intdiv($from, 8);
            $col = $from % 8;

            switch (strtolower($piece)) {
                case 'p':
                    $dir = $seat === 0 ? -1 : 1;
                    $one = $from + $dir * 8;
                    if ($board[$one] === null) {
                        self::addPawnMove($moves, $from, $one);
                        $two = $one + $dir * 8;
                        if ($row === ($seat === 0 ? 6 : 1) && $board[$two] === null) {
                            $moves[] = ['from' => $from, 'to' => $two];
                        }
                    }
                    foreach ([-1, 1] as $dc) {
                        if ($col + $dc < 0 || $col + $dc > 7) {
                            continue;
                        }
                        $to = $one + $dc;
                        if (($board[$to] !== null && self::owner($board[$to]) !== $seat) || $to === $position['ep']) {
                            self::addPawnMove($moves, $from, $to);
                        }
                    }
                    break;
                case 'n':
                    self::addSteps($moves, $board, $seat, $from, self::KNIGHT, false);
                    break;
                case 'b':
                    self::addSteps($moves, $board, $seat, $from, self::DIAGONAL, true);
                    break;
                case 'r':
                    self::addSteps($moves, $board, $seat, $from, self::STRAIGHT, true);
                    break;
                case 'q':
                    self::addSteps($moves, $board, $seat, $from, [...self::DIAGONAL, ...self::STRAIGHT], true);
                    break;
                case 'k':
                    self::addSteps($moves, $board, $seat, $from, self::KING, false);
                    foreach (self::CASTLING as $right => [$kingFrom, $kingTo, $rookFrom, , $empty, $crossed]) {
                        if (! str_contains($position['castling'], $right) || $from !== $kingFrom || self::owner($right) !== $seat) {
                            continue;
                        }
                        if ($board[$rookFrom] !== ($seat === 0 ? 'R' : 'r')) {
                            continue;
                        }
                        foreach ($empty as $square) {
                            if ($board[$square] !== null) {
                                continue 2;
                            }
                        }
                        foreach ($crossed as $square) {
                            if (self::attacked($board, $square, 1 - $seat)) {
                                continue 2;
                            }
                        }
                        $moves[] = ['from' => $from, 'to' => $kingTo];
                    }
                    break;
            }
        }

        return $moves;
    }

    /**
     * Play a legal move: the next position, with castling rights, en passant square and counters.
     *
     * @param  array<string, mixed>  $position
     * @param  array{from: int, to: int, promotion?: string}  $move
     * @return array<string, mixed>
     */
    public static function play(array $position, array $move): array
    {
        ['from' => $from, 'to' => $to] = $move;
        $piece = (string) $position['board'][$from];
        $pawn = strtolower($piece) === 'p';
        $capture = $position['board'][$to] !== null || ($pawn && $to === $position['ep']);

        $castling = $position['castling'];
        $lost = match ($piece) {
            'K' => 'KQ',
            'k' => 'kq',
            default => '',
        };
        foreach ([63 => 'K', 56 => 'Q', 7 => 'k', 0 => 'q'] as $corner => $right) {
            if ($from === $corner || $to === $corner) {
                $lost .= $right;
            }
        }
        $castling = str_replace(str_split($lost ?: ' '), '', $castling);

        return [
            'board' => self::movePieces($position['board'], $move, $position['ep']),
            'turn' => 1 - $position['turn'],
            'castling' => $castling,
            'ep' => $pawn && abs($to - $from) === 16 ? intdiv($from + $to, 2) : null,
            'halfmove' => $pawn || $capture ? 0 : $position['halfmove'] + 1,
            'fullmove' => $position['fullmove'] + ($position['turn'] === 1 ? 1 : 0),
        ];
    }

    /**
     * Just the board after a move (en passant capture, castling rook and promotion included).
     *
     * @param  list<?string>  $board
     * @param  array{from: int, to: int, promotion?: string}  $move
     * @return list<?string>
     */
    public static function movePieces(array $board, array $move, ?int $ep): array
    {
        ['from' => $from, 'to' => $to] = $move;
        $piece = (string) $board[$from];
        $type = strtolower($piece);

        if ($type === 'p' && $to === $ep && $board[$to] === null) {
            $board[intdiv($from, 8) * 8 + $to % 8] = null;
        }
        if ($type === 'k' && abs($to - $from) === 2) {
            [$rookFrom, $rookTo] = $to > $from ? [$from + 3, $from + 1] : [$from - 4, $from - 1];
            $board[$rookTo] = $board[$rookFrom];
            $board[$rookFrom] = null;
        }
        if (isset($move['promotion'])) {
            $piece = ctype_upper($piece) ? strtoupper($move['promotion']) : $move['promotion'];
        }
        $board[$from] = null;
        $board[$to] = $piece;

        return $board;
    }

    /**
     * @param  list<?string>  $board
     */
    public static function inCheck(array $board, int $seat): bool
    {
        $king = array_search($seat === 0 ? 'K' : 'k', $board, true);

        return $king !== false && self::attacked($board, $king, 1 - $seat);
    }

    /**
     * Whether $by attacks $square.
     *
     * @param  list<?string>  $board
     */
    public static function attacked(array $board, int $square, int $by): bool
    {
        $row = intdiv($square, 8);
        $col = $square % 8;
        $white = $by === 0;

        // A pawn attacks diagonally forward, so it stands one row behind the square.
        $pawnRow = $row + ($white ? 1 : -1);
        if ($pawnRow >= 0 && $pawnRow <= 7) {
            foreach ([-1, 1] as $dc) {
                if ($col + $dc >= 0 && $col + $dc <= 7 && $board[$pawnRow * 8 + $col + $dc] === ($white ? 'P' : 'p')) {
                    return true;
                }
            }
        }

        foreach ([[self::KNIGHT, 'n', false], [self::KING, 'k', false], [self::DIAGONAL, 'bq', true], [self::STRAIGHT, 'rq', true]] as [$directions, $types, $slides]) {
            foreach ($directions as [$dr, $dc]) {
                for ($r = $row + $dr, $c = $col + $dc; $r >= 0 && $r <= 7 && $c >= 0 && $c <= 7; $r += $dr, $c += $dc) {
                    $piece = $board[$r * 8 + $c];
                    if ($piece !== null) {
                        if (self::owner($piece) === $by && str_contains($types, strtolower($piece))) {
                            return true;
                        }
                        break;
                    }
                    if (! $slides) {
                        break;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Neither side can possibly checkmate: bare kings, or a single minor piece, or bishops only, all
     * on squares of one colour.
     *
     * @param  list<?string>  $board
     */
    public static function insufficientMaterial(array $board): bool
    {
        $others = [];
        foreach ($board as $square => $piece) {
            if ($piece !== null && strtolower($piece) !== 'k') {
                $others[$square] = strtolower($piece);
            }
        }
        if (count($others) <= 1) {
            return $others === [] || in_array(reset($others), ['n', 'b'], true);
        }
        if (array_unique($others) === [array_key_first($others) => 'b']) {
            $colours = array_map(fn (int $square) => (intdiv($square, 8) + $square % 8) % 2, array_keys($others));

            return count(array_unique($colours)) === 1;
        }

        return false;
    }

    /**
     * Whether $seat has enough material to ever checkmate (anything beyond a lone king or a king
     * with one minor piece).
     *
     * @param  list<?string>  $board
     */
    public static function canMate(array $board, int $seat): bool
    {
        $pieces = array_values(array_filter($board, fn (?string $p) => $p !== null && self::owner($p) === $seat && strtolower($p) !== 'k'));

        return count($pieces) > 1 || (count($pieces) === 1 && ! in_array(strtolower($pieces[0]), ['n', 'b'], true));
    }

    /**
     * Standard algebraic notation of a legal move, e.g. "Nbd7", "exd6", "e8=Q+", "O-O-O#".
     *
     * @param  array<string, mixed>  $position
     * @param  array{from: int, to: int, promotion?: string}  $move
     */
    public static function san(array $position, array $move): string
    {
        ['from' => $from, 'to' => $to] = $move;
        $board = $position['board'];
        $piece = (string) $board[$from];
        $type = strtolower($piece);
        $capture = $board[$to] !== null || ($type === 'p' && $to === $position['ep']);

        if ($type === 'k' && abs($to - $from) === 2) {
            $san = $to > $from ? 'O-O' : 'O-O-O';
        } elseif ($type === 'p') {
            $san = ($capture ? self::name($from)[0].'x' : '').self::name($to).(isset($move['promotion']) ? '='.strtoupper($move['promotion']) : '');
        } else {
            $rivals = array_filter(self::legalMoves($position), fn (array $m) => $m['to'] === $to && $m['from'] !== $from && $board[$m['from']] === $piece);
            $hint = '';
            if ($rivals !== []) {
                $sameFile = array_filter($rivals, fn (array $m) => $from % 8 === $m['from'] % 8) !== [];
                $sameRank = array_filter($rivals, fn (array $m) => intdiv($m['from'], 8) === intdiv($from, 8)) !== [];
                $hint = ! $sameFile ? self::name($from)[0] : (! $sameRank ? self::name($from)[1] : self::name($from));
            }
            $san = strtoupper($type).$hint.($capture ? 'x' : '').self::name($to);
        }

        $after = self::play($position, $move);
        if (self::inCheck($after['board'], $after['turn'])) {
            $san .= self::legalMoves($after) === [] ? '#' : '+';
        }

        return $san;
    }

    /**
     * Identity of a position for repetition: pieces, side to move, castling rights and a usable
     * en passant capture.
     *
     * @param  array<string, mixed>  $position
     */
    public static function key(array $position): string
    {
        $ep = '-';
        if ($position['ep'] !== null) {
            foreach (self::legalMoves($position) as $move) {
                if ($move['to'] === $position['ep'] && strtolower((string) $position['board'][$move['from']]) === 'p') {
                    $ep = (string) $position['ep'];
                    break;
                }
            }
        }

        return implode('', array_map(fn (?string $p) => $p ?? '.', $position['board'])).$position['turn'].$position['castling'].$ep;
    }

    /**
     * @param  list<array{from: int, to: int, promotion?: string}>  $moves
     */
    private static function addPawnMove(array &$moves, int $from, int $to): void
    {
        $row = intdiv($to, 8);
        if ($row === 0 || $row === 7) {
            foreach (['q', 'r', 'b', 'n'] as $promotion) {
                $moves[] = ['from' => $from, 'to' => $to, 'promotion' => $promotion];
            }
        } else {
            $moves[] = ['from' => $from, 'to' => $to];
        }
    }

    /**
     * @param  list<array{from: int, to: int, promotion?: string}>  $moves
     * @param  list<?string>  $board
     * @param  list<array{0: int, 1: int}>  $directions
     */
    private static function addSteps(array &$moves, array $board, int $seat, int $from, array $directions, bool $slides): void
    {
        $row = intdiv($from, 8);
        $col = $from % 8;
        foreach ($directions as [$dr, $dc]) {
            for ($r = $row + $dr, $c = $col + $dc; $r >= 0 && $r <= 7 && $c >= 0 && $c <= 7; $r += $dr, $c += $dc) {
                $target = $board[$r * 8 + $c];
                if ($target === null || self::owner($target) !== $seat) {
                    $moves[] = ['from' => $from, 'to' => $r * 8 + $c];
                }
                if ($target !== null || ! $slides) {
                    break;
                }
            }
        }
    }
}
