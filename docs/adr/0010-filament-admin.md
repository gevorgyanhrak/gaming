# 0010. Admin panel in Filament

**Status:** Accepted (2026-10-27)

## Context
The admin panel is a major requirement made mostly of CRUD screens: searchable, filterable,
paginated tables, forms, uploads, dashboards and RBAC.

## Decision
Build the admin panel with Filament inside the Laravel app, on its own host (`admin.`), with its own
session login and MFA. The public product UI remains React.

## Consequences
- Estimated 5–8 days instead of 12–18 for a custom React admin.
- Administrative operations are not exposed through the public REST API.
- A second UI technology (Livewire) exists, limited to internal screens.
