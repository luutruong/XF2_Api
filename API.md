# API reference — Truonglv/Api

This add-on layers a mobile-app API on top of the XenForo REST API. It does two things:

1. **Adds new endpoints** under the `tapi-*` route prefixes (`/tapi-apps`, `/tapi-app-notifications`, `/tapi-bookmarks`, `/tapi-featured-contents`, `/tapi-search`).
2. **Extends existing XenForo endpoints** with extra parameters, extra actions and extra response fields.

Only the add-on's own behaviour is documented here. For everything else — base parameters, base response shapes, entity field lists — see the [official XenForo API docs](https://xenforo.com/community/pages/api-endpoints/).

---

## Conventions

### Request headers

| Header | Required | Meaning |
|---|---|---|
| `XF-TApi-Key` | yes | Presence of this header switches the request onto the API key configured in the `tApi_apiKey` option. |
| `XF-TApi-Token` | no | 32-character access token identifying the user. Omitted or expired ⇒ the request runs as a guest. |
| `XF-TApi-Version` | no | Client app version. Stored on request logs (`tApi_logLength` option) and on push subscriptions. |

> **Note on `XF-TApi-Key`:** the add-on only checks that the header is non-empty; the value itself is never compared against the `key` sub-option. Any non-empty value authenticates as the configured API key. See `Listener::appApiValidateRequest()`.

When `XF-TApi-Key` is absent the add-on stays out of the way entirely and the request falls through to standard XenForo API authentication (`XF-Api-Key` / `XF-Api-User`).

### Authentication flow

```
POST /tapi-apps/auth          → accessToken + refreshToken
   ↓ (accessToken expires after tApi_accessTokenTtl seconds, default 3600)
POST /tapi-apps/refresh-token → new accessToken
   ↓
POST /tapi-apps/log-out       → invalidates the current accessToken
```

Refresh tokens live 30 days and their expiry is extended on each use.

Passwords are sent **as-is** over the wire — there is no client-side encryption step. (Older versions of this document described an encrypted-password scheme; no such decryption exists in the code.)

### Pagination

Endpoints that paginate accept `page` (int, 1-based) and return:

```json
{
  "current_page": 1,
  "last_page": 5,
  "per_page": 10,
  "shown_items": 10,
  "total_items": 47
}
```

Page size comes from the `tApi_recordsPerPage` option (default 10) unless stated otherwise.

### Success and error shapes

Endpoints that return no payload answer `{"success": true}`. Errors follow the XenForo API convention:

```json
{ "errors": [ { "code": "...", "message": "...", "params": {} } ] }
```

Where an endpoint returns a documented machine-readable error `code`, it is listed under that endpoint.

---

## App

### `GET /tapi-apps`

Bootstrap information for the client. No parameters.

```json
{
  "reactions": { "1": { "text": "Like", "imageUrl": "..." } },
  "apiVersion": 1000000,
  "allowRegistration": true,
  "defaultReactionId": 1,
  "defaultReactionText": "Like",
  "quotePlaceholderTemplate": "[QUOTE={content_type},{content_id}]",
  "allowedAttachmentExtensions": ["jpg", "png", "..."],
  "registerMinimumAge": 16,
  "connectedAccountProviders": { "apple": "apple" },
  "appName": "My Forum",
  "xfrmEnabled": true,
  "privacyPolicyUrl": "https://...",
  "tosUrl": "https://..."
}
```

`registerMinimumAge` is `0` when date of birth is not required at registration. `privacyPolicyUrl` / `tosUrl` are empty strings when disabled in the board options.

### `GET /tapi-apps/news-feeds`

A thread feed built from the visitor's viewable forums. The result set is persisted as a search so subsequent pages stay stable; reuse `search_id` when paging.

