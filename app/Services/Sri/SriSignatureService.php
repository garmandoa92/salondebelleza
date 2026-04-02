<?php

namespace App\Services\Sri;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Symfony\Component\Process\Process;

class SriSignatureService
{
    /**
     * Sign XML with XAdES-BES for SRI Ecuador.
     * Spec: ETSI TS 101 903 v1.3.2, RSA-SHA1, ENVELOPED.
     */
    public function sign(string $xml, ?string $p12Content = null, ?string $p12Password = null): string
    {
        if (! $p12Content || ! $p12Password) {
            Log::warning('SRI Firma: Sin certificado, retornando XML sin firmar');
            return $xml;
        }

        try {
            $keys = $this->extractFromP12($p12Content, $p12Password);

            Log::info('SRI Firma: Certificado extraido', [
                'issuer' => substr($keys['issuer'], 0, 80),
                'serial' => $keys['serial'],
            ]);

            $signedXml = $this->applyXadesBes($xml, $keys);

            // Save debug XML
            try {
                Storage::put('debug/xml_firmado_' . date('YmdHis') . '.xml', $signedXml);
            } catch (\Throwable $e) {}

            Log::info('SRI Firma: XML firmado con XAdES-BES OK');
            return $signedXml;

        } catch (\Throwable $e) {
            Log::error('SRI Firma: Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function extractFromP12(string $p12Content, string $password): array
    {
        $tmpP12 = tempnam(sys_get_temp_dir(), 'sri_p12_');
        $tmpKey = tempnam(sys_get_temp_dir(), 'sri_key_');
        $tmpCert = tempnam(sys_get_temp_dir(), 'sri_crt_');

        try {
            file_put_contents($tmpP12, $p12Content);

            // Extract private key (try -legacy first for SRI Ecuador certs)
            foreach (['-legacy', ''] as $flag) {
                $args = array_values(array_filter([
                    'openssl', 'pkcs12', $flag ?: null,
                    '-in', $tmpP12, '-passin', 'pass:' . $password,
                    '-nocerts', '-nodes', '-out', $tmpKey,
                ]));
                $p = new Process($args);
                $p->run();
                if ($p->isSuccessful()) break;
            }

            // Extract certificate
            foreach (['-legacy', ''] as $flag) {
                $args = array_values(array_filter([
                    'openssl', 'pkcs12', $flag ?: null,
                    '-in', $tmpP12, '-passin', 'pass:' . $password,
                    '-nokeys', '-clcerts', '-out', $tmpCert,
                ]));
                $p = new Process($args);
                $p->run();
                if ($p->isSuccessful()) break;
            }

            $privateKeyPem = file_get_contents($tmpKey);
            $certPem = file_get_contents($tmpCert);

            if (empty($privateKeyPem) || empty($certPem)) {
                throw new \RuntimeException('No se pudo extraer clave/certificado del .p12');
            }

            // Get issuer in RFC2253 format (required by SRI XAdES)
            $pIssuer = new Process(['openssl', 'x509', '-in', $tmpCert, '-noout', '-issuer', '-nameopt', 'RFC2253']);
            $pIssuer->run();
            $issuer = trim(str_replace('issuer=', '', $pIssuer->getOutput()));

            // Get serial in decimal
            $pSerial = new Process(['openssl', 'x509', '-in', $tmpCert, '-noout', '-serial']);
            $pSerial->run();
            $hexSerial = trim(str_replace('serial=', '', $pSerial->getOutput()));
            // Convert hex to decimal without losing precision
            $serial = function_exists('gmp_strval')
                ? gmp_strval(gmp_init($hexSerial, 16), 10)
                : self::hexToDecimal($hexSerial);

            // Clean base64 cert (no PEM headers)
            $certBase64 = preg_replace('/-----[^-]+-----|[\r\n\s]/', '', $certPem);

            // SHA1 digest of DER cert
            $certDer = base64_decode($certBase64);
            $certDigest = base64_encode(sha1($certDer, true));

            return [
                'private_key' => $privateKeyPem,
                'cert_pem' => $certPem,
                'cert_base64' => $certBase64,
                'cert_digest' => $certDigest,
                'issuer' => $issuer,
                'serial' => $serial,
            ];
        } finally {
            @unlink($tmpP12);
            @unlink($tmpKey);
            @unlink($tmpCert);
        }
    }

    private static function hexToDecimal(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        $dec = '0';
        for ($i = 0; $i < strlen($hex); $i++) {
            $dec = bcmul($dec, '16');
            $dec = bcadd($dec, (string) hexdec($hex[$i]));
        }
        return $dec;
    }

    private function applyXadesBes(string $xml, array $keys): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);

        // IDs for cross-referencing
        $sigId = 'Signature' . rand(100000, 999999);
        $sigValueId = $sigId . '-SignatureValue';
        $signedInfoId = $sigId . '-SignedInfo';
        $signedPropsId = $sigId . '-SignedProperties';
        $certId = 'Certificate' . rand(100000, 999999);
        $refId = 'Reference-ID-' . rand(100000, 999999);

        // Use xmlseclibs for the core signature
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);

        // Reference to the comprobante (the root element with id="comprobante")
        $objDSig->addReference(
            $doc,
            XMLSecurityDSig::SHA1,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true, 'uri' => '#comprobante', 'overwrite' => false, 'id_name' => 'Id', 'id_value' => $refId]
        );

