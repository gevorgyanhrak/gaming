package util_test

import (
	"fmt"
	"testing"
	"unsafe"

	"github.com/slotopol/server/util"
)

func TestS2B(t *testing.T) {
	var s = "some string"
	var ps = unsafe.Pointer(unsafe.StringData(s))
	/*var b = []byte(s)
	var pb = unsafe.Pointer(unsafe.SliceData(b))
	if ps == pb {
		t.Error("string pointer is equal to pointer on new allocated bytes slice")
	}*/
	var b = util.S2B(s)
	var pb = unsafe.Pointer(unsafe.SliceData(b))
	if ps != pb {
		t.Error("string pointer is not equal to pointer on same bytes slice")
	}
}

func TestB2S(t *testing.T) {
	var b = []byte("some string")
	var pb = unsafe.Pointer(unsafe.SliceData(b))
	var s = string(b)
	var ps = unsafe.Pointer(unsafe.StringData(s))
	if pb == ps {
		t.Error("bytes slice pointer is equal to pointer on new allocated string")
	}
	s = util.B2S(b)
	ps = unsafe.Pointer(unsafe.StringData(s))
	if pb != ps {
		t.Error("bytes slice pointer is not equal to pointer on same string")
	}
}

func ExampleToLower() {
	fmt.Println(util.ToLower("C:\\Windows\\Temp"))
	// Output: c:\windows\temp
}

func ExampleToUpper() {
	fmt.Println(util.ToUpper("C:\\Windows\\Temp"))
	// Output: C:\WINDOWS\TEMP
}

func ExampleToKey() {
	fmt.Println(util.ToKey("C:\\Windows\\Temp"))
	// Output: c:/windows/temp
}

func ExampleToID() {
	fmt.Println(util.ToID("Joker Dolphin"))
	fmt.Println(util.ToID("Lucky Lady's Charm"))
	fmt.Println(util.ToID("BetSoft/2 Million B.C."))
	// Output:
	// jokerdolphin
	// luckyladyscharm
	// betsoft/2millionbc
}
