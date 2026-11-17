// Hardening checks of our engine layer (security audit 2026-11-18).
package engine

import (
	"errors"
	"math"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestInputIsBounded(t *testing.T) {
	for name, in := range map[string]SpinIn{
		"path in id":     {Game: "../../etc/passwd", RTP: 95},
		"upper case id":  {Game: "Novomatic/BookOfRa", RTP: 95},
		"long id":        {Game: "a/" + strings.Repeat("b", 200), RTP: 95},
		"negative bet":   {Game: "novomatic/sizzlinghot", RTP: 95, Bet: -1},
		"huge bet":       {Game: "novomatic/sizzlinghot", RTP: 95, Bet: 1e9},
		"nan bet":        {Game: "novomatic/sizzlinghot", RTP: 95, Bet: math.NaN()},
		"lines":          {Game: "novomatic/sizzlinghot", RTP: 95, Sel: 5000},
		"fund":           {Game: "novomatic/sizzlinghot", RTP: 95, Fund: -5},
		"state an array": {Game: "novomatic/sizzlinghot", RTP: 95, State: []byte(`[1,2,3]`)},
		"state a string": {Game: "novomatic/sizzlinghot", RTP: 95, State: []byte(`"x"`)},
	} {
		if _, err := Spin(in); !errors.Is(err, ErrBadInput) {
			t.Errorf("%s: %v", name, err)
		}
	}
	// What the platform sends still plays.
	if _, err := Spin(SpinIn{Game: "novomatic/sizzlinghot", RTP: 95, Bet: 2, Sel: 5}); err != nil {
		t.Error(err)
	}
}

func TestAnswersShowNoInternals(t *testing.T) {
	var key = strings.Repeat("k", 40)
	var h = Handler(key)
	var call = func(method, path, body, k string) (int, string) {
		var r = httptest.NewRequest(method, path, strings.NewReader(body))
		if k != "" {
			r.Header.Set("X-Engine-Key", k)
		}
		var w = httptest.NewRecorder()
		h.ServeHTTP(w, r)
		return w.Code, strings.TrimSpace(w.Body.String())
	}
	for _, c := range []struct {
		body string
		code int
		want string
	}{
		{`{"game":"novomatic/sizzlinghot","rtp":95,"wallet":5}`, 400, `{"error":"bad request"}`},
		{`{"game":"novomatic/sizzlinghot","rtp":95}{"x":1}`, 400, `{"error":"bad request"}`},
		{`not json`, 400, `{"error":"bad request"}`},
		{`{"game":"nobody/nothing","rtp":95}`, 404, `{"error":"unknown game"}`},
		{`{"game":"novomatic/sizzlinghot","rtp":95,"sel":99}`, 422, `{"error":"refused"}`},
		{`{"game":"novomatic/sizzlinghot","rtp":95,"state":{"grid":[[1]],"sel":"x"}}`, 500, `{"error":"internal error"}`},
	} {
		if code, body := call("POST", "/v1/spin", c.body, key); code != c.code || body != c.want {
			t.Errorf("%s → %d %s", c.body, code, body)
		}
	}
	if code, body := call("GET", "/health", "", ""); code != 200 || body != `{"ok":true}` {
		t.Errorf("health: %d %s", code, body)
	}
	// A wrong key of any length gets the same answer.
	for _, k := range []string{"x", strings.Repeat("k", 39), strings.Repeat("k", 41), strings.Repeat("z", 40)} {
		if code, body := call("GET", "/v1/games", "", k); code != 401 || body != `{"error":"unauthorized"}` {
			t.Errorf("key %q: %d %s", k, code, body)
		}
	}
	// Methods other than the routes' own are refused.
	if code, _ := call("DELETE", "/v1/spin", "", key); code != http.StatusMethodNotAllowed {
		t.Errorf("DELETE: %d", code)
	}
}
