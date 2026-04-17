<?php
/**
 * Minimal CBOR decoder for WebAuthn
 * Handles the subset of CBOR used in WebAuthn attestation/assertion objects.
 */

function cbor_decode(string $data): mixed {
    $pos = 0;
    return _cbor_item($data, $pos);
}

function _cbor_item(string $data, int &$pos): mixed {
    if ($pos >= strlen($data)) {
        throw new \RuntimeException('Unexpected end of CBOR data');
    }

    $byte = ord($data[$pos++]);
    $major = ($byte >> 5) & 0x07;
    $info  = $byte & 0x1F;

    // Determine the integer value
    $val = _cbor_read_uint($data, $pos, $info);

    switch ($major) {
        case 0: // Unsigned integer
            return $val;

        case 1: // Negative integer
            return -1 - $val;

        case 2: // Byte string
            $result = substr($data, $pos, $val);
            $pos += $val;
            return $result;

        case 3: // Text string
            $result = substr($data, $pos, $val);
            $pos += $val;
            return $result;

        case 4: // Array
            $arr = [];
            for ($i = 0; $i < $val; $i++) {
                $arr[] = _cbor_item($data, $pos);
            }
            return $arr;

        case 5: // Map
            $map = [];
            for ($i = 0; $i < $val; $i++) {
                $k = _cbor_item($data, $pos);
                $v = _cbor_item($data, $pos);
                $map[$k] = $v;
            }
            return $map;

        case 7: // Simple / float
            if ($info === 20) return false;
            if ($info === 21) return true;
            if ($info === 22) return null;
            throw new \RuntimeException("Unsupported CBOR simple value: $info");

        default:
            throw new \RuntimeException("Unsupported CBOR major type: $major");
    }
}

function _cbor_read_uint(string $data, int &$pos, int $info): int {
    if ($info < 24) return $info;
    if ($info === 24) return ord($data[$pos++]);
    if ($info === 25) {
        $v = unpack('n', substr($data, $pos, 2))[1];
        $pos += 2;
        return $v;
    }
    if ($info === 26) {
        $v = unpack('N', substr($data, $pos, 4))[1];
        $pos += 4;
        return $v;
    }
    if ($info === 27) {
        $hi = unpack('N', substr($data, $pos,     4))[1];
        $lo = unpack('N', substr($data, $pos + 4, 4))[1];
        $pos += 8;
        return $hi * 4294967296 + $lo;
    }
    throw new \RuntimeException("Unsupported CBOR additional info: $info");
}
