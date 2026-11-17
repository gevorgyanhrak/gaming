// Our engine (game-platform): the RTP each game really paid through our spin path, measured with `slot-engine
// measure` (200,000 paid spins per game; 2,000,000 for the ones that were more than 3 points off) at the reel set
// closest to 95 %. Regenerate measured.json after changing the engine or a game.
package engine

import (
	_ "embed"
	"encoding/json"
	"math"
)

// Tolerance is how far (in RTP points) the measured RTP may be from the reel set's label for a game to be verified.
const Tolerance = 3.0

//go:embed measured.json
var measuredData []byte

type measurement struct {
	Label    float64 `json:"label"`
	Measured float64 `json:"measured"`
	Spins    int     `json:"spins"`
}

var measurements = func() map[string]measurement {
	var m = map[string]measurement{}
	if err := json.Unmarshal(measuredData, &m); err != nil {
		panic(err)
	}
	return m
}()

// Verified tells the measured RTP of a game and whether it matches its label within Tolerance.
func Verified(id string) (measured float64, ok bool) {
	var m, found = measurements[id]
	if !found {
		return 0, false
	}
	return m.Measured, math.Abs(m.Measured-m.Label) <= Tolerance
}
