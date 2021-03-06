<?php
/*
Copyright 2016 Daniil Gentili
(https://daniil.it)
This file is part of MadelineProto.
MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU Affero General Public License for more details.
You should have received a copy of the GNU General Public License along with MadelineProto.
If not, see <http://www.gnu.org/licenses/>.
*/

namespace danog\MadelineProto\TL;

class TL extends \danog\MadelineProto\Tools
{
    public function __construct($files)
    {
        \danog\MadelineProto\Logger::log('Loading TL schemes...');
        $this->constructors = new \danog\MadelineProto\TL\TLConstructor();
        $this->methods = new \danog\MadelineProto\TL\TLMethod();
        foreach ($files as $type => $file) {
            $type = $type === 'mtproto';
            $TL_dict = json_decode(file_get_contents($file), true);

            \danog\MadelineProto\Logger::log('Translating objects...');
            foreach ($TL_dict['constructors'] as $elem) {
                $this->constructors->add($elem, $type);
            }

            \danog\MadelineProto\Logger::log('Translating methods...');
            foreach ($TL_dict['methods'] as $elem) {
                $this->methods->add($elem);
            }
        }
    }

    public function get_named_method_args($method, $arguments)
    {
        $tl_method = $this->methods->find_by_method($method);
        if ($tl_method === false) {
            throw new Exception('Could not extract type: '.$method);
        }

        if (count(array_filter(array_keys($arguments), 'is_string')) == 0) {
            $argcount = 0;
            $newargs = [];
            foreach ($tl_method['params'] as $current_argument) {
                $newargs[$current_argument['name']] = $arguments[$argcount++];
            }
            $arguments = $newargs;
        }

        return $arguments;
    }

    public function serialize_bool($bool)
    {
        return \danog\PHP\Struct::pack('<i', $this->constructors->find_by_predicate('bool'.($bool ? 'True' : 'False'))['id']);
    }

    public function deserialize_bool($data)
    {
        $id = \danog\PHP\Struct::unpack('<i', $data)[0];
        $tl_elem = $this->constructors->find_by_id($id);
        if ($tl_elem === false) {
            throw new Exception('Could not extract boolean');
        }

        return $tl_elem['predicate'] === 'boolTrue';
    }

    public function serialize_object($type, $object)
    {
        switch ($type['type']) {
            case 'int':
                if (!is_numeric($object)) {
                    throw new Exception("given value isn't numeric");
                }

                return \danog\PHP\Struct::pack('<i', $object);
            case '#':
                if (!is_numeric($object)) {
                    throw new Exception("given value isn't numeric");
                }

                return \danog\PHP\Struct::pack('<I', $object);
            case 'long':
                if (!is_numeric($object)) {
                    var_dump($object);
                    throw new Exception("given value isn't numeric");
                }

                return \danog\PHP\Struct::pack('<q', $object);
            case 'int128':
            case 'int256':
            case 'int512':
                return (string) $object;
            case 'double':
                return \danog\PHP\Struct::pack('<d', $object);
            case 'string':
            case 'bytes':
                $l = strlen($object);
                $concat = '';
                if ($l <= 253) {
                    $concat .= \danog\PHP\Struct::pack('<b', $l);
                    $concat .= $object;
                    $concat .= pack('@'.$this->posmod((-$l - 1), 4));
                } else {
                    $concat .= $this->string2bin('\xfe');
                    $concat .= substr(\danog\PHP\Struct::pack('<i', $l), 0, 3);
                    $concat .= $object;
                    $concat .= pack('@'.$this->posmod(-$l, 4));
                }

                return $concat;
            case 'Bool':
                if (!is_bool($object)) {
                    throw new Exception("given value isn't a boolean");
                }

                return $this->serialize_bool($object);
            case 'true':
                return;
            case '!X':
                return $object;
            case 'Vector t':
                $concat = \danog\PHP\Struct::pack('<i', $this->constructors->find_by_predicate('vector')['id']);
                $concat .= \danog\PHP\Struct::pack('<i', count($object));
                foreach ($object as $current_object) {
                    $concat .= $this->serialize_object(['type' => $type['subtype']], $current_object);
                }

                return $concat;

        }
        $auto = false;
        if (!isset($object['_'])) {
            $constructorData = $this->constructors->find_by_predicate($type['type']);
            if ($constructorData === false) {
                throw new Exception('Predicate was not set!');
            }
            $auto = true;
            $object['_'] = $constructorData['predicate'];
        }
        $predicate = $object['_'];

        $constructorData = $this->constructors->find_by_predicate($predicate);
        if ($constructorData === false) {
            throw new Exception('Could not extract type: '.$object);
        }

        if ($bare = ($type['type'] != '' && $type['type'][0] == '%')) {
            $type['type'] = substr($type['type'], 1);
        }

        if ($predicate == $type['type'] && !$auto) {
            $bare = true;
        }

        $concat = '';
        if (!$bare) {
            $concat .= \danog\PHP\Struct::pack('<i', $constructorData['id']);
        }

        return $concat.$this->serialize_params($constructorData, $object);
    }

