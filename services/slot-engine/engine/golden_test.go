// Gameplay regression lock (game-platform, security audit 2026-11-18): for every game, screens dealt once and saved
// with the wins the game's scanner gave them, plus the catalogue (games, makers, sizes, lines, RTP sets, features,
// prices). Any change to a game's rules, paytables, reels or RTP list shows up here. Regenerate deliberately with
// SLOT_GOLDEN_WRITE=1 go test ./engine -run Golden.
package engine

import (
	"bytes"
	"encoding/json"
	"os"
	"testing"

	"github.com/slotopol/server/game/slot"
)

const goldenScreens = 6

type goldenScreen struct {
	State json.RawMessage `json:"state"`
	Wins  json.RawMessage `json:"wins"`
}

func catalogueForGolden(t *testing.T) []byte {
	var list, err = Catalogue()
	if err != nil {
		t.Fatal(err)
	}
	for i := range list {
		list[i].State = nil // a fresh game's screen is dealt at random
	}
	var b, _ = json.MarshalIndent(list, "", " ")
	return b
}

func TestGolden(t *testing.T) {
	if os.Getenv("SLOT_GOLDEN_WRITE") == "1" {
		var golden = map[string][]goldenScreen{}
		var list, _ = Catalogue()
		for _, g := range list {
			for range goldenScreens {
				var game, _ = Restore(g.ID, nil)
				game.Prepare(slot.CCRNG)
				game.Spin(slot.CCRNG, closest(g.RTP, 95))
				var state, _ = json.Marshal(game)
				var wins slot.Wins
				if game.Scanner(&wins) != nil {
					continue
				}
				var w, _ = json.Marshal(wins)
				golden[g.ID] = append(golden[g.ID], goldenScreen{state, w})
			}
		}
		var b, _ = json.Marshal(golden)
		_ = os.WriteFile("testdata/golden.json", b, 0o644)
		_ = os.WriteFile("testdata/catalogue.json", catalogueForGolden(t), 0o644)
		t.Skip("written")
	}

	var want, err = os.ReadFile("testdata/catalogue.json")
	if err != nil {
		t.Fatal(err)
	}
	if !bytes.Equal(bytes.TrimSpace(want), bytes.TrimSpace(catalogueForGolden(t))) {
		t.Error("catalogue changed (games, makers, sizes, lines, RTP sets, features or prices)")
	}

	var raw, _ = os.ReadFile("testdata/golden.json")
	var golden map[string][]goldenScreen
	if err := json.Unmarshal(raw, &golden); err != nil {
		t.Fatal(err)
	}
	var screens int
	for id, list := range golden {
		for i, s := range list {
			var game, err = Restore(id, s.State)
			if err != nil {
				t.Fatalf("%s #%d: %v", id, i, err)
			}
			var wins slot.Wins
			if err := game.Scanner(&wins); err != nil {
				t.Fatalf("%s #%d: %v", id, i, err)
			}
			var got, _ = json.Marshal(wins)
			if !bytes.Equal(got, s.Wins) {
				t.Errorf("%s #%d: wins changed\n want %s\n got  %s", id, i, s.Wins, got)
			}
			screens++
		}
	}
	if len(golden) != 347 {
		t.Errorf("golden covers %d games", len(golden))
	}
	t.Logf("%d games, %d screens re-scored identically", len(golden), screens)
}
