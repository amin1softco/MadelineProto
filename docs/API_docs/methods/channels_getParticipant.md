---
title: channels_getParticipant
description: channels_getParticipant parameters, return type and example
---
## Method: channels\_getParticipant  
[Back to methods index](index.md)


### Parameters:

| Name     |    Type       | Required |
|----------|:-------------:|---------:|
|channel|[InputChannel](../types/InputChannel.md) | Required|
|user\_id|[InputUser](../types/InputUser.md) | Required|


### Return type: [channels\_ChannelParticipant](../types/channels_ChannelParticipant.md)

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

$channels_ChannelParticipant = $MadelineProto->channels_getParticipant(['channel' => InputChannel, 'user_id' => InputUser, ]);
```