    public function serialize_method($method, $arguments)
    {
        $tl = $this->methods->find_by_method($method);
        if ($tl === false) {
            throw new Exception('Could not extract type: '.$method);
        }

        return \danog\PHP\Struct::pack('<i', $tl['id']).$this->serialize_params($tl, $arguments);
    }

    public function serialize_params($tl, $arguments)
    {
        $serialized = '';
        $flags = 0;
        foreach ($tl['params'] as $cur_flag) {
            if ($cur_flag['flag']) {
                switch ($cur_flag['type']) {
                    case 'true':
                    case 'false':
                        $flags = (isset($arguments[$cur_flag['name']]) && $arguments[$cur_flag['name']]) ? ($flags | $cur_flag['pow']) : ($flags & ~$cur_flag['pow']);
                        unset($arguments[$cur_flag['name']]);
                        break;
                    case 'Bool':
                        $arguments[$cur_flag['name']] = (isset($arguments[$cur_flag['name']]) && $arguments[$cur_flag['name']]) && (($flags & $cur_flag['pow']) != 0);
                        if (($flags & $cur_flag['pow']) == 0) {
                            unset($arguments[$cur_flag['name']]);
                        }
                        break;
                    default:
                        $flags = (isset($arguments[$cur_flag['name']]) && $arguments[$cur_flag['name']] !== null) ? ($flags | $cur_flag['pow']) : ($flags & ~$cur_flag['pow']);
                        break;
                }
            }
        }
        $arguments['flags'] = $flags;
        foreach ($tl['params'] as $current_argument) {
            if (!isset($arguments[$current_argument['name']])) {
                if ($current_argument['flag'] && (in_array($current_argument['type'], ['true', 'false']) || ($flags & $current_argument['pow']) == 0)) {
                    //\danog\MadelineProto\Logger::log('Skipping '.$current_argument['name'].' of type '.$current_argument['type'].'/'.$current_argument['subtype']);
                    continue;
                }
                throw new Exception('Missing required parameter ('.$current_argument['name'].')');
            }
            //\danog\MadelineProto\Logger::log('Serializing '.$current_argument['name'].' of type '.$current_argument['type'].'/'.$current_argument['subtype']);
            $serialized .= $this->serialize_object($current_argument, $arguments[$current_argument['name']]);
        }

        return $serialized;
    }

    public function get_length($bytes_io, $type = ['type' => ''])
    {
        $this->deserialize($bytes_io, $type);

        return ftell($bytes_io);
    }

