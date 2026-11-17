// Our engine (game-platform, 2026-11-17). Loading of the embedded reel data, as the original server did it in
// core/startup.go (LoadInternalYaml, UpdateAlgList, CheckAlgList) — that package also held users, clubs and the
// database, so only these lines were kept.
package engine

import (
	"bytes"
	"fmt"

	"github.com/slotopol/server/game"
)

// Load reads every game's embedded reels and paytables and completes the algorithm list (RTP sets).
func Load() (games int, err error) {
	for _, b := range game.LoadMap {
		if _, err = game.ReadChain(bytes.NewReader(b)); err != nil {
			return 0, err
		}
	}
	for _, ai := range game.AlgList {
		if ai.Update != nil {
			ai.Update(ai)
		}
	}
	for _, ai := range game.AlgList {
		if len(ai.RTP) == 0 {
			var id string
			if len(ai.Aliases) > 0 {
				id = ai.Aliases[0].ID()
			}
			return 0, fmt.Errorf("RTP list does not complete for %s", id)
		}
	}
	return len(game.InfoMap), nil
}
