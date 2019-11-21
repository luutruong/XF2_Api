## Extended endpoints

All requests must be include there keys in headers:
* `XF-TApi-Key`
* `XF-TApi-Version`
* `XF-TApi-Token`: If omitted the request processed for Guest user.

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

### Encrypt password
Please see the method `Util\PasswordDecrypter::encrypt(...)`