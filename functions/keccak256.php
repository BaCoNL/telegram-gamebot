<?php
/**
 * Keccak-256 Implementation for TRON Address Derivation
 *
 * PHP's built-in sha3-256 uses FIPS-202 SHA3, but TRON uses Keccak-256 (pre-FIPS)
 * This is a pure PHP implementation of Keccak-256
 */

/**
 * Keccak-256 hash function
 *
 * @param string $data Binary data to hash
 * @return string 32-byte hash (binary)
 */
function keccak256($data) {
    // Keccak-256 parameters
    $rate = 1088; // bits
    $capacity = 512; // bits
    $outputLength = 256; // bits
    $delimitedSuffix = 0x01; // Keccak uses 0x01, SHA3 uses 0x06

    return keccakHash($data, $rate, $capacity, $outputLength, $delimitedSuffix);
}

/**
 * Keccak sponge function
 */
function keccakHash($data, $rate, $capacity, $outputLength, $delimitedSuffix) {
    // State is 1600 bits = 200 bytes = 25 uint64s
    $state = array_fill(0, 25, gmp_init(0));

    $rateInBytes = $rate / 8;
    $blockSize = 0;

    // Absorbing phase
    $dataLen = strlen($data);
    $offset = 0;

    while ($dataLen >= $rateInBytes) {
        for ($i = 0; $i < $rateInBytes / 8; $i++) {
            $bytes = substr($data, $offset + $i * 8, 8);
            if (strlen($bytes) < 8) {
                $bytes = str_pad($bytes, 8, "\0");
            }
            $val = unpack('P', $bytes)[1]; // Little-endian uint64
            $state[$i] = gmp_xor($state[$i], gmp_init($val));
        }
        $state = keccakF1600($state);
        $offset += $rateInBytes;
        $dataLen -= $rateInBytes;
    }

    // Padding
    $padded = substr($data, $offset);
    $padded .= chr($delimitedSuffix);

    while (strlen($padded) < $rateInBytes) {
        $padded .= "\0";
    }

    $padded[strlen($padded) - 1] = chr(ord($padded[strlen($padded) - 1]) | 0x80);

    // Final block
    for ($i = 0; $i < $rateInBytes / 8; $i++) {
        $bytes = substr($padded, $i * 8, 8);
        if (strlen($bytes) < 8) {
            $bytes = str_pad($bytes, 8, "\0");
        }
        $val = unpack('P', $bytes)[1];
        $state[$i] = gmp_xor($state[$i], gmp_init($val));
    }
    $state = keccakF1600($state);

    // Squeezing phase
    $output = '';
    $outputBytes = $outputLength / 8;

    while (strlen($output) < $outputBytes) {
        for ($i = 0; $i < $rateInBytes / 8 && strlen($output) < $outputBytes; $i++) {
            $output .= pack('P', gmp_intval($state[$i]));
        }
        if (strlen($output) < $outputBytes) {
            $state = keccakF1600($state);
        }
    }

    return substr($output, 0, $outputBytes);
}

/**
 * Keccak-f[1600] permutation
 */
function keccakF1600($state) {
    $rounds = 24;

    // Round constants
    $RC = [
        gmp_init('0x0000000000000001'), gmp_init('0x0000000000008082'),
        gmp_init('0x800000000000808a'), gmp_init('0x8000000080008000'),
        gmp_init('0x000000000000808b'), gmp_init('0x0000000080000001'),
        gmp_init('0x8000000080008081'), gmp_init('0x8000000000008009'),
        gmp_init('0x000000000000008a'), gmp_init('0x0000000000000088'),
        gmp_init('0x0000000080008009'), gmp_init('0x000000008000000a'),
        gmp_init('0x000000008000808b'), gmp_init('0x800000000000008b'),
        gmp_init('0x8000000000008089'), gmp_init('0x8000000000008003'),
        gmp_init('0x8000000000008002'), gmp_init('0x8000000000000080'),
        gmp_init('0x000000000000800a'), gmp_init('0x800000008000000a'),
        gmp_init('0x8000000080008081'), gmp_init('0x8000000000008080'),
        gmp_init('0x0000000080000001'), gmp_init('0x8000000080008008')
    ];

    for ($round = 0; $round < $rounds; $round++) {
        // θ (theta) step
        $C = [];
        for ($x = 0; $x < 5; $x++) {
            $C[$x] = gmp_xor(
                gmp_xor(gmp_xor(gmp_xor($state[$x], $state[$x + 5]), $state[$x + 10]), $state[$x + 15]),
                $state[$x + 20]
            );
        }

        $D = [];
        for ($x = 0; $x < 5; $x++) {
            $D[$x] = gmp_xor($C[($x + 4) % 5], rotl64($C[($x + 1) % 5], 1));
        }

        for ($x = 0; $x < 5; $x++) {
            for ($y = 0; $y < 5; $y++) {
                $state[$x + $y * 5] = gmp_xor($state[$x + $y * 5], $D[$x]);
            }
        }

        // ρ (rho) and π (pi) steps
        $rotations = [
            0, 1, 62, 28, 27,
            36, 44, 6, 55, 20,
            3, 10, 43, 25, 39,
            41, 45, 15, 21, 8,
            18, 2, 61, 56, 14
        ];

        $B = [];
        for ($x = 0; $x < 5; $x++) {
            for ($y = 0; $y < 5; $y++) {
                $B[$y + ((2 * $x + 3 * $y) % 5) * 5] = rotl64($state[$x + $y * 5], $rotations[$x + $y * 5]);
            }
        }

        // χ (chi) step
        for ($x = 0; $x < 5; $x++) {
            for ($y = 0; $y < 5; $y++) {
                $state[$x + $y * 5] = gmp_xor(
                    $B[$x + $y * 5],
                    gmp_and(
                        gmp_xor($B[($x + 1) % 5 + $y * 5], gmp_init('0xFFFFFFFFFFFFFFFF')),
                        $B[($x + 2) % 5 + $y * 5]
                    )
                );
            }
        }

        // ι (iota) step
        $state[0] = gmp_xor($state[0], $RC[$round]);
    }

    return $state;
}

/**
 * Rotate left for 64-bit value
 */
function rotl64($val, $shift) {
    $shift = $shift % 64;
    if ($shift == 0) return $val;

    // Ensure value is in 64-bit range
    $val = gmp_and($val, gmp_init('0xFFFFFFFFFFFFFFFF'));

    $left = gmp_and(
        gmp_mul($val, gmp_pow(2, $shift)),
        gmp_init('0xFFFFFFFFFFFFFFFF')
    );

    $right = gmp_div($val, gmp_pow(2, 64 - $shift));

    return gmp_and(
        gmp_or($left, $right),
        gmp_init('0xFFFFFFFFFFFFFFFF')
    );
}

