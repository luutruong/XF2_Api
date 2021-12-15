# Add-on enhancement to XenForo API

Official XenForo API docs: https://xenforo.com/community/pages/api-endpoints/

All requests must be include there keys in headers:
* `XF-TApi-Key`
* `XF-TApi-Version`
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

### Encrypt password
Please see the method `Util\PasswordDecrypter::encrypt(...)`
