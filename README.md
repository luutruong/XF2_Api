# Add-on enhancement to XenForo API

This document only provides API which the add-on makes an enhancement. To view other API end points please go to [Official XenForo API docs](https://xenforo.com/community/pages/api-endpoints/)

All requests must be include there keys in headers:
* `XF-TApi-Key`
* `XF-TApi-Token`: If omitted the request processed for Guest user.

## Auth

### POST `/tapi-apps/auth`

Parameters:

* `username` (string) __required__
* `password` (string) __required__: Password must be encrypted.

Response:

```
{
  user: (user),
  accessToken: (string)
}
```

### POST `/tapi-apps/register`

Parameters:

* `username` (string) __required__
* `email` (string) __required__
* `password` (string) __required__: Password must be encrypted.
* `birthday` (string) __optional__: Birthday must be formatted `Y-m-d`. Eg: `2021-12-15`

Response:

```
{
  user: (user),
  accessToken: (string)
}
```

## Conversations

### GET `/conversations`

Get list conversations by current visitor. Requires valid user access token. [XenForo API Docs](https://xenforo.com/community/pages/api-endpoints/#route_get_conversations_)

### POST `/conversations`

Creating a conversation.

Extra parameters:

* `recipients` (string) __optional__: Create with conversation with many recipients. Each recipient name separate by comma (,).
* `tapi_recipients` (bool) __optional__: Include recipients in conversation object.
* [Other params](https://xenforo.com/community/pages/api-endpoints/#route_post_conversations_)

Response:

```
{
  "conversation: (conversation)
}
```

### GET `/conversations/:conversationId`

Get specific conversation details.

Extra parameters:

* `tapi_recipients` (bool) __optional__: Include recipients in conversation object.
* [Other params](https://xenforo.com/community/pages/api-endpoints/#route_get_conversations_id_)

Response:

```
{
  "conversation": (conversation)
}
```

### GET `/conversation-messages/:messageId/tapi-reactions`

Get reactions on specific conversation message.

Parameters:

* `reaction_id` (int) __optional__: Filter show specific reaction.

Response:

```
{
  "reactions": [
    (reaction)
  ]
}
```

## Featured Contents

### Feature object schema

The `(feature)` object returned by the endpoints below has the following shape:

```
{
  "featured_content_id": (int),
  "content_type": (string),         // e.g. "thread", "post", "xfrm_resource"
  "content_id": (int),
  "content_container_id": (int),    // e.g. forum/node id for a thread
  "content_user_id": (int),         // author of the underlying content
  "content_username": (string),
  "content_date": (int),            // unix timestamp
  "content_visible": (bool),
  "feature_user_id": (int),         // user who created the feature
  "feature_date": (int),            // unix timestamp
  "auto_featured": (bool),
  "always_visible": (bool),
  "title": (string),
  "snippet": (string),
  "image_url": (string),            // empty string when no image
  "content_link": (string),         // canonical URL to the underlying content
  "content": (thread|xfrm_resource|...|null) // see "The `content` field" below
}
```

#### The `content` field

`content` is only present at `VERBOSITY_VERBOSE` (returned by the list `GET`, single `GET`, and `POST` create/update endpoints — not by `DELETE`). Its shape **depends on `content_type`**: it is the standard XenForo API result of the underlying entity, at `VERBOSITY_NORMAL`. The full per-field schema is not duplicated here — refer to the official XenForo API endpoints docs for the matching entity.

| `content_type`   | `content` shape                       | Reference                                                                                    |
|------------------|---------------------------------------|----------------------------------------------------------------------------------------------|
| `thread`         | a `thread` object                     | [XF API — threads](https://xenforo.com/community/pages/api-endpoints/#type_thread)           |
| `xfrm_resource`  | a `resource` object (if XFRM is installed) | [XFRM API — resources](https://xenforo.com/community/pages/api-endpoints/#type_resource) |
| (other)          | the API result for the matching entity registered via the `featured_content_handler_class` content-type field | n/a |

The exact set of supported `content_type` values is whatever has registered a `featured_content_handler_class` in the running XenForo instance. Out of the box that is just `thread`; XFRM/XFMG and similar add-ons register their own. Call `GET /tapi-featured-contents` with no filter to see what is actually in use.

`content` is `null` when the underlying entity has been deleted or is not viewable by the current visitor.

The `(pagination)` object follows the standard XenForo API pagination shape:

```
{
  "current_page": (int),
  "last_page": (int),
  "per_page": (int),
  "shown_items": (int),
  "total_items": (int)
}
```

### GET `/tapi-featured-contents`

Get a paginated list of featured contents that the current visitor can view.

Parameters:

* `page` (int) __optional__
* `content_type` (string) __optional__: Filter by content type. Only supported content types are accepted (e.g. `thread`, `post`, `xfrm_resource`); other values are ignored.

Response:

```
{
  "features": [
    (feature),
    ...
  ],
  "pagination": (pagination)
}
```

### POST `/tapi-featured-contents`

Feature a piece of content. Requires the visitor to have permission to feature/unfeature the target content.

Parameters:

* `content_type` (string) __required__: Supported content type (e.g. `thread`, `post`, `xfrm_resource`).
* `content_id` (int) __required__: ID of the content to feature.
* `title` (string) __optional__: Override feature title.
* `snippet` (string) __optional__: Override feature snippet.
* `always_visible` (bool) __optional__
* `auto_featured` (bool) __optional__

Response:

```
{
  "feature": (feature)
}
```

Errors:

* `already_featured`: The content has already been featured.
* `validation_failed`: Returned with validation error details.

### GET `/tapi-featured-contents/:featuredContentId`

Get a single featured content by id.

Parameters:

* N/A

Response:

```
{
  "feature": (feature)
}
```

### POST `/tapi-featured-contents/:featuredContentId`

Update an existing featured content. Requires the visitor to have permission to feature/unfeature the underlying content.

Parameters:

* `title` (string) __optional__
* `snippet` (string) __optional__
* `always_visible` (bool) __optional__
* `auto_featured` (bool) __optional__
* `feature_date` (int) __optional__: Unix timestamp. Only applied when greater than 0.

Response:

```
{
  "feature": (feature)
}
```

Errors:

* `validation_failed`: Returned with validation error details.

### DELETE `/tapi-featured-contents/:featuredContentId`

Remove a featured content. Requires the visitor to have permission to feature/unfeature the underlying content.

Parameters:

* N/A

Response:

```
{
  "success": true
}
```

## Forums

### GET `/forums/:forumId/prefixes`

Parameters:

* N/A

Response:

```
{
  "prefix_groups": any[],
  "prefixes": [
    (prefix),
    ...
  ],
  "prefix_tree": int[],
}
```

### POST `/forums/:forumId/watch`

Parameters:

* N/A

Response:

```
{
  "message": (string)
}
```

### GET `/forums/:forumId/threads`

Parameters:

* `started_by` (string) __optional__: Filter threads were created by specific user name.
* `with_first_post` (bool) __optional__: Determine include FirstPost in the thread data.
* [Other params](https://xenforo.com/community/pages/api-endpoints/#route_get_forums_id_threads)

Response:

```
{
  "threads": [
    (thread),
    ...
  ]
}
```

## ME

### GET `/me/ignoring`

Parameters:

* N/A

Response:

```
{
  "users": [
    (user),
    ...
  ]
}
```

### POST `/me/ignoring`

Ignore a user

Parameters:

* `user_id` (int) __required__

Response:

```
{
  "message": (string)
}
```

### DELETE `/me/ignoring`

Unignore a user

Parameters:

* N/A

Response:

```
{
  "message": (string)
}
```

### GET `/me/watched-threads`

Parameters:

* `page` (int) __optional__

Response:

```
{
  "threads": [
    (thread),
    ...
  ],
  "pagination": (pagination)
}
```

## MISC

### POST `/tapi-batch`

Send a numerous requests in a single request. This API requires body is JSON which contains the following info:

```
[
  {
    "uri": (string),
    "method": (string),
    "params": {
      "foo": "baz",
      ...
    }
  }
]
```


## Posts

### POST `/posts/:postId/report`

Parameters:

* `message` (string) __required__

Response:

```
{
  "message": (string)
}
```

### GET `/posts/:postId/tapi-reactions`

Parameters:

* `reaction_id` (int) __optional__: Filter show specific reaction.

Response:

```
{
  "reactions": [
    (reaction)
  ]
}
```

## Profile Posts

### POST `/profile-posts/:profilePostId/report`

Parameters:

* `message` (string) __required__

Response:

```
{
  "message": (string)
}
```

### GET `/profile-posts/:profilePostId/tapi-reactions`

Parameters:

* `reaction_id` (int) __optional__: Filter show specific reaction.

Response:

```
{
  "reactions": [
    (reaction)
  ]
}
```

### POST `/profile-post-comments/:profilePostCommentId/report`

Parameters:

* `message` (string) __required__

Response:

```
{
  "message": (string)
}
```

### GET `/profile-post-comments/:profilePostCommentId/tapi-reactions`

Parameters:

* `reaction_id` (int) __optional__: Filter show specific reaction.

Response:

```
{
  "reactions": [
    (reaction)
  ]
}
```

## Threads

### GET `/threads/:threadId`

Parameters:

* `post_id` (int) __optional__
* `is_unread` (int) __optional__
* [Other params](https://xenforo.com/community/pages/api-endpoints/#route_get_threads_id_)

Response:

```
{
  "thread": (thread)
}
```

### POST `/threads/:threadId/watch`

Parameters:

* N/A

Response:

```
{
  "is_watched": (bool)
}
```

## Users

### GET `/users/:userId/following`

Parameters:

* `page` (int) __optional__

Response:

```
{
  "users": [
    (user),
    ...
  ],
  "pagination": (pagination)
}
```

### POST `/users/:userId/following`

Make current visitor follow this user

Parameters:

* N/A

Response:

```
{
  "message": (string)
}
```

### DELETE `/users/:userId/following`

Make current visitor unfollow this user

Parameters:

* N/A

Response:

```
{
  "message": (string)
}
```

### POST `/users/:userId/report`

Parameters:

* `message` (string) __required__

Response:

```
{
  "message": (string)
}
```

### GET `/users/:userId/threads`

Parameters:

* `page` (int) __optional__

Response:

```
{
  "threads": [
    (thread),
    ...
  ],
  "pagination": (pagination)
}
```

### DELETE `/me`

Self-delete user account.

Parameters:

* N/A

Response:

```
{
  "message": (string)
}
```

### POST `/tapi-apps/search`

Searching content...

Parameters:

* `keywords` __required__
* `search_type` __optional__. Allowed values: thread, post, user.
* `search_order` __optional__. Allowed values: date, relevance*

Response:

```
{
  "keywords": (string),
  "search_id": (int),
  "results": (any),
  "pagination": (pagination)
}
```

### POST `/tapi-apps/connected-accounts`

Associate with external account.

Parameters:

* `provider` __required__. Connected account provider ID.
* `token` __required__. The access token.

Response:

```
{
  "user": (user),
  "accessToken": (string)
}
```

### POST `/me/username`

Request to change username

Parameters:

* `username` __required__: New username
* `change_reason` __required__

Response:

```
{
  "message": (string),
  "changeState": (string),
}
```

### Encrypt password
Please see the method `Util\PasswordDecrypter::encrypt(...)`