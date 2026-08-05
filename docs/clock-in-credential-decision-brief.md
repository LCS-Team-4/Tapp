# Clock-In Credential & Fraud Prevention — Decision Brief for the Team

**Why this exists:** this thread worked through how an employee should actually
prove "it's me, right now, here" at the terminal. It started as a two-part
question (Decision 1: backend-mediated or not; Decision 2: what physical
mechanism) and Decision 2 turned into a real design exploration once fraud
resistance came into it. Recording the full trail — not just the ending —
because the reasoning at each step is what the team needs to weigh in on, not
just the conclusion. This is a companion to the earlier
`terminal-architecture-decision-brief.md` (which covers reconciling with the
`taaraa` branch); this one goes deeper specifically on Decision 2.

Nothing below is finalized. It ends with an explicit list of what the team
needs to actually decide.

---

## Decision 1 — settled

**The terminal talks to the backend API (token-mediated), not the database
directly.** No disagreement on this one; see the earlier brief for the full
reasoning (centralized business logic, terminal never holds DB credentials,
fits Xneelo's hosting model).

---

## Decision 2 — how an employee proves presence at the terminal

### Starting point

Original proposal, verbatim:

> Employee opens PWA > Click clock in > Phone generates a unique id (whether
> this needs to be exposed/showed somewhere im not sure or whether it can
> just be a "background process") > Taps phone to the reader > clocked in.
> ...What if someone does not have a phone? — build the NFC tag/card
> fallback (reason for phone is to save company money instead of buying many
> cards). What if someone does not have NFC? — could a Bluetooth fallback
> work, or remove both and just require internet to be on.

This maps directly onto `spec.md` §5's already-documented "tap-to-write"
candidate (phone writes a signed token to the NFC tag, the reader picks it
up, forwards it to the backend) — so it wasn't a new direction, it was
closing out an already-scoped open question.

**On the exposure question:** resolved — no, it doesn't need to be shown
anywhere. The original QR-code idea was specifically for *camera*-based
scanning, which needs something visible. NFC transfers over radio; the whole
operation can be a background process triggered by the "Clock In" button
tap and completed by the physical tap.

### Finding: Web NFC and Web Bluetooth are both Android-only

This is the fact that reshaped everything downstream, so worth stating
precisely:

- **Web NFC API** ships only in Chrome, Edge, Opera, and Samsung Internet on
  Android. Safari (macOS and iOS), Firefox, and all desktop browsers have
  zero support. This isn't a setting to enable — Apple has never exposed NFC
  to web content on iOS at all, only to native apps. Global browser support
  sits around 6%.
- **Web Bluetooth API** has the identical shape: Chromium-only, no native
  iOS support. The only iOS workaround is a third-party Safari extension
  (iOSWebBLE) — meaning every iPhone employee would need to install a
  browser extension just to clock in. Not a reasonable ask, so Bluetooth
  doesn't actually close the device gap either.

**Implication:** any phone-side NFC or Bluetooth mechanism only covers
Android users. In a school context, iPhone ownership is a large,
unpredictable fraction of staff — this isn't an edge case, it fails for a
real chunk of the org.

**Revised recommendation at this point:** flip the priority. A registered
physical card, read directly by the terminal, works identically regardless
of phone OS — the employee's browser is never involved. Phone-NFC-write
becomes an optional Android convenience layered on top, not the thing the
system depends on. On the original cost concern (buying cards to avoid):
NTAG213 cards run roughly $0.20–$1.50 each depending on quantity — for a
small staff, a two-figure total spend, not a real budget line.

This also collapses the two fallback questions into one: with cards as
primary, "no NFC on the phone" stops being a distinct failure mode — the
fallback for a missing/incompatible phone is just "use the card," the same
single fallback either way. Bluetooth adds a mechanism that doesn't cover
any device Web NFC doesn't already fail to cover, for the same iOS reason —
not worth building.

### Pushback: cards alone are weak on fraud

Correct catch — plain card-tap is the textbook "buddy punching" problem:
whoever holds the card gets recorded as that person. Phone-primary doesn't
actually solve this either — it only ever covered the Android slice; every
iPhone employee was always going to end up on the card path regardless, so
phone-primary just narrowed who the fraud gap applied to, it didn't close it.

**First mitigation attempt: tap-then-phone-confirm.** Card tap creates a
*pending* clock-in; the employee gets a prompt on their own phone (any OS,
plain internet request, no Web NFC/Bluetooth needed) with a short window to
confirm. Gets universal device coverage plus a second factor beyond just
holding the card.

**Flaw, caught immediately:** *"I can give you my card and then just confirm
from home or on the highway."* Correct — a phone notification confirmable
from anywhere proves possession of an authenticated device, nothing about
location. The confirm step answered "who authorized this," not "were they
actually there" — which was the entire point of a physical terminal in the
first place.

**General principle that fell out of this:** any step meant to prove
presence has to be tied to something physically constrained — radio range,
network topology, or line of sight to the terminal. An internet-connected
confirmation, however implemented, isn't that.

**Two location-bound fixes considered, with honest costs:**

| Option | How | Closes | Doesn't close | Cost |
|---|---|---|---|---|
| WiFi-range-gated confirm | Confirm only succeeds if the phone is on the building's WiFi, window shrunk to ~60–90s | Confirming from home/highway | Someone in the parking lot within WiFi range at the right moment | Cheap — logic only, no new hardware |
| Rotating code on a terminal screen | Code refreshes every 30–60s, visible only in person; employee types it into the app | Tighter — requires literally seeing the screen | — | Real — needs a small display added to the Pi, new hardware |

**Explicitly ruled out: GPS.** Trivially spoofable on Android (mock-location
is a stock developer setting), unreliable indoors, and turns this into a
staff location-tracking feature — a bigger privacy question than this system
needs to open.

### Better idea: physical placement + human confirmation

Proposal: put the terminal at reception; admin gets a push notification and
must confirm after each tap.

**Why this is actually the strongest option so far:** it doesn't try to
prove presence with a digital signal at all. Every prior attempt (WiFi
range, rotating code) was software trying to approximate a physical
guarantee. A person at reception watching the reader *is* the physical
guarantee — a stronger primitive than anything digital proposed before it.

**Real edges worth deciding on, not just noting:**

- **Don't gate on it.** Reception isn't staffed every second (lunch,
  walk-ins, after admin leaves for the day). If confirmation blocks the
  clock-in, those become dead zones. Better: record the tap immediately with
  the real timestamp; confirmation is a review/flag layer on top, not a
  lock.
- **Rubber-stamp risk.** Once "tap approve" happens dozens of times a day, it
  tends to become reflexive rather than actually verified — a known failure
  mode for any high-frequency manual approval step. If that happens here,
  the system quietly degrades to "logged but not really verified," same
  position as a plain card tap, just with extra steps. Worth being honest
  about whether this holds up for the actual staff size and rush-hour
  pattern.
- **Single point of failure.** As described, all attendance depends on one
  person's phone being on and attended to. Needs more than one person able
  to confirm — a role, not a hardcoded individual.
- **Who confirms admin's own clock-in?** Not resolved as stated — either
  admin self-confirms (reopens the remote-confirm hole for a smaller group)
  or a second person covers it.
- **Implementation note:** favor the admin dashboard's live feed (already
  planned) with a confirm button over OS push notifications — Web Push has
  its own cross-browser reliability gaps, same category of problem as the
  NFC/Bluetooth issue above. Admin sits at a fixed desk near the reader
  anyway, so a dashboard item fits naturally; polling or server-sent events,
  no new platform dependency.

