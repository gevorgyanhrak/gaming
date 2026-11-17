// Our engine (game-platform, 2026-11-17): the internal HTTP service. Only our platform's API calls it, over the
// private Docker network, with a shared key; it is never published to the internet. It knows games, never people.
package engine

import (
	"crypto/sha256"
	"crypto/subtle"
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"sort"
	"time"

	"github.com/slotopol/server/game"
	"github.com/slotopol/server/game/slot"
)

// MaxBody bounds a request (a game state is a few kilobytes).
const MaxBody = 256 << 10

// GameEntry describes one game for the platform's catalogue.
type GameEntry struct {
	ID       string          `json:"id"`
	Name     string          `json:"name"`
	Provider string          `json:"provider"`
	Year     int             `json:"year,omitempty"`
	Width    int             `json:"width"`   // reels
	Height   int             `json:"height"`  // rows
	Symbols  int             `json:"symbols"` // symbol count
	Lines    int             `json:"lines"`   // selectable lines (0: ways or clusters)
	Ways     int             `json:"ways,omitempty"`
	Bonuses  int             `json:"bonuses,omitempty"`
	RTP      []float64       `json:"rtp"`
	Measured *float64        `json:"measured"` // RTP paid through our spin path (measured.go); null when not measured
	Cost     float64         `json:"cost"`     // what a spin costs at bet 1 with the default lines (ways games: their own price)
	Verified bool            `json:"verified"` // measured within Tolerance of the label
	Features []string        `json:"features"`
	State    json.RawMessage `json:"state"` // a fresh game's state (its first screen)
}

var featureNames = []struct {
	flag game.GP
	name string
}{
	{game.GPlpay, "pays_left"}, {game.GPrpay &^ game.GPlpay, "pays_both_ways"}, {game.GPapay &^ game.GPrpay, "pays_anywhere"},
	{game.GPcpay, "cluster_pays"}, {game.GPlsel, "line_select"}, {game.GPwsel, "ways_select"}, {game.GPjack, "jackpot"},
	{game.GPfill, "full_screen_multiplier"}, {game.GPmix, "mixed_pays"}, {game.GPcumul, "cumulative_pays"},
	{game.GPbmode, "bonus_mode"}, {game.GPpick, "pick_mode"}, {game.GPcas, "cascade"}, {game.GPcfeat, "cascade_features"},
	{game.GPfgonce, "free_games"}, {game.GPfgtwic, "free_games_retrigger_once"}, {game.GPfgseq, "free_games_retrigger"},
	{game.GPfgreel, "free_games_own_reels"}, {game.GPrgmult, "multipliers"}, {game.GPfgmult, "free_games_multipliers"},
	{game.GPscat, "scatters"}, {game.GPstscat, "stacked_scatters"}, {game.GPwsc, "wild_scatters"}, {game.GPwild, "wilds"},
	{game.GPrwild, "reel_wilds"}, {game.GPbwild, "big_wilds"}, {game.GPewild, "expanding_wilds"},
	{game.GPwturn, "symbols_turn_wild"}, {game.GPwmult, "wild_multipliers"}, {game.GPbsym, "big_symbols"},
}

func features(gp game.GP) []string {
	var list = []string{}
	for _, f := range featureNames {
		if f.flag != 0 && gp&f.flag == f.flag {
			list = append(list, f.name)
		}
	}
	return list
}

// Catalogue lists every slot the engine plays, from the registered algorithms (never a fixed list).
func Catalogue() ([]GameEntry, error) {
	var list []GameEntry
	for _, ai := range game.AlgList {
		if ai.GT != game.GTslot {
			continue
		}
		for _, ga := range ai.Aliases {
			var id = ga.ID()
			var g, err = Restore(id, nil)
			if err != nil {
				return nil, err
			}
			var state, _ = json.Marshal(g)
			var lines = ga.LNum
			var entry = GameEntry{
				ID: id, Name: ga.Name, Provider: ga.Prov, Width: ai.SX, Height: ai.SY, Symbols: ai.SN,
				Lines: lines, Ways: ai.WN, Cost: g.Cost(), Bonuses: ai.BN, RTP: ai.RTP, Features: features(ai.GP), State: state,
			}
			if m, ok := Verified(id); m != 0 {
				entry.Measured, entry.Verified = &m, ok
			}
			if !ga.Date.IsZero() {
				entry.Year = ga.Date.Time().Year()
			}
			list = append(list, entry)
		}
	}
	sort.Slice(list, func(i, j int) bool { return list[i].ID < list[j].ID })
	return list, nil
}

