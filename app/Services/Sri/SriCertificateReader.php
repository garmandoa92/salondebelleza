<?php

namespace App\Services\Sri;

class SriCertificateReader
{
    /**
     * Read a .p12 certificate with legacy algorithm support.
     * Ecuador SRI certificates use RC2-40-CBC which requires the legacy OpenSSL provider.
     */
    public static function read(string $content, string $password): array|false
    {
        $certs = [];

        // Try standard read first
        if (openssl_pkcs12_read($content, $certs, $password)) {
            return $certs;
        }

        // Enable legacy provider and retry
        $originalConf = getenv('OPENSSL_CONF');
        $legacyConf = config_path('openssl_legacy.cnf');

        if (file_exists($legacyConf)) {
            putenv('OPENSSL_CONF=' . $legacyConf);

            // Reset OpenSSL error queue
            while (openssl_error_string() !== false) {}

            $result = openssl_pkcs12_read($content, $certs, $password);

            // Restore original config
            if ($originalConf !== false) {
                putenv('OPENSSL_CONF=' . $originalConf);
            } else {
                putenv('OPENSSL_CONF');
            }

            return $result ? $certs : false;
        }

        return false;
    }
}
