---
title: messages_getDocumentByHash
description: messages_getDocumentByHash parameters, return type and example
---
## Method: messages\_getDocumentByHash  
[Back to methods index](index.md)


### Parameters:

| Name     |    Type       | Required |
|----------|:-------------:|---------:|
|sha256|[bytes](../types/bytes.md) | Required|
|size|[int](../types/int.md) | Required|
|mime\_type|[string](../types/string.md) | Required|


### Return type: [Document](../types/Document.md)

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

$Document = $MadelineProto->messages_getDocumentByHash(['sha256' => bytes, 'size' => int, 'mime_type' => string, ]);
```