| Parameter | Type | Notes |
|---|---|---|
| `page` | int | |
| `search_id` | int | Reuse a previous result set. Valid for 1 hour and only for the user that created it. |
| `order` | string | `last_post_date` (default), `post_date`, `reply_count`, `view_count` |
| `direction` | string | `asc` / `desc` (default `desc`) |
| `unread` | bool | Forces `last_post_date desc` and limits to unread threads |
| `watched` | bool | Forces `last_post_date desc` and limits to watched threads |

```json
{
  "threads": [ /* thread objects, incl. FirstPost / LastPost */ ],
  "pagination": { },
  "search_id": 123,
  "next_url": "https://.../tapi-apps/news-feeds?search_id=123&page=2"
}
```

`next_url` is only present when a further page exists. When nothing matches, the response is `{"threads": [], "search_id": 0}`.

### `GET /tapi-apps/trending-tags`

Top 30 thread tags used in the last 7 days. Returns `{"tags": []}` when tagging is disabled board-wide.

```json
{ "tags": [ { "tag_id": 1, "tag": "..." } ] }
```

### `GET /tapi-apps/forum-name`

Resolve a forum by its URL name and return it in the same shape as `GET /forums/:forumId`.

* `name` (string) **required** — the node's `node_name`.

Returns `404` when no node has that name.

---

## Authentication

### `POST /tapi-apps/auth`

* `username` (string) **required**
* `password` (string) **required**
* `tfa_provider` (string) — required when two-step verification is enabled for the account
* `tfa_trigger` (bool) — ask the provider to send its code (e.g. email) instead of verifying

Rejected with `403` if the request already carries a valid user token.

When two-step verification is required, the response header `X-Api-Tfa-Providers` lists the available providers (comma separated) and the body is a `202` message. Repeat the request with `tfa_provider` plus the provider's input.

```json
{
  "success": true,
  "user": { },
  "accessToken": "…32 chars…",
  "expiresAt": 1750000000,
  "refreshToken": "…"
}
```

### `POST /tapi-apps/register`

* `username` (string) **required**
* `email` (string) **required**
* `password` (string) **required**
* `birthday` (string) — `Y-m-d`, e.g. `2001-12-15`

Returns the same body as `/tapi-apps/auth`. Fails with `400` when registration is disabled board-wide, and with validation errors otherwise.

### `POST /tapi-apps/connected-accounts`

Sign in or register through a connected-account provider (Apple, Google, …) using a token obtained on the device.

* `provider` (string) **required** — connected account provider id
* `token` (string) **required** — the provider's id token
* `type` (string) — `''` (default), `new`, `existing`, `test`
* `username`, `email`, `password` (string) — used when creating a new local account, or with `type=existing` to link the provider to an existing account (`username` + `password` are then the local credentials)

`type=test` only checks for an existing link and returns `400` when none is found — it never creates an account.

Returns the same body as `/tapi-apps/auth`.

### `POST /tapi-apps/refresh-token`

* `token` (string) **required** — a refresh token

Returns the auth body **without** a new `refreshToken`; the supplied refresh token is extended by 30 days instead. `404` when unknown, `403` when expired.

### `POST /tapi-apps/log-out`

No parameters. Deletes the access token carried in `XF-TApi-Token`. Always returns `{"success": true}`.

---

## Push subscriptions

### `POST /tapi-apps/subscriptions`

Register or unregister a device for push notifications. Requires a logged-in user.

| Parameter | Type | Notes |
|---|---|---|
| `device_token` | string | **required** — FCM registration token |
| `type` | string | **required** — `subscribe` or `unsubscribe` |
| `provider` | string | **required for `subscribe`** — currently `fcm` |
| `provider_key` | string | **required for `subscribe`** |
| `device_type` | string | `ios` or `android` — decides the platform-specific push payload |
| `is_device_test` | bool | Marks the device as a test device |

On `subscribe`, the same `device_token` registered to *other* users is removed first, so a token always maps to one user. `app_version` is taken from the `XF-TApi-Version` header, not from the body.

