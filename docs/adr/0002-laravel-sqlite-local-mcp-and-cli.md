# 0002 — Laravel, SQLite and a local schedule; MCP for Claude Code, CLI for pi

The owner ruled out Node, Python and Kotlin: the frontend is plain HTML/CSS/JS, and any backend is Laravel. The analyzer is therefore a Laravel app rendering Blade pages with unbundled ES modules, storing everything in one SQLite file in WAL mode on the Linux filesystem, never on `/mnt/c`.

It runs on the owner's WSL machine. Reddit's JSON endpoints returned 403 to a datacenter IP during research, so the collectors need a residential connection, and local also costs nothing. The weekly trigger comes from Windows Task Scheduler invoking `wsl.exe`, not from in-distro cron: WSL distros stop on host sleep (microsoft/WSL#8763, open since 2022) and their clocks drift after waking, so Windows has to own the clock. Jobs are idempotent upserts with `withoutOverlapping()` so a missed or doubled run is harmless.

Data leaves the app through two read-only doors over one query layer: an MCP server (`laravel/mcp`) that Claude Code consumes, and an Artisan CLI plus a pi skill, because pi has no MCP support by design. Nothing writes back into the analyzer — Verdicts, labels and the owner's idea store belong to the agent and the owner.

Consequence: no bundler, no JS framework, no queue infrastructure, and no hosting until the UI needs to leave the network. Anything that later needs concurrent writers (multi-user input, a hosted UI) is the point at which SQLite is revisited, not before.
