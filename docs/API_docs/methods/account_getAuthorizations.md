---
title: account_getAuthorizations
description: account_getAuthorizations parameters, return type and example
---
## Method: account\_getAuthorizations  
[Back to methods index](index.md)




### Return type: [account\_Authorizations](../types/account_Authorizations.md)

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

$account_Authorizations = $MadelineProto->account_getAuthorizations();
```