```json
{ "success": true, "subscription": { } }
```

`unsubscribe` returns `{"success": true}` with no `subscription`.

---

## In-app purchases

### `GET /tapi-apps/iap-products`

Active IAP products, ordered by display order. Requires a logged-in user.

```json
{ "products": [ { "product_id": 1, "platform": "ios", "store_product_id": "...", "user_upgrade_id": 2 } ] }
```

### `POST /tapi-apps/iap-verify`

Verify a store receipt and apply the matching user upgrade. Requires a logged-in user.

| Parameter | Type | Notes |
|---|---|---|
| `platform` | string | **required** — `ios` or `android` |
| `store_product_id` | string | **required** — must match a configured IAP product |
| `purchase` | string | **required** — the store's purchase object, JSON-encoded |
| `purchase_token` | string | **required** |
| `package_name` | string | Android package name |

```json
{ "success": true, "message": "...", "request_key": "..." }
```

Errors:

* `purchase_expired` — the purchase is no longer valid
* `400` with `tapi_iap_product_not_found` — no product matches `platform` + `store_product_id`
* `400` with `tapi_iap_product_already_subscribed` — the upgrade is already active

---

## Batch

### `POST /tapi-apps/batch`

Run several API calls in one HTTP request. The request body is a raw JSON array (not form-encoded):

```json
[
  { "uri": "tapi-apps", "method": "GET", "params": {} },
  { "uri": "threads/123", "method": "GET", "params": { "with_posts": true } }
]
```

`method` defaults to `GET` and `params` to `{}`. Each job is dispatched internally with the same authentication as the outer request.

```json
{
  "jobs": {
    "tapi-apps":   { "_job_result": "ok",    "_job_response": { } },
    "threads/123": { "_job_result": "error", "_job_error": [ ] }
  },
  "_job_timing": 0.0421
}
```

A job returns `_job_response` for data replies, `_job_message` for message replies, and `_job_error` for errors. A body that is not a JSON array fails with `invalid_batch_json_format`.

---

## Notifications

Alerts are limited to the content types the add-on supports: `conversation`, `conversation_message`, `post`, `profile_post`, `profile_post_comment`, `thread`, `trophy`, `user`, plus `resource` and `resource_update` when XFRM is installed and viewable.

### `GET /tapi-app-notifications`

Requires a logged-in user.

* `page` (int)
* `content_type` (string) — one type, or several separated by commas. Unsupported values are dropped; if nothing valid remains the result is empty.
* `unread` (bool) — only alerts that have not been read

```json
{ "notifications": [ ], "pagination": { } }
```

### `GET /tapi-app-notifications/:alertId`

The alert plus the content it points at. `404` when the alert's content type is unsupported, `403` when the alert belongs to another user.

```json
{ "notification": { }, "content": { } }
```

`content` is `null` when the target has been deleted or is not viewable. Depending on the alert type it is expanded with its parent (`post` → thread, `profile_post_comment` → profile post, `conversation_message` → conversation, `resource_update` → resource).

### `POST /tapi-app-notifications/:alertId/mark-read`

Mark one alert read. No parameters.

### `POST /tapi-app-notifications/read`

Mark **all** the visitor's alerts read. No parameters.

### `POST /tapi-app-notifications/viewed`

Mark **all** the visitor's alerts viewed (clears the unread counter without marking them read). No parameters.

---

## Bookmarks

### `GET /tapi-bookmarks`

Bookmarked content of one type, newest first. Requires a logged-in user. Fixed page size of 20.

* `content_type` (string) **required** — e.g. `post`, `xfrm_resource`
* `page` (int)

```json
{ "entities": [ ], "pagination": { } }
```

### `POST /tapi-bookmarks`

* `content_type` (string) **required**
* `content_id` (int) **required**
* `message` (string) — bookmark note
* `labels` (string) — comma separated labels

Returns `{"success": true}`, including when the content was already bookmarked.

