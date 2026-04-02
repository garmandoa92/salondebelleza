<?php

namespace App\Services\Sri;

use Illuminate\Support\Facades\Log;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Symfony\Component\Process\Process;

class SriSignatureService
{
    /**
     * Sign XML with XAdES-BES for SRI Ecuador.
     * Uses robrichards/xmlseclibs + openssl CLI for legacy .p12 extraction.
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

        try {
            file_put_contents($tmpP12, $p12Content);

            // Extract private key with -legacy for SRI Ecuador certificates
            $this->extractWithOpenssl($tmpP12, $p12Password, $tmpKey, $tmpCert);

            $privateKeyPem = file_get_contents($tmpKey);
            $certPem = file_get_contents($tmpCert);

            if (empty($privateKeyPem) || empty($certPem)) {
                throw new \RuntimeException('Certificado o llave vacios al firmar');
            }

            // Sign with XAdES-BES using xmlseclibs
            $signedXml = $this->signXadesBes($xml, $privateKeyPem, $certPem);

            Log::info('SRI Firma: XML firmado con XAdES-BES');
            return $signedXml;

        } catch (\Throwable $e) {
            Log::error('SRI Firma: Error al firmar', ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            @unlink($tmpP12);
            @unlink($tmpKey);
            @unlink($tmpCert);
        }
    }

    private function extractWithOpenssl(string $p12Path, string $password, string $keyPath, string $certPath): void
    {
        // Extract private key
        $keyProc = new Process(['openssl', 'pkcs12', '-legacy', '-in', $p12Path, '-passin', 'pass:' . $password, '-nocerts', '-nodes', '-out', $keyPath]);
        $keyProc->run();
        if (! $keyProc->isSuccessful()) {
            // Retry without -legacy
            $keyProc2 = new Process(['openssl', 'pkcs12', '-in', $p12Path, '-passin', 'pass:' . $password, '-nocerts', '-nodes', '-out', $keyPath]);
            $keyProc2->run();
            if (! $keyProc2->isSuccessful()) {
                throw new \RuntimeException('Error extrayendo clave: ' . $keyProc2->getErrorOutput());
            }
        }

        // Extract certificate
        $certProc = new Process(['openssl', 'pkcs12', '-legacy', '-in', $p12Path, '-passin', 'pass:' . $password, '-nokeys', '-clcerts', '-out', $certPath]);
        $certProc->run();
        if (! $certProc->isSuccessful()) {
            $certProc2 = new Process(['openssl', 'pkcs12', '-in', $p12Path, '-passin', 'pass:' . $password, '-nokeys', '-clcerts', '-out', $certPath]);
            $certProc2->run();
        }
    }

    private function signXadesBes(string $xml, string $privateKeyPem, string $certPem): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);

        // Create XMLSecurityDSig
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

        // Add enveloped-signature reference to the root element
        $rootId = $doc->documentElement->getAttribute('id');
        $refUri = $rootId ? '#' . $rootId : '';

        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA1,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true, 'uri' => $refUri]
        );

        // Create the key
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $objKey->loadKey($privateKeyPem);

        // Sign the XML
        $objDSig->sign($objKey);

        // Add certificate to KeyInfo
        $certClean = preg_replace('/-----[^-]+-----/', '', $certPem);
        $certClean = preg_replace('/\s+/', '', $certClean);

        $objDSig->add509Cert($certClean, true, false, ['issuerSerial' => true, 'subjectName' => true]);

        // Append signature to root element FIRST
        $objDSig->appendSignature($doc->documentElement);

        // Then add XAdES-BES SignedProperties inside the signature (now in $doc)
        $this->addXadesProperties($doc, $certPem, $certClean);

        return $doc->saveXML();
    }

    private function addXadesProperties(\DOMDocument $doc, string $certPem, string $certClean): void
    {
        // Find the Signature node already appended to $doc
        $signatureNodes = $doc->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
        if ($signatureNodes->length === 0) return;
        $signatureNode = $signatureNodes->item(0);

        $signatureId = 'Signature' . rand(100000, 999999);
        $signatureNode->setAttribute('Id', $signatureId);

        // Parse certificate for issuer and serial
        $certData = openssl_x509_parse($certPem);
        $issuerDN = '';
        if (isset($certData['issuer'])) {
            $parts = [];
            foreach ($certData['issuer'] as $k => $v) {
                $parts[] = "$k=$v";
            }
            $issuerDN = implode(',', $parts);
        }
        $serialNumber = $certData['serialNumber'] ?? '';
        $certDigest = base64_encode(hash('sha1', base64_decode($certClean), true));

        $signingTime = date('Y-m-d\TH:i:sP');

        $etsiNs = 'http://uri.etsi.org/01903/v1.3.2#';

        // Build QualifyingProperties
        $qpXml = '<etsi:QualifyingProperties xmlns:etsi="' . $etsiNs . '" Target="#' . $signatureId . '">'
            . '<etsi:SignedProperties Id="' . $signatureId . '-SignedProperties">'
            . '<etsi:SignedSignatureProperties>'
            . '<etsi:SigningTime>' . $signingTime . '</etsi:SigningTime>'
            . '<etsi:SigningCertificate>'
            . '<etsi:Cert>'
            . '<etsi:CertDigest>'
            . '<ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></ds:DigestMethod>'
            . '<ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . $certDigest . '</ds:DigestValue>'
            . '</etsi:CertDigest>'
            . '<etsi:IssuerSerial>'
            . '<ds:X509IssuerName xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . htmlspecialchars($issuerDN) . '</ds:X509IssuerName>'
            . '<ds:X509SerialNumber xmlns:ds="http://www.w3.org/2000/09/xmldsig#">' . $serialNumber . '</ds:X509SerialNumber>'
            . '</etsi:IssuerSerial>'
            . '</etsi:Cert>'
            . '</etsi:SigningCertificate>'
            . '</etsi:SignedSignatureProperties>'
            . '<etsi:SignedDataObjectProperties>'
            . '<etsi:DataObjectFormat ObjectReference="#Reference-ID-' . rand(100000, 999999) . '">'
            . '<etsi:Description>contenido comprobante</etsi:Description>'
            . '<etsi:MimeType>text/xml</etsi:MimeType>'
            . '</etsi:DataObjectFormat>'
            . '</etsi:SignedDataObjectProperties>'
            . '</etsi:SignedProperties>'
            . '</etsi:QualifyingProperties>';

        // Append as ds:Object inside ds:Signature
        $objectNode = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Object');
        $fragment = $doc->createDocumentFragment();
        $fragment->appendXML($qpXml);
        $objectNode->appendChild($fragment);
        $signatureNode->appendChild($objectNode);
    }
}
