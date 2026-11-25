# Application guide

## What Effemeral is for

Effemeral helps you discover full albums, decide which ones you want to hear, organise them into named album playlists, and send a reviewed playlist to Spotify.

The catalogue is the centre of the application. Imports supply listening history and supporting catalogue information; they are not the main destination.

Last.fm is the source of truth for listening history. Spotify supplies catalogue and playlist context and is the destination for an explicit playlist sync. MusicBrainz adds artist identity and origin metadata only to records already grounded in Last.fm or Spotify data; it does not establish catalogue provenance.

## Page map

| Page | Purpose |
| --- | --- |
| Overview | See catalogue, incomplete albums, playlist activity, and import status. |
| Playlists | Create, reopen, rename, delete, reorder, and manage Effemeral album playlists. |
| Catalogue | Search and filter artists and albums by artist, genre, country, release year, and completion. |
| Styles | Browse the accepted genre/style vocabulary used by catalogue filters. |
| Library | Review saved Spotify albums and library-backed catalogue context. |
| Listening | Browse the Last.fm listening archive and scrobble summaries. |
| Imports | Start imports, inspect provider status, retry failures, and review warnings. |
| Recommendations | Review, reorder, exclude, and turn album suggestions into a playlist draft. |
| Spotify Archive | Browse playlists imported from Spotify as supporting context. |
| Artist page | View an artist’s country, location, genres, albums, and provider links. |
| Album page | View release information, tracks, genres, completion, and associated playlists. |
| Genre page | View catalogue artists and albums associated with a normalized genre. |

All application pages use the private-access middleware. The local URL is `https://app.effemeral.local`.

## Normal workflow

### Keep source data current

Import Last.fm history and the Spotify collections that matter to the catalogue. Last.fm scrobbles provide completion evidence. Spotify saved albums and imported playlists provide catalogue context.

Do not treat Spotify saved albums or playlist membership as proof that an album has been heard.

### Find albums

Use Catalogue to search by artist or album, or filter by normalized genre, country, release year, and listening completion. Open an artist or album to inspect its tracks, genres, provider identifiers, and related playlists before adding it.

Completed albums are excluded from discovery by default for the selected listening window. Album-level manual completion overrides remain available when imported evidence is not enough.

### Build an Effemeral playlist

Create a playlist from the Playlists page. A name is suggested from the playlist contents and stored name options; it can be edited inline. Refreshing the suggestion generates another name without immediately reusing recent suggestions.

The playlist stores albums and their order. Its management page allows you to:

- move an album up or down;
- remove an album locally;
- rename the playlist or refresh its suggested name;
- export the current contents as CSV;
- open a Spotify sync review.

Deleting an Effemeral playlist removes the local playlist and its album membership. It does not remove the corresponding Spotify playlist or tracks.

### Review and sync

The sync review compares the Effemeral album order with the selected Spotify destination. It shows available and missing tracks, additions, removals, and albums that block the sync.

An incomplete album blocks synchronization so that a playlist cannot silently contain only part of an album. Resolve missing tracks through Spotify enrichment, remove the album, or make the appropriate manual catalogue decision before trying again.

Nothing changes in Spotify until the review is confirmed.

## Completion states

Completion is based on Last.fm listening evidence and can be adjusted manually at album level.

- `Not heard` means no qualifying listening evidence is present.
- `Partially heard` means some, but not all, canonical album tracks have been heard.
- `Completed` means all canonical tracks have qualifying evidence or a manual completion override says the album is complete.
- A manual incomplete override keeps an album eligible even when imported evidence would otherwise mark it complete.

The interface may change its labels, but it should continue to distinguish imported evidence from manual decisions.

## Recommendations

Recommendations are reviewable album drafts, not automatic Spotify changes. Discovery can use an artist or album as context, a normalized genre, a release year, and a listening window such as 7 days, 1 month, the current year, 1 year, or all time.

Suggestions prioritize albums that have not been completed and can be reordered, excluded, added to a playlist draft, and reviewed before Spotify sync. The broader style-similarity algorithm is a later phase; current recommendations should remain explainable from the catalogue data shown in the review.

## Spotify archive

The Spotify Archive page lets you search imported Spotify playlists and inspect their contents locally. It is an audit and discovery view. It does not make Spotify playlist membership the canonical listening history and does not perform bidirectional editing.
