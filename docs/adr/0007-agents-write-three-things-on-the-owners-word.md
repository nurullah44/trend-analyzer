# 0007 — Agents write three things, on the owner's word

ADR-0002 made both agent doors read-only, but the product's whole feedback loop is the owner's Verdicts, and an agent-native app that needs a separate UI to record them puts manual work back in. The owner already talks the week's Alarms over with an agent.

We decided the read layer has exactly three writes, exposed through the CLI, the MCP server and the inspection page alike: record a **Verdict** on an Alarm, attach or remove a **Label** on a Subject, and add a **seed** Subject by name and query. An agent calls them only when the owner says so in the conversation. Nothing else is writable from outside: agents never collect, never run the app, never publish or edit an Alarm, Evidence or a series.

Consequence: supersedes the "nothing writes back" sentence of ADR-0002. The weekly report can be produced by a scheduled agent run that reads, writes a report and records nothing.
