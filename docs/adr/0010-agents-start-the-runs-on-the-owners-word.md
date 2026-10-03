# 0010 — Agents start the two runs on the owner's word

Until the Linux server owns the clock (ADR-0009), the runs are started by hand — and the owner's hand is an agent: they ask Claude Code or pi to run the week and report back. Keeping agents away from the runs would put the manual work back.

We decided an agent may start exactly the two scheduled runs, `trends:daily` and `trends:weekly`, through the MCP server or the CLI, when the owner asks, and then report what came out. The runs are the same jobs the scheduler fires: idempotent, healing, never overlapping a running copy. Everything else stays as ADR-0007 set it — an agent never collects one Source, discovers or backtests on its own, and never edits an Alarm, Evidence or a series; its only writes are the owner's Verdicts, Labels and Seeds.

Consequence: amends ADR-0007 and the "never runs the app" rule. Once the server's scheduler fires the runs, an agent has no reason to start them except to heal a gap the report names.