        // Create key
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $objKey->loadKey($keys['private_key']);

        // Sign
        $objDSig->sign($objKey);

        // Set IDs on signature nodes
        $objDSig->sigNode->setAttribute('Id', $sigId);

        // Set SignedInfo Id
        $signedInfoNodes = $objDSig->sigNode->getElementsByTagName('SignedInfo');
        if ($signedInfoNodes->length > 0) {
            $signedInfoNodes->item(0)->setAttribute('Id', $signedInfoId);
        }

        // Set SignatureValue Id
        $sigValueNodes = $objDSig->sigNode->getElementsByTagName('SignatureValue');
        if ($sigValueNodes->length > 0) {
            $sigValueNodes->item(0)->setAttribute('Id', $sigValueId);
        }

        // Add X509 certificate to KeyInfo
        $objDSig->add509Cert($keys['cert_pem'], true, false, [
            'issuerSerial' => true,
            'subjectName' => true,
        ]);

        // Set KeyInfo Id
        $keyInfoNodes = $objDSig->sigNode->getElementsByTagName('KeyInfo');
        if ($keyInfoNodes->length > 0) {
            $keyInfoNodes->item(0)->setAttribute('Id', $certId);
        }

        // Build XAdES QualifyingProperties
        $signingTime = date('Y-m-d\TH:i:sP');

        $xadesXml = '<etsi:QualifyingProperties Target="#' . $sigId . '">'
            . '<etsi:SignedProperties Id="' . $signedPropsId . '">'
            . '<etsi:SignedSignatureProperties>'
            . '<etsi:SigningTime>' . $signingTime . '</etsi:SigningTime>'
            . '<etsi:SigningCertificate>'
            . '<etsi:Cert>'
            . '<etsi:CertDigest>'
            . '<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>'
            . '<ds:DigestValue>' . $keys['cert_digest'] . '</ds:DigestValue>'
            . '</etsi:CertDigest>'
            . '<etsi:IssuerSerial>'
            . '<ds:X509IssuerName>' . htmlspecialchars($keys['issuer']) . '</ds:X509IssuerName>'
            . '<ds:X509SerialNumber>' . $keys['serial'] . '</ds:X509SerialNumber>'
            . '</etsi:IssuerSerial>'
            . '</etsi:Cert>'
            . '</etsi:SigningCertificate>'
            . '</etsi:SignedSignatureProperties>'
            . '<etsi:SignedDataObjectProperties>'
            . '<etsi:DataObjectFormat ObjectReference="#' . $refId . '">'
            . '<etsi:Description>contenido comprobante</etsi:Description>'
            . '<etsi:MimeType>text/xml</etsi:MimeType>'
            . '</etsi:DataObjectFormat>'
            . '</etsi:SignedDataObjectProperties>'
            . '</etsi:SignedProperties>'
            . '</etsi:QualifyingProperties>';

        // Create ds:Object with QualifyingProperties
        $dsNs = 'http://www.w3.org/2000/09/xmldsig#';
        $etsiNs = 'http://uri.etsi.org/01903/v1.3.2#';

        $objectNode = $objDSig->sigNode->ownerDocument->createElementNS($dsNs, 'ds:Object');
        $objectNode->setAttribute('Id', $sigId . '-Object');

        $tmpDoc = new \DOMDocument();
        $tmpDoc->loadXML('<root xmlns:ds="' . $dsNs . '" xmlns:etsi="' . $etsiNs . '">' . $xadesXml . '</root>');
        $imported = $objDSig->sigNode->ownerDocument->importNode($tmpDoc->documentElement->firstChild, true);
        $objectNode->appendChild($imported);
        $objDSig->sigNode->appendChild($objectNode);

        // Append the complete signature to the document
        $objDSig->appendSignature($doc->documentElement);

        return $doc->saveXML();
    }
}