// Handler serves the engine's API behind the key: GET /v1/games, POST /v1/spin, GET /health (no key).
func Handler(key string) http.Handler {
	var mux = http.NewServeMux()
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, r *http.Request) {
		// Only "alive": it is the one route without the key.
		reply(w, http.StatusOK, map[string]any{"ok": true})
	})
	mux.HandleFunc("GET /v1/games", func(w http.ResponseWriter, r *http.Request) {
		var list, err = Catalogue()
		if err != nil {
			fail(w, r, http.StatusInternalServerError, "internal error", err)
			return
		}
		reply(w, http.StatusOK, map[string]any{"data": list})
	})
	mux.HandleFunc("POST /v1/spin", func(w http.ResponseWriter, r *http.Request) {
		var in SpinIn
		var dec = json.NewDecoder(http.MaxBytesReader(w, r.Body, MaxBody))
		dec.DisallowUnknownFields()
		if err := dec.Decode(&in); err != nil {
			fail(w, r, http.StatusBadRequest, "bad request", err)
			return
		}
		if dec.More() {
			fail(w, r, http.StatusBadRequest, "bad request", errors.New("trailing data"))
			return
		}
		var out, err = Spin(in)
		// Fixed answers: what failed is logged here, never sent back (no Go errors, input or game internals).
		switch {
		case errors.Is(err, ErrBadInput):
			fail(w, r, http.StatusBadRequest, "bad request", err)
		case errors.Is(err, ErrNoGame):
			fail(w, r, http.StatusNotFound, "unknown game", err)
		case errors.Is(err, slot.ErrBadParam), errors.Is(err, slot.ErrDisabled), errors.Is(err, slot.ErrNoFeature),
			errors.Is(err, ErrBadRTP):
			fail(w, r, http.StatusUnprocessableEntity, "refused", err)
		case err != nil:
			fail(w, r, http.StatusInternalServerError, "internal error", err)
		default:
			reply(w, http.StatusOK, out)
		}
	})
	return guard(key, mux)
}

// guard lets /health through and asks everything else for the shared key. Both sides are hashed first, so the
// comparison takes the same time whatever was sent (ConstantTimeCompare alone returns early on a length mismatch).
func guard(key string, next http.Handler) http.Handler {
	var want = sha256.Sum256([]byte(key))
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		var t0 = time.Now()
		var got = sha256.Sum256([]byte(r.Header.Get("X-Engine-Key")))
		if r.URL.Path != "/health" && subtle.ConstantTimeCompare(got[:], want[:]) != 1 {
			fail(w, r, http.StatusUnauthorized, "unauthorized", nil)
			return
		}
		next.ServeHTTP(w, r)
		slog.Debug("request", "method", r.Method, "path", r.URL.Path, "took", time.Since(t0).String())
	})
}

func reply(w http.ResponseWriter, code int, body any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	_ = json.NewEncoder(w).Encode(body)
}

// fail answers with a fixed public message; the cause goes to the log (never the request body, state or key).
func fail(w http.ResponseWriter, r *http.Request, code int, public string, cause error) {
	if cause != nil {
		slog.Warn("request refused", "method", r.Method, "path", r.URL.Path, "status", code, "cause", cause.Error())
	} else {
		slog.Warn("request refused", "method", r.Method, "path", r.URL.Path, "status", code)
	}
	reply(w, code, map[string]string{"error": public})
}
