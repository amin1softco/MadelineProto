---
title: auth_checkPassword
description: auth_checkPassword parameters, return type and example
---
## Method: auth\_checkPassword  
[Back to methods index](index.md)


### Parameters:

| Name     |    Type       | Required |
|----------|:-------------:|---------:|
|password\_hash|[bytes](../types/bytes.md) | Required|


### Return type: [auth\_Authorization](../types/auth_Authorization.md)

### Example:


```
$MadelineProto = new \danog\MadelineProto\API();
if (isset($token)) {
    $this->bot_login($token);
}
if (isset($number)) {
    $sentCode = $MadelineProto->phone_login($number);
    echo 'Enter the code you received: ';
    $code = '';
    for ($x = 0; $x < $sentCode['type']['length']; $x++) {
        $code .= fgetc(STDIN);
    }
    $MadelineProto->complete_phone_login($code);
}

$auth_Authorization = $MadelineProto->auth_checkPassword(['password_hash' => bytes, ]);
```