### `DELETE /tapi-bookmarks`

* `content_type` (string) **required**
* `content_id` (int) **required**

Returns `{"success": true}`, including when no bookmark existed.

---

## Featured contents

### The `(feature)` object

```
{
  "featured_content_id": (int),
  "content_type": (string),         // e.g. "thread", "xfrm_resource"
  "content_id": (int),
  "content_container_id": (int),    // e.g. node id for a thread
  "content_user_id": (int),
  "content_username": (string),
  "content_date": (int),            // unix timestamp
  "content_visible": (bool),
  "feature_user_id": (int),
  "feature_date": (int),            // unix timestamp
  "auto_featured": (bool),
  "always_visible": (bool),
  "title": (string),
  "snippet": (string),
  "image_url": (string),            // empty string when no image
  "content_link": (string),
  "content": (thread|xfrm_resource|…|null)
}
```

`content` is only present at verbose verbosity (list `GET`, single `GET`, `POST`) and holds the standard XenForo API result of the underlying entity. Its shape depends on `content_type` — whatever entities have registered a `featured_content_handler_class` in the running installation (out of the box: `thread`). It is `null` when the target is deleted or not viewable.

### `GET /tapi-featured-contents`

* `page` (int)
* `content_type` (string) — ignored unless it is a supported featured content type

```json
{ "features": [ ], "pagination": { } }
```

### `POST /tapi-featured-contents`

Feature a piece of content. The visitor needs feature/unfeature permission on the target.

* `content_type` (string) **required**
* `content_id` (int) **required**
* `title` (string)
* `snippet` (string)
* `always_visible` (bool)
* `auto_featured` (bool)

Returns `{"feature": (feature)}`. Errors: `already_featured`, `validation_failed`.

### `GET /tapi-featured-contents/:featuredContentId`

Returns `{"feature": (feature)}`.

### `POST /tapi-featured-contents/:featuredContentId`

Update a feature. Same permission requirement as creating one. Every parameter is optional; only the ones present are applied.

* `title` (string)
* `snippet` (string)
* `always_visible` (bool)
* `auto_featured` (bool)
* `feature_date` (int) — unix timestamp, applied only when greater than 0

Returns `{"feature": (feature)}`. Error: `validation_failed`.

### `DELETE /tapi-featured-contents/:featuredContentId`

Returns `{"success": true}`.

---

## Search

Searchable types: `thread`, `post`, `user`, plus `resource` when XFRM is installed and viewable.

### `POST /tapi-search`

Run a search. Internally this creates a search record and re-routes to `GET /tapi-search/:searchId`, so the response is the result page itself.

* `keywords` (string) **required** — or `tag:<name>` to search by tag
* `search_type` (string) — one of the searchable types; anything else searches everything
* `search_order` (string) — `date` or `relevance` (only when the search backend supports relevance)

`search_type=user` is delegated to a username prefix lookup instead of the search index.

```json
{
  "keywords": "...",
  "search_id": 42,
  "results": [ /* each entry carries "content_type" and "content_id" extras */ ],
  "pagination": { }
}
```

Returns a `no_results_found` message when the keywords are shorter than the board's minimum word length, when the visitor cannot search, or when nothing matches.

### `GET /tapi-search/:searchId`

Page through an existing result set. Accepts `page`. A search created by another logged-in user returns `404`.

### `GET /tapi-search/tagged`

Threads carrying a tag, returned through the same shape as the search endpoints.

* `tag_name` (string) **required**
* `search_id` (int) — reuse a previous result set instead of re-running the lookup
* `page` (int)

`keywords` in the response is `tag:<tag>`.

### `GET /tapi-search/trending-queries`

Recently popular search queries logged by this add-on. No parameters.

```json
{ "queries": [ ] }
```

---

## Extensions to XenForo endpoints

### Reactions

The add-on adds a reaction *listing* endpoint to several content types. All of them behave identically:

* `GET /posts/:postId/tapi-reactions`
* `GET /profile-posts/:profilePostId/tapi-reactions`
* `GET /profile-post-comments/:profilePostCommentId/tapi-reactions`
* `GET /conversation-messages/:messageId/tapi-reactions`
* `GET /resource-updates/:resourceUpdateId/tapi-reactions` (XFRM)

Parameters: `reaction_id` (int) to show a single reaction type, `page` (int).

```json
{ "reactions": [ ], "pagination": { } }
```

### Reports

`POST /posts/:postId/report`, `POST /profile-posts/:profilePostId/report`, `POST /profile-post-comments/:profilePostCommentId/report`, `POST /users/:userId/report`.

* `message` (string) **required** — reason for the report

```json
{ "success": true, "message": "Thank you for reporting this content." }
```

### Quote placeholders

When posting a reply (`POST /posts`, `POST /conversation-messages`), the `message` body may contain placeholders of the form `[QUOTE=post,123]` / `[QUOTE=conversation_message,123]` (the exact template is returned as `quotePlaceholderTemplate` by `GET /tapi-apps`). Each placeholder is expanded server-side into a real quote block, or removed when the quoted content is not viewable.

`POST /conversation-messages` additionally accepts `quote_message_id` (int), which prepends a quote of that message to the reply.

### Forums

**`GET /forums/:forumId/prefixes`**

* `with_thread_stats` (bool) — add per-prefix thread counts (cached 10 minutes)

```json
{
  "prefix_groups": [ ],
  "prefixes": [ ],
  "prefix_tree": { "0": [1, 2] },
  "thread_stats": { "1": 42 }
}
```

`prefix_groups` is an empty array when the board has one group or fewer.

**`POST /forums/:forumId/watch`** — toggles the watch state. No parameters.

```json
{ "success": true, "is_watched": true }
```

**`GET /forums/:forumId/threads`** and **`GET /threads`** accept two extra filters:

* `started_by` (string) — username of the thread starter. An unknown name yields an empty result rather than an error.
* `with_first_post` (bool) — include `FirstPost` in each thread

### Threads

**`GET /threads/:threadId`** — two extra ways to pick the page of posts to return:

* `post_id` (int) — jump to the page containing this post
* `is_unread` (bool) — jump to the page containing the first unread post

**`POST /threads`** accepts `tag_names` (string, comma separated) as an alternative to the core `tags[]` array.

**`POST /threads/:threadId/watch`** — toggles the watch state (watch without email). No parameters.

```json
{ "success": true, "is_watched": true }
```

**`POST /threads/:threadId/poll-vote`**

* `responses` (int[]) **required** — the poll response ids to vote for

```json
{ "success": true, "poll": { } }
```

**`POST /threads/:threadId/viewed`** — log a thread view. No parameters.

### Conversations

**`GET /conversations`**

* `started_by` (string) — username of the conversation starter
* `received_by` (string) — username of a recipient
* `with_last_message` (bool) — include the last message on each conversation
* `tapi_recipients` (bool) — add a `tapi_recipients` extra to each conversation (`user_id`, `username`, `avatar_urls`)

**`POST /conversations`**

* `recipients` (string) — recipient usernames separated by commas, as an alternative to the core `recipient_ids[]`

`with_last_message` and `tapi_recipients` are forced on for this endpoint, so the created conversation always comes back with its last message and recipient list. An unknown username fails with `recipient_not_found`.

**`GET /conversations/:conversationId`**

* `tapi_recipients` (bool) — as above

Fetching a conversation also marks it read for the visitor.

**`GET /conversations/:conversationId/message-ids`** — every message id in the conversation, oldest first. No parameters.

```json
{ "message_ids": [1, 2, 3], "total": 3, "unread_message_id": 2 }
```

`unread_message_id` is `0` when everything has been read.

**`GET /conversations/:conversationId/recipients`**

* `with_conversation` (bool) — also return the conversation object

