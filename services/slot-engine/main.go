// The slot engine service of game-platform: the Slotopol games (MIT, see LICENSE and UPSTREAM.md) behind a small
// internal API. Run: SLOT_ENGINE_KEY=… slot-engine serve [-addr :8090]; slot-engine list (catalogue as JSON).
package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"time"

	cfg "github.com/slotopol/server/config"
	"github.com/slotopol/server/engine"
)

func main() {
	cfg.Verbose = 2
	cfg.InitLogger()
	var games, err = engine.Load()
	if err != nil {
		cfg.Fatalf("can not load games: %s", err)
	}
	if len(os.Args) < 2 {
		fmt.Fprintln(os.Stderr, "usage: slot-engine serve [-addr :8090] | list | measure [spins] [game ids…]")
		os.Exit(2)
	}
	switch os.Args[1] {
	case "list":
		var list, err = engine.Catalogue()
		if err != nil {
			cfg.Fatalf("%s", err)
		}
		_ = json.NewEncoder(os.Stdout).Encode(list)
	case "measure":
		// slot-engine measure [spins per game] — the RTP each game really pays through our spin path.
		var n = 200000
		if len(os.Args) > 2 {
			fmt.Sscan(os.Args[2], &n)
		}
		_ = json.NewEncoder(os.Stdout).Encode(engine.Measure(n, 95, os.Args[min(3, len(os.Args)):]...))
	case "serve":
		var fs = flag.NewFlagSet("serve", flag.ExitOnError)
		var addr = fs.String("addr", ":8090", "listen address (internal network only)")
		_ = fs.Parse(os.Args[2:])
		var key = os.Getenv("SLOT_ENGINE_KEY")
		if len(key) < 32 {
			cfg.Fatalf("SLOT_ENGINE_KEY must be set (32+ characters)")
		}
		slog.Info(fmt.Sprintf("slot engine: %d games, listening on %s", games, *addr))
		var srv = &http.Server{
			Addr:              *addr,
			Handler:           engine.Handler(key),
			ReadHeaderTimeout: 5 * time.Second,
			MaxHeaderBytes:    16 << 10,
			IdleTimeout:       60 * time.Second,
			ReadTimeout:       10 * time.Second,
			WriteTimeout:      30 * time.Second,
		}
		cfg.Fatalf("%s", srv.ListenAndServe())
	default:
		fmt.Fprintln(os.Stderr, "unknown command:", os.Args[1])
		os.Exit(2)
	}
}
