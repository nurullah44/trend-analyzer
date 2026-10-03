# 0008 — Archive without deleting while thresholds are placeholders

ADR-0003 dropped the series of Subjects that never rose after thirty days. While the Trend Score thresholds are a placeholder, those rows are exactly what retuning needs, and at this volume they cost almost nothing to keep.

We decided a Subject that stays in Watching for thirty days is Archived and stops being measured, but its rows stay. Pruning returns only when the store's size is a real problem.

Consequence: supersedes ADR-0003.