```json
{ "recipients": [ ], "conversation": { } }
```

**`POST /conversations/:conversationId/recipients`** — alias of the core invite endpoint; takes the same parameters.

**`GET /conversations/:conversationId/messages`** — three extra ways to select messages:

* `message_ids` (int[]) — fetch specific messages, max 50. In this mode `pagination` is `null`; more than 50 ids fails with `tapi_message_ids_too_long`.
* `message_id` (int) — jump to the page containing this message
* `is_unread` (bool) — jump to the page containing the first unread message

### Users

**`GET /users/:userId/following`**

* `page` (int)
* `order` (string) — `last_activity` to order by the followed user's last activity
* `direction` (string) — `asc` / `desc` (default `desc`)

```json
{ "users": [ ], "pagination": { } }
```

**`POST /users/:userId/following`** / **`DELETE /users/:userId/following`** — follow / unfollow. No parameters. Both return `{"success": true}`, including when already in the requested state.

**`GET /users/:userId/threads`** — threads started by the user, filtered to forums the visitor can view.

* `page` (int)

```json
{ "threads": [ ], "pagination": { } }
```

**`GET /users/find-names`**

* `names` (string) **required** — comma separated usernames

```json
{ "users": [ ] }
```

Unlike the core name-lookup endpoint this matches whole usernames, not prefixes.

### Me

**`GET /me/ignoring`** — users the visitor ignores. No parameters.

```json
{ "users": [ ] }
```

**`POST /me/ignoring`** / **`DELETE /me/ignoring`**

* `user_id` (int) **required**

**`GET /me/watched-threads`**

* `page` (int)

```json
{ "threads": [ ], "pagination": { } }
```

**`POST /me/avatar`** — same as core, but the response also includes the updated `user` object.

**`POST /me/cover`** — upload a profile banner.

* `file` (file) **required**

```json
{ "user": { } }
```

**`DELETE /me/cover`** — remove the profile banner. No parameters.

**`POST /me/username`** — request a username change.

* `username` (string) **required**
* `change_reason` (string) — required when the board option `usernameChangeRequireReason` is set

```json
{ "success": true, "message": "...", "changeState": "approved" }
```

`changeState` is `approved` when the change took effect immediately, otherwise it is awaiting moderation.

**`DELETE /me`** — self-delete the account. No parameters. Requires the `tApi_allowSelfDelete` option to be on; administrators, super administrators and moderators can never self-delete. The account is renamed to `guest-<timestamp>`, then deleted, and all of the user's access and refresh tokens are removed.

### XFRM (resources)

Available when the XenForo Resource Manager is installed and the visitor can view resources.

**`POST /resources/:resourceId/watch`** — toggles the watch state. No parameters.

```json
{ "success": true, "is_watched": true }
```

**`POST /resource-updates/:resourceUpdateId/react`** — react to a resource update; same parameters as other core react endpoints.

**`GET /resource-updates/:resourceUpdateId/tapi-reactions`** — see [Reactions](#reactions).

---

## Related options

| Option | Effect on the API |
|---|---|
| `tApi_apiKey` | The XenForo API key requests are executed under |
| `tApi_accessTokenTtl` | Access token lifetime in seconds (default 3600) |
| `tApi_recordsPerPage` | Default page size (default 10) |
| `tApi_logLength` | Request logging: `0` disables it |
| `tApi_appName` | Reported by `GET /tapi-apps` |
| `tApi_caAppleProviderId` | Apple connected-account provider id reported by `GET /tapi-apps` |
| `tApi_reactions` | Reaction set reported by `GET /tapi-apps` and reaction endpoints |
| `tApi_firebaseConfigPath` | Path to the Firebase service-account JSON used for push notifications |
| `tApi_encryptKey` | Key used to sign the `misc/tapi-goto` link proxy |
| `tApi_allowSelfDelete` | Gates `DELETE /me` |
