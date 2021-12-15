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

Get list conversations by current visitor. Requires valid user access token.
https://xenforo.com/community/pages/api-endpoints/#route_get_conversations_

### POST `/conversations`

Creating a conversation. https://xenforo.com/community/pages/api-endpoints/#route_post_conversations_

Extra parameters:

* `recipients` (string) __optional__: Create with conversation with many recipients. Each recipient name separate by comma (,).
* `tapi_recipients` (bool) __optional__: Include recipients in conversation object.

Response:

```
{
  "conversation: (conversation)
}
```

### GET `/conversations/:conversationId`

Get specific conversation details. https://xenforo.com/community/pages/api-endpoints/#route_get_conversations_id_

Extra parameters:

* `tapi_recipients` (bool) __optional__: Include recipients in conversation object.

Response:

```
{
  "conversation": (conversation)
}
```


## Forums

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

## Profile Posts

## Threads

## Users

### GET `tapi-apps/`

Parameters:
* N/A

Response:
```
{
  "reactions": [
    {
      "reactionId": number,
      "text": string,
      "imageUrl": url
    },
    ...
  ],
  "apiVersion": number,
  "homeTabActive": string,
  "allowRegistration": boolean,
  "googleAnalyticsWebPropertyId": string
}
```

### GET `tapi-apps/trending-tags`

Parameters:
* N/A

Response:
```
{
  "tags": [
    "tag a",
    "tag b",
    ...
  ]
}
```

### GET `tapi-apps/newfeeds`

Parameters:
* order: (string) Currently support: new_threads, recent_threads, trending

Response:
```
{
    "threads": array
    "pagintion": object
}
```

### POST `tapi-apps/auth`
Attempt login and generate access token

Parameters:
* username: (string) (required)
* password: (string) (required) A encrypted password string.

Response:
```
{
    "user": object,
    "accessToken": string
}
```

### POST `tapi-apps/refresh-token`
Refresh existing token

Parameters:
* token: (string) (required)

Response:
```
{
    "user": object,
    "accessToken": string
}
```

### POST `tapi-apps/register`
Creating new user

Parameters:
* username: (string) (required)
* password: (string) (required) A encrypted password string
* email: (string) (password)

Response:
```
{
    "user": object,
    "accessToken": string
}
```

### POST `/tapi-apps/batch`
Do batch requests. You may pass json to request body. Each batch request has these keys:

* `method`: GET, POST
* `uri`: Target request
* `params`: Array of params which for this request

Response:

```
{
  "jobs": [
    ...
  ]
}
```

### Encrypt password
Please see the method `Util\PasswordDecrypter::encrypt(...)`
