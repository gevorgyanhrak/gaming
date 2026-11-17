// Our engine (game-platform): plays every game through the same Spin path the platform uses and reports the RTP
// actually paid next to the RTP the reel set is labelled with. The labels come from the original authors'
// scanners and formulas; a game whose play differs from its formula shows here.
package engine

import (
	"encoding/json"
	"runtime"
	"slices"
	"sync"
)

type Measured struct {
	ID       string  `json:"id"`
	Label    float64 `json:"label"`    // the reel set's RTP (closest to the asked one)
	Measured float64 `json:"measured"` // paid / staked over the spins, free games and falls included
	Spins    int     `json:"spins"`
	FreeHits int     `json:"free_hits"` // spins that started or continued free games or falls
	Error    string  `json:"error,omitempty"`
}

// Measure plays n paid spins (plus every free game and fall they bring) of each game at the RTP closest to rtp.
func Measure(n int, rtp float64, only ...string) []Measured {
	var all, _ = Catalogue()
	var list = all[:0:0]
	for _, g := range all {
		if len(only) == 0 || slices.Contains(only, g.ID) {
			list = append(list, g)
		}
	}
	var out = make([]Measured, len(list))
	var next = make(chan int)
	var wg sync.WaitGroup
	for range runtime.NumCPU() {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for i := range next {
				out[i] = measureOne(list[i], n, rtp)
			}
		}()
	}
	for i := range list {
		next <- i
	}
	close(next)
	wg.Wait()
	return out
}

func measureOne(g GameEntry, n int, rtp float64) (m Measured) {
	m.ID = g.ID
	m.Label = closest(g.RTP, rtp)
	var state json.RawMessage
	var paid, won float64
	for m.Spins < n {
		var res, err = Spin(SpinIn{Game: g.ID, State: state, RTP: m.Label})
		if err != nil {
			m.Error = err.Error()
			return
		}
		if res.Free {
			m.FreeHits++
		} else {
			m.Spins++
		}
		paid += res.Cost
		won += res.Win
		state = res.State
	}
	if paid > 0 {
		m.Measured = won / paid * 100
	}
	return
}

func closest(list []float64, want float64) float64 {
	var best = list[0]
	for _, r := range list {
		if abs(r-want) < abs(best-want) {
			best = r
		}
	}
	return best
}

func abs(x float64) float64 {
	if x < 0 {
		return -x
	}
	return x
}
