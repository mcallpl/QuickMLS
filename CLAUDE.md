# QuickMLS - Claude Code Instructions

## What This Is
QuickMLS — instant property intelligence. Type an address, get a full quick sheet with property details, comps on a map, and agent contact info. Uses Trestle/Cotality RESO API.

## Tech Stack
- PHP backend (no framework)
- Vanilla JS frontend
- Leaflet + OpenStreetMap for maps (free)
- Trestle/Cotality RESO Web API for MLS data
- Google Places for address autocomplete

## Deployment
- GitHub: mcallpl/QuickMLS
- Hosted on Digital Ocean

## End-of-Session Rule
Before ending any conversation where code was changed, ALWAYS:
1. `git add` all relevant changes
2. Commit with a descriptive message
3. `git push` to GitHub
Never leave uncommitted or unpushed work behind.
