<?php

namespace App\Services\Sri;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SriSignatureService
{
    /**
     * Sign XML with XAdES-BES using .p12 certificate.
     * Uses openssl CLI with -legacy flag for SRI Ecuador certificates.
     */
    public function sign(string $xml, ?string $p12Content = null, ?string $p12Password = null): string
    {
        if (! $p12Content || ! $p12Password) {
            Log::warning('SRI Firma: Sin certificado configurado, retornando XML sin firmar');
            return $xml;
        }

        $tmpP12 = tempnam(sys_get_temp_dir(), 'sri_p12_');
        $tmpKey = tempnam(sys_get_temp_dir(), 'sri_key_');
        $tmpCert = tempnam(sys_get_temp_dir(), 'sri_cert_');
        $tmpXml = tempnam(sys_get_temp_dir(), 'sri_xml_');
        $tmpSigned = tempnam(sys_get_temp_dir(), 'sri_signed_');

        try {
            file_put_contents($tmpP12, $p12Content);
            file_put_contents($tmpXml, $xml);

            // Extract private key with -legacy
            $keyProcess = new Process([
                'openssl', 'pkcs12', '-legacy',
                '-in', $tmpP12, '-passin', 'pass:' . $p12Password,
                '-nocerts', '-nodes', '-out', $tmpKey,
            ]);
            $keyProcess->run();

            if (! $keyProcess->isSuccessful()) {
                // Retry without -legacy
                $keyProcess2 = new Process([
                    'openssl', 'pkcs12',
                    '-in', $tmpP12, '-passin', 'pass:' . $p12Password,
                    '-nocerts', '-nodes', '-out', $tmpKey,
                ]);
                $keyProcess2->run();
                if (! $keyProcess2->isSuccessful()) {
                    throw new \RuntimeException('Error extrayendo clave privada: ' . $keyProcess2->getErrorOutput());
                }
            }

            // Extract certificate with -legacy
            $certProcess = new Process([
                'openssl', 'pkcs12', '-legacy',
                '-in', $tmpP12, '-passin', 'pass:' . $p12Password,
                '-nokeys', '-clcerts', '-out', $tmpCert,
            ]);
            $certProcess->run();

            if (! $certProcess->isSuccessful()) {
                $certProcess2 = new Process([
                    'openssl', 'pkcs12',
                    '-in', $tmpP12, '-passin', 'pass:' . $p12Password,
                    '-nokeys', '-clcerts', '-out', $tmpCert,
                ]);
                $certProcess2->run();
            }

            $privateKey = file_get_contents($tmpKey);
            $certificate = file_get_contents($tmpCert);

            if (empty($privateKey) || empty($certificate)) {
                throw new \RuntimeException('Certificado o llave vacios al firmar');
            }

            // Sign XML with xmlsec1 if available, otherwise use PHP native
            $signedXml = $this->signWithXmlsec($tmpXml, $tmpKey, $tmpCert, $tmpSigned);
            if ($signedXml) {
                Log::info('SRI Firma: XML firmado con xmlsec1');
                return $signedXml;
            }

            // Fallback: PHP native enveloped signature
            $signedXml = $this->signWithPhp($xml, $privateKey, $certificate);
            Log::info('SRI Firma: XML firmado con PHP nativo');
            return $signedXml;

        } catch (\Throwable $e) {
            Log::error('SRI Firma: Error al firmar', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            @unlink($tmpP12);
            @unlink($tmpKey);
            @unlink($tmpCert);
            @unlink($tmpXml);
            @unlink($tmpSigned);
        }
    }

    private function signWithXmlsec(string $xmlPath, string $keyPath, string $certPath, string $outputPath): ?string
    {
        // Check if xmlsec1 is available
        $check = new Process(['which', 'xmlsec1']);
        $check->run();
        if (! $check->isSuccessful()) {
            return null;
        }

        $process = new Process([
            'xmlsec1', '--sign',
            '--privkey-pem', $keyPath,
            '--pubkey-cert-pem', $certPath,
            '--output', $outputPath,
            $xmlPath,
        ]);
        $process->run();

        if ($process->isSuccessful() && file_exists($outputPath)) {
            return file_get_contents($outputPath);
        }

        return null;
    }

    private function signWithPhp(string $xml, string $privateKey, string $certificate): string
    {
        // Load the XML
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);

        // Get the certificate data (clean PEM)
        $certClean = preg_replace('/-----[^-]+-----/', '', $certificate);
        $certClean = preg_replace('/\s+/', '', $certClean);

        // Canonicalize the XML for signing
        $canonicalXml = $doc->C14N();

        // Create SHA1 digest of the document
        $digest = base64_encode(hash('sha1', $canonicalXml, true));

        // Build SignedInfo
        $signedInfo = '<ds:SignedInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">'
            . '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>'
            . '<ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>'
            . '<ds:Reference URI="">'
            . '<ds:Transforms>'
            . '<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>'
            . '</ds:Transforms>'
            . '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>'
            . '<ds:DigestValue>' . $digest . '</ds:DigestValue>'
            . '</ds:Reference>'
            . '</ds:SignedInfo>';

        // Sign the SignedInfo
        $pkey = openssl_pkey_get_private($privateKey);
        if (! $pkey) {
            throw new \RuntimeException('No se pudo cargar la llave privada para firmar');
        }

        $signedInfoDoc = new \DOMDocument();
        $signedInfoDoc->loadXML($signedInfo);
        $canonicalSignedInfo = $signedInfoDoc->C14N();

        openssl_sign($canonicalSignedInfo, $signature, $pkey, OPENSSL_ALGO_SHA1);
        $signatureBase64 = base64_encode($signature);

        // Build complete Signature element
        $signatureXml = '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="Signature">'
            . $signedInfo
            . '<ds:SignatureValue>' . $signatureBase64 . '</ds:SignatureValue>'
            . '<ds:KeyInfo>'
            . '<ds:X509Data>'
            . '<ds:X509Certificate>' . $certClean . '</ds:X509Certificate>'
            . '</ds:X509Data>'
            . '</ds:KeyInfo>'
            . '</ds:Signature>';

        // Insert signature before closing tag of root element
        $root = $doc->documentElement;
        $sigFragment = $doc->createDocumentFragment();
        $sigFragment->appendXML($signatureXml);
        $root->appendChild($sigFragment);

        return $doc->saveXML();
    }
}
