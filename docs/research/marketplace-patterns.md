# Marketplace patterns applied to the game catalogue (2026-11-17)

Owner brief: study the leading marketplaces (Amazon, eBay, Etsy, Alibaba, Temu, AliExpress, Facebook
Marketplace, Airbnb) and bring their best browsing patterns into the app. This app is a game club, not a store:
nobody sells, buys or ships anything, and Fishkas are never bought or sold (house rule). So the patterns that
help people **find and come back to** things were taken; the commerce ones were left out on purpose.

## What the research says (sources)

- **Autocomplete** — Baymard: users treat suggestions as a starting point, not a speed-up; too many suggestions
  cause choice paralysis on desktop and mobile; label any product suggestions with their context; only a minority of sites
  get the details right. <https://baymard.com/blog/autocomplete-design>, <https://baymard.com/research/eCommerce-search>
- **Filters and sorting** — Baymard: filtering removes the irrelevant, sorting orders the relevant; show applied
  filters as an overview of removable chips above the results, with a reset, especially on mobile.
  <https://baymard.com/blog/how-to-design-applied-filters>, <https://baymard.com/learn/ecommerce-filter-ui>
- **Save / wishlist** — the heart is the recognised icon; the saved state must be visible and undoable (a
  disabled "saved" button confused users in a Bigbasket study); Etsy and Airbnb keep saving one tap away on cards.
  <https://www.thestory.is/en/journal/designing-wishlists-in-e-commerce/>,
  <https://medium.com/@bblife.at/enhancing-the-wishlist-experience-for-bigbasket-aa20999d5c72>,
  <https://baymard.com/guidelines/798-implementing-save-features>

## What shipped

| Marketplace pattern | Here | Why |
|---|---|---|
| Header search with suggestions (Amazon, Etsy) | `features/search/SiteSearch`: games and slots, at most 6, art + shelf, matched text in gold, ↑/↓/Enter, Enter on the text = every match on `/games?q=`; matches the Latin slug too ("durak" finds Դուրակ); full screen on phones | Search is how returning players jump to a game; a short labelled list per Baymard |
| Favourites (Etsy ♥, Airbnb saved) | `favorites` table + `GET/PUT/DELETE /me/favorites[/{game|slot}/{slug}]`; ♥ on every game and slot card (pressed state, optimistic, undo by tapping again); "Your favourites" shelf first on the dashboard; Favourites pill on `/games` | One tap to keep a game; the dashboard opens on what the player chose |
| Filters + sort + applied chips (eBay, Amazon) | `/games`: Players (2 / 3 or more), Sort (Recommended = admin order, Name, Playing now = real counts only); chips with ✕, Clear all, result count; all in the URL | A 23-game catalogue with slots needs narrowing; the URL keeps Back and shared links honest |
| Category browsing in a sheet (Airbnb, Temu mobile) | Home/dashboard banners open the category in a popup, full screen on phones (691ee21) | Browse without losing the place on the page |

## Left out, and why

- **Cart, checkout, payments, seller listings, offers** — nothing is sold; Fishkas are never bought or sold.
- **Reviews and star ratings of games** — would need moderation and enough players to mean anything; the
  rankings and real "playing now" counts are the trust signals today. Possible later with owner approval.
- **"Best seller" / "Trending" badges** — only real numbers may be shown (owner rule: never invent activity);
  "Playing now" sorts by the server's real count and puts unknown counts last.
- **Messaging** — tables already have reactions and quick messages; a DM system is a product decision.
