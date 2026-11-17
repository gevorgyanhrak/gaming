// Our engine (game-platform, 2026-11-17): one spin of one game, with no users, clubs, wallets or sessions — the
// platform that calls it owns all of those. The steps are the original server's (api/slot.go ApiSlotSpin and
// CascadeGain), minus the club bank: prepare, spin on the reels closest to the RTP, scan the wins, play out a
// cascade to check it ends, spawn bonuses, apply. The game's whole state goes in and comes back as JSON, so the
// caller stores it (free games, cascades, expanding symbols survive between spins and page loads).
package engine

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"math"
	"regexp"

	"github.com/slotopol/server/game"
	"github.com/slotopol/server/game/slot"
)

// MaxSpinAttempts bounds re-spins of a grid the scanner can't take (the original server's default).
const MaxSpinAttempts = 300

var (
	ErrNoGame    = errors.New("no such game")
	ErrNotSlot   = errors.New("not a slot game")
	ErrBadRTP    = errors.New("rtp out of range")
	ErrNoSpin    = errors.New("no valid spin within the attempts limit")
	ErrGameFault = errors.New("game fault")
	ErrBadInput  = errors.New("bad input")
)

type SpinIn struct {
	Game  string          `json:"game"`
	State json.RawMessage `json:"state,omitempty"` // the state the last spin returned; empty for a new game
	Bet   float64         `json:"bet,omitempty"`   // bet per line (0: keep)
	Sel   int             `json:"sel,omitempty"`   // selected lines (0: keep)
	RTP   float64         `json:"rtp"`             // the RTP to play at: the closest reel set is used
	Fund  float64         `json:"fund,omitempty"`  // progressive jackpot fund, for games that have one
}

type SpinOut struct {
	State json.RawMessage `json:"state"`
	Wins  slot.Wins       `json:"wins"`
	Cost  float64         `json:"cost"`  // what this spin costs (0 in free games and cascade falls)
	Win   float64         `json:"win"`   // what this spin pays
	Jack  float64         `json:"jack"`  // jackpot part of the win
	Free  bool            `json:"free"`  // this spin was free (free game or cascade fall)
	Next  bool            `json:"next"`  // the next spin is free too
	FSR   int             `json:"fsr"`   // free games left
	Total float64         `json:"total"` // the game's running gain (all free games of a series)
}

// Restore makes the game and loads its stored state (none: a fresh game at its defaults).
func Restore(id string, state json.RawMessage) (slot.SlotGeneric, error) {
	var factory, ok = game.GameFactory[id]
	if !ok {
		return nil, ErrNoGame
	}
	var g, isSlot = factory().(slot.SlotGeneric)
	if !isSlot {
		return nil, ErrNotSlot
	}
	if len(state) > 0 && string(state) != "null" {
		if err := json.Unmarshal(state, g); err != nil {
			return nil, fmt.Errorf("bad state: %w", err)
		}
	}
	return g, nil
}

// gameID is what game ids look like ("maker/game", lower-case letters and digits).
var gameID = regexp.MustCompile(`^[a-z0-9]{1,40}/[a-z0-9]{1,80}$`)

// validate bounds the request before any game code sees it. The game's own rules check the rest (lines in its set,
// no changes during free games).
func (in SpinIn) validate() error {
	var finite = func(v float64) bool { return !math.IsNaN(v) && !math.IsInf(v, 0) }
	switch {
	case !gameID.MatchString(in.Game):
		return fmt.Errorf("%w: game id", ErrBadInput)
	case !finite(in.Bet) || in.Bet < 0 || in.Bet > 1e6:
		return fmt.Errorf("%w: bet", ErrBadInput)
	case in.Sel < 0 || in.Sel > 1024:
		return fmt.Errorf("%w: sel", ErrBadInput)
	case !finite(in.Fund) || in.Fund < 0 || in.Fund > 1e12:
		return fmt.Errorf("%w: fund", ErrBadInput)
	case !finite(in.RTP):
		return fmt.Errorf("%w: rtp", ErrBadInput)
	}
	// A state is the JSON object a previous spin returned (or nothing).
	if trimmed := bytes.TrimSpace(in.State); len(trimmed) > 0 && string(trimmed) != "null" && trimmed[0] != '{' {
		return fmt.Errorf("%w: state", ErrBadInput)
	}
	return nil
}

// cascadeGain plays the falls after a winning cascade spin on a copy, so a spin whose avalanche never ends is
// thrown away (the original CascadeGain).
func cascadeGain(rng *slot.Rand, g slot.SlotGeneric, wins slot.Wins, fund, mrtp float64) error {
	if len(wins) == 0 {
		return nil
	}
	var cas, ok = g.Clone().(slot.SlotCascade)
	if !ok {
		return nil
	}
	cas.Strike(wins)
	var cw slot.Wins
	for range slot.FallLimit {
		cas.UntoFall()
		cas.SpinReels(rng, cas.GetReels(mrtp))
		if err := cas.Scanner(&cw); err != nil {
			return err
		}
		if len(cw) == 0 {
			return nil
		}
		g.Spawn(rng, cw, fund, mrtp)
		cas.Strike(cw)
		cw.Reset()
	}
	return slot.ErrAvalanche
}

// Spin plays one spin. It never touches money: the caller charges Cost and pays Win.
func Spin(in SpinIn) (out SpinOut, err error) {
	// A fault inside one game's code fails that spin, never the service (and never charges: the caller pays on success).
	defer func() {
		if r := recover(); r != nil {
			out, err = SpinOut{}, fmt.Errorf("%w: %v", ErrGameFault, r)
		}
	}()
	if err = in.validate(); err != nil {
		return
	}
	if in.RTP < 50 || in.RTP > 150 {
		return out, ErrBadRTP
	}
	var g slot.SlotGeneric
	if g, err = Restore(in.Game, in.State); err != nil {
		return
	}
	// The game's own rules decide: no bet or line change during free games, lines within the game's set.
	if in.Bet != 0 {
		if err = g.SetBet(in.Bet); err != nil {
			return
		}
	}
	if in.Sel != 0 {
		if err = g.SetSel(in.Sel); err != nil {
			return
		}
	}
	out.Free = g.FreeMode()
	if !out.Free {
		out.Cost = g.Cost()
	}

	var wins slot.Wins
	g.Prepare(slot.CCRNG)
	for n := 0; ; n++ {
		if n >= MaxSpinAttempts {
			return out, ErrNoSpin
		}
		g.Spin(slot.CCRNG, in.RTP)
		if g.Scanner(&wins) != nil {
			wins.Reset()
			continue
		}
		if cascadeGain(slot.CCRNG, g, wins, in.Fund, in.RTP) != nil {
			wins.Reset()
			continue
		}
		g.Spawn(slot.CCRNG, wins, in.Fund, in.RTP)
		break
	}
	out.Win = wins.Gain()
	out.Jack = wins.Jackpot()
	g.Apply(wins)

	if out.Wins = wins; out.Wins == nil {
		out.Wins = slot.Wins{}
	}
	out.Next = g.FreeMode()
	out.Total = g.GetGain()
	if out.State, err = json.Marshal(g); err != nil {
		return
	}
	// Free games left, as the state carries them (Slotx.FSR).
	var left struct {
		FSR int `json:"fsr"`
	}
	_ = json.Unmarshal(out.State, &left)
	out.FSR = left.FSR
	return
}
