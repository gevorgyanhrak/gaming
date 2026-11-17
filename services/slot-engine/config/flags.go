// Our engine keeps only the original logger of this package (logger.go); the server configuration it held
// (database, JWT, Brevo e-mail, clubs) was removed with the platform parts — see UPSTREAM.md.
package cfg

var (
	// Silent turns the log off.
	Silent bool
	// Verbose is the log level: 0 errors, 1 warnings, 2 info, 3 debug.
	Verbose = 1
)
