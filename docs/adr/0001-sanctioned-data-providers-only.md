# 0001 — Collect only from sanctioned data providers, never from pages

TikTok's research API excludes Turkey and is academic-only, Meta has no public trend API since CrowdTangle shut down, and scraping any of them breaches platform terms. We decided every Source must be an official API, RSS feed, public dataset, or a licensed third-party provider, and that neither the collectors nor the agents may fetch external pages on the analyzer's behalf — an agent reading pages in bulk is scraping with a friendlier name.

Consequence: TikTok, Instagram and Meta coverage depends on a provider that licenses that data at a price a single person can pay, or on public ad-data APIs. If no such provider exists, those platforms remain a gap rather than a risk. It also means the analyzer can always be run from an unattended schedule without breaking when a page layout changes.