**Cheap alternative, legitimate on its own:** skip active per-tap
confirmation entirely. Rely on physical placement (people are less likely to
hand over a card in front of someone) plus normal admin review of the live
feed for anything that looks off. Less robust in real time, but close to
zero extra build cost — no pending state, no confirm endpoint, no queue.

### Why OAuth isn't the right tool here

Question raised: could the employee app use OAuth to confirm each action?

OAuth solves **delegated authorization** — App A getting limited access to
something App B holds on a user's behalf, without A ever seeing the user's B
credentials. That's inherently a three-party problem. Here there's no third
party — the employee is already directly authenticated to TAPP's own
session. Building OAuth machinery (authorization server, client
registration, token exchange) to answer "is this really them right now"
would be significant infrastructure for a question the existing session
already answers.

More importantly, it doesn't fix anything still open: whatever the
confirmation mechanism, OAuth doesn't add a location guarantee any more than
a push button does.

Two things that *are* relevant, surfaced by this question:

- **WebAuthn/passkeys** (Face ID/fingerprint via the browser's Credential
  Management API) — strengthens *identity freshness* of whichever
  confirmation step gets chosen. A stolen, unlocked phone can tap a plain
  approve button; it can't pass a fresh biometric prompt. Worth adding on
  top of whichever mechanism below is chosen, not a replacement for any of
  them — it answers the identity axis, not the location axis.
- **Device-authorization-grant pattern** (the "code on a TV, approve on your
  phone" shape) — architecturally the same idea as the rotating-code option
  already on the table, just a different label for it.

---

## What the team actually needs to decide

1. **Primary credential medium:** physical card (universal, recommended
   given the Android-only reality of Web NFC/Bluetooth) vs. phone-tap as
   primary with card as fallback (simpler mentally, but structurally excludes
   iPhone users from the primary flow).
2. **Fraud-prevention layer on top of the card tap** — pick one:
   - None: log everything, admin reviews the feed after the fact (cheapest,
     weakest real-time guarantee)
   - WiFi-range-gated phone confirm, short window (cheap, moderate
     proximity guarantee)
   - Rotating code on a terminal display (strongest proximity guarantee,
     needs new hardware)
   - Reception placement + human confirmation via the admin dashboard
     (strongest available guarantee, needs a confirmer-role decision and
     careful non-gating design)
3. **If reception-confirmation is chosen:** gate or non-gate (recommend
   non-gate); single confirmer or a role with multiple people; who confirms
   admin's own clock-in.
4. **Whether to layer WebAuthn/passkey verification** onto whichever
   confirmation step is chosen, to strengthen identity freshness
   independent of the location question.
5. **Budget check:** confirm NTAG cards (and a small display, if the
   rotating-code option is chosen) fall inside whatever "no additional
   hardware purchase" constraint was originally set — likely a non-issue
   given the per-unit costs above, but worth confirming rather than
   assuming.