    /**
     * :type bytes_io: io.BytesIO object.
     */
    public function deserialize($bytes_io, $type = ['type' => ''])
    {
        if (!(!is_string($bytes_io) && (get_resource_type($bytes_io) == 'file' || get_resource_type($bytes_io) == 'stream'))) {
            if (is_string($bytes_io)) {
                $bytes_io = $this->fopen_and_write('php://memory', 'rw+b', $bytes_io);
            } else {
                throw new Exception('An invalid bytes_io handle was provided.');
            }
        }
        //\danog\MadelineProto\Logger::log('Deserializing '.$type['type'].'/'.$subtype.' at byte '.ftell($bytes_io));
        switch ($type['type']) {
            case 'Bool':
                return $this->deserialize_bool(fread($bytes_io, 4));
            case 'int':
                return \danog\PHP\Struct::unpack('<i', fread($bytes_io, 4))[0];
            case '#':
                return \danog\PHP\Struct::unpack('<I', fread($bytes_io, 4))[0];
            case 'long':
                return \danog\PHP\Struct::unpack('<q', fread($bytes_io, 8))[0];
            case 'double':
                return \danog\PHP\Struct::unpack('<d', fread($bytes_io, 8))[0];
            case 'int128':
                return fread($bytes_io, 16);
            case 'int256':
                return fread($bytes_io, 32);
            case 'int512':
                return fread($bytes_io, 32);
            case 'string':
            case 'bytes':
                $l = \danog\PHP\Struct::unpack('<B', fread($bytes_io, 1))[0];
                if ($l > 254) {
                    throw new Exception('Length is too big');
                }
                if ($l == 254) {
                    $long_len = \danog\PHP\Struct::unpack('<I', fread($bytes_io, 3).$this->string2bin('\x00'))[0];
                    $x = fread($bytes_io, $long_len);
                    $resto = $this->posmod(-$long_len, 4);
                    if ($resto > 0) {
                        fread($bytes_io, $resto);
                    }
                } else {
                    $x = fread($bytes_io, $l);
                    $resto = $this->posmod(-($l + 1), 4);
                    if ($resto > 0) {
                        fread($bytes_io, $resto);
                    }
                }
                if (!is_string($x)) {
                    throw new Exception("deserialize: generated value isn't a string");
                }

                return $x;
            case 'true':
                return true;
            case 'Vector t':
                $id = \danog\PHP\Struct::unpack('<i', fread($bytes_io, 4))[0];
                $constructorData = $this->constructors->find_by_id($id);
                if ($constructorData === false) {
                    throw new Exception('Could not extract type: '.$type['type'].' with id '.$id);
                }
                switch ($constructorData['predicate']) {
                    case 'gzip_packed':
                        return $this->deserialize(gzdecode($this->deserialize($bytes_io, ['type' => 'string'])));
                    case 'Vector t':
                    case 'vector':
                        break;
                    default:
                        throw new Exception('Invalid vector constructor: '.$constructorData['predicate']);
                }
            case 'vector':
                $count = \danog\PHP\Struct::unpack('<i', fread($bytes_io, 4))[0];
                $result = [];
                for ($i = 0; $i < $count; $i++) {
                    $result[] = $this->deserialize($bytes_io, ['type' => $type['subtype']]);
                }

                return $result;
        }
        if ($type['type'] != '' && $type['type'][0] == '%') {
            $checkType = substr($type['type'], 1);
            $constructorData = $this->constructors->find_by_type($checkType);
            if ($constructorData === false) {
                throw new Exception('Constructor not found for type: '.$checkType);
            }
        } else {
            $constructorData = $this->constructors->find_by_predicate($type['type']);
            if ($constructorData === false) {
                $id = \danog\PHP\Struct::unpack('<i', fread($bytes_io, 4))[0];
                $constructorData = $this->constructors->find_by_id($id);
                if ($constructorData === false) {
                    throw new Exception('Could not extract type: '.$type['type'].' with id '.$id);
                }
            }
        }
        if ($constructorData['predicate'] == 'gzip_packed') {
            return $this->deserialize(gzdecode($this->deserialize($bytes_io, ['type' => 'string'])));
        }
        $x = ['_' => $constructorData['predicate']];
        foreach ($constructorData['params'] as $arg) {
            if ($arg['flag']) {
                switch ($arg['type']) {
                    case 'true':
                    case 'false':
                        $x[$arg['name']] = ($x['flags'] & $arg['pow']) == 1;
                        continue 2;
                        break;
                    case 'Bool':
                        $default = false;
                    default:
                        $default = null;
                        if (($x['flags'] & $arg['pow']) == 0) {
                            $x[$arg['name']] = $default;
                            continue 2;
                        }
                        break;
                }
            }
            $x[$arg['name']] = $this->deserialize($bytes_io, $arg);
        }
        if (isset($x['flags'])) { // I don't think we need this anymore
            unset($x['flags']);
        }

        return $x;
    }

    public function content_related($method)
    {
        return !in_array(
            $method,
            [
                'rpc_result',
                'rpc_error',
                'rpc_drop_answer',
                'rpc_answer_unknown',
                'rpc_answer_dropped_running',
                'rpc_answer_dropped',
                'get_future_salts',
                'future_salt',
                'future_salts',
                'ping',
                'pong',
                'ping_delay_disconnect',
                'destroy_session',
                'destroy_session_ok',
                'destroy_session_none',
                'new_session_created',
                'msg_container',
                'msg_copy',
                'gzip_packed',
                'http_wait',
                'msgs_ack',
                'bad_msg_notification',
                'bad_server_salt',
                'msgs_state_req',
                'msgs_state_info',
                'msgs_all_info',
                'msg_detailed_info',
                'msg_new_detailed_info',
                'msg_resend_req',
                'msg_resend_ans_req',
            ]
        );
    }
}
