<?php
declare(strict_types=1);

final class SecretVault
{
    public static function encrypt(string $plain, string $encodedKey): string
    {
        $key=base64_decode($encodedKey,true);if(!is_string($key)||strlen($key)!==32)throw new RuntimeException('Falta configurar la clave privada de cifrado del panel.');
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('No se pudo cifrar el secreto.');return 'v1.'.base64_encode($iv.$tag.$cipher);
    }
    public static function decrypt(string $stored, string $encodedKey): string
    {
        if(!str_starts_with($stored,'v1.'))throw new RuntimeException('Formato de secreto no compatible.');$key=base64_decode($encodedKey,true);$raw=base64_decode(substr($stored,3),true);if(!is_string($key)||strlen($key)!==32||!is_string($raw)||strlen($raw)<29)throw new RuntimeException('No se pudo abrir el secreto TOTP.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if(!is_string($plain))throw new RuntimeException('No se pudo abrir el secreto TOTP.');return $plain;
    }
}
