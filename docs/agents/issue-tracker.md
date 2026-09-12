# Issue tracker

Issues live in **GitHub Issues** on `nurullah44/trend-analyzer` (public). Use the `gh` CLI.

- Create an issue: `gh issue create --title "<title>" --body-file <file>`, then apply one triage label.
- Blocking edges: use GitHub's native issue dependencies where available; otherwise state `Blocked by: #12` at the end of the body.
- PRs as a request surface: **off** — pull requests are not part of the triage queue.
- Parent issues are never closed or modified by the skills that consume this file.

## Triage labels

The five canonical roles, label string equal to its name:

| Label | Meaning |
|---|---|
| `needs-triage` | Not yet judged |
| `needs-info` | Waiting on the owner |
| `ready-for-agent` | Fully specified, an agent can pick it up |
| `ready-for-human` | Needs a human decision or hands |
| `wontfix` | Deliberately not doing it |
