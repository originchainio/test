<?php
//Fork from arionum https://github.com/arionum/node
//add checkalias
final class Blacklist{
    // The official list of blacklisted public keys
    public const PUBLIC_KEYS = [
        //'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCvVQcHHC123123123123219cctEFvcsUdgrkGqy18taz9ZMrAGtq7NhBYpQ4ZTHkKYiZDaSUqQ' => 'aaa Abuser',
        //'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCxYDeQHk7Ke66UB2Un3UMmMoJ123123123123rER2ZLX1mgND7sLFXKETGTjRYjoHcuRNiJN1g' => 'bbb Exchange',
    ];

    // The official list of blacklisted addresses
    public const ADDRESSES = [
        //'xu123123q2gh3shuhwBT5nJHez9AynCaxpJwL6dpkavmZBA3JkrMkg' => 'XXX Exchange',
    ];
    // The official list of ALIAS
    public const ALIAS = [
        //'abc' => 'test abc',
    ];

    public static function checkPublicKey(string $publicKey): bool
    {
        return key_exists($publicKey, static::PUBLIC_KEYS);
    }
    public static function checkAddress(string $address): bool
    {
        return key_exists($address, static::ADDRESSES);
    }
    public static function checkalias(string $alias): bool
    {
        return key_exists($alias, static::ALIAS);
    }
}
