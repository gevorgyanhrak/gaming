module github.com/slotopol/server

go 1.27

require (
	go.uber.org/atomic v1.12.0
	go.yaml.in/yaml/v3 v3.0.6
	golang.org/x/crypto v0.59.0
)

require golang.org/x/sys v0.50.0 // indirect

// Advice: add to file `.vscode/settings.json` followed lines:
// {
//     "gopls": {
//         "buildFlags": ["-tags", "full keno agt aristocrat betsoft ct igt megajack netent novomatic playngo playtech"],
//     },
//     "Lua.diagnostics.disable": ["lowercase-global"]
// }

// Advice: upgrade this file by command line
//   go get -u ./...
//   go mod tidy
