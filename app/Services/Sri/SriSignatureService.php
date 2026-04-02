<?php

namespace App\Services\Sri;

class SriSignatureService
{
    /**
     * Sign XML with XAdES-BES using .p12 certificate.
     *
     * NOTE: Full XAdES-BES signing requires the tenant's .p12 certificate
     * which is configured in Settings (Session 10). For now, this returns
     * the unsigned XML as-is for testing in the SRI test environment.
     */
    public function sign(string $xml, ?string $p12Content = null, ?string $p12Password = null): string
    {
        if (! $p12Content || ! $p12Password) {
            // No certificate configured - return unsigned for test environment
            return $xml;
        }

        $result = SriCertificateReader::read($p12Content, $p12Password);
        if ($result['error']) {
            throw new \RuntimeException('Error .p12: ' . $result['error']);
        }
        $certs = $result['certs'];

        // Full XAdES-BES signing implementation will be completed
        // when certificate management is configured in Session 10.
        // For test environment, the unsigned XML is accepted.
        return $xml;
    }
}
