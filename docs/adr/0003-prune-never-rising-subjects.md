# 0003 — Prune Subjects that never rise; never prune one that alarmed

Daily counts for every tracked Subject would otherwise grow without limit, and most discovered Subjects never accelerate at all. A Subject that has not left Watching within 30 days is Archived and its raw rows dropped; anything that ever reached Rising, Trending or Mainstream is kept permanently, including its daily series, because those are the only rows that can answer "what did this look like a month before it took off".

This is deliberately asymmetric: the cheap data goes, the expensive data stays. It means the instrument cannot be re-tuned against Subjects it discarded, which is an accepted cost — a Subject that never rose carries no signal about what rising looks like.
