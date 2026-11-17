// game-platform audit 2026-11-18: the file-path and environment helpers (ToSlash, JoinPath, JoinFilePath,
// PathName, ExpandHomePath, Envfmt) were removed with the configuration loader that used them; see
// docs/casino/SLOT_ENGINE_REMOVED_CODE.md. What the games use (ToID, ToLower, B2S) is unchanged.
package util

import (
	"unsafe"
)

// B2S converts bytes slice to string without memory allocation.
func B2S(b []byte) string {
	return unsafe.String(unsafe.SliceData(b), len(b))
}

// S2B converts string to bytes slice without memory allocation.
func S2B(s string) []byte {
	return unsafe.Slice(unsafe.StringData(s), len(s))
}

// ToLower is high performance function to bring filenames to lower case in ASCII
// without superfluous allocations if it possible.
func ToLower(s string) string {
	var b = S2B(s)
	var bc = b
	var c bool
	for i, v := range b {
		if v >= 'A' && v <= 'Z' {
			if !c {
				bc, c = []byte(s), true
			}
			bc[i] |= 0x20
		}
	}
	return B2S(bc)
}

// ToUpper is high performance function to bring filenames to upper case in ASCII
// without superfluous allocations if it possible.
func ToUpper(s string) string {
	var b = S2B(s)
	var bc = b
	var c bool
	for i, v := range b {
		if v >= 'a' && v <= 'z' {
			if !c {
				bc, c = []byte(s), true
			}
			bc[i] &= 0xdf
		}
	}
	return B2S(bc)
}

// ToKey is high performance function to bring filenames to lower case in ASCII
// and true slashes at once without superfluous allocations if it possible.
func ToKey(s string) string {
	var b = S2B(s)
	var bc = b
	var c bool
	for i, v := range b {
		if v >= 'A' && v <= 'Z' {
			if !c {
				bc, c = []byte(s), true
			}
			bc[i] |= 0x20
		} else if v == '\\' {
			if !c {
				bc, c = []byte(s), true
			}
			bc[i] = '/'
		}
	}
	return B2S(bc)
}

// ToID is high performance function to bring filenames to lower case identifier
// with only letters, digits and '_', without superfluous allocations if it possible.
func ToID(s string) string {
	var b = S2B(s)
	var bc = b
	var n int
	for _, v := range b {
		if VarChar[v] || v == '/' {
			n++
		}
	}
	var c bool
	if n != len(b) {
		bc = make([]byte, n)
		c = true
	}
	var i int
	for _, v := range b {
		if VarChar[v] || v == '/' {
			if v >= 'A' && v <= 'Z' {
				if !c {
					bc, c = []byte(s), true
				}
				bc[i] = v | 0x20
			} else if c {
				bc[i] = v
			}
			i++
		}
	}
	return B2S(bc)
}

// VarChar is table for fast check that ASCII code is acceptable symbol of variable.
var VarChar [256]bool = func() (a [256]bool) {
	a['_'] = true
	for c := 'A'; c <= 'Z'; c++ {
		a[c] = true
		a[c+32] = true
	}
	for c := '0'; c <= '9'; c++ {
		a[c] = true
	}
	return
}()
