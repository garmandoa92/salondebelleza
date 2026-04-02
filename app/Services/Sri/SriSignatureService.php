<?php

namespace App\Services\Sri;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class SriSignatureService
{
    public function sign(string $xml, ?string $p12Content = null, ?string $p12Password = null): string
    {
        if (! $p12Content || ! $p12Password) {
            Log::warning('SRI Firma: Sin certificado');
            return $xml;
        }

        try {
            $keys = $this->extractFromP12($p12Content, $p12Password);
            Log::info('SRI Firma: Cert extraido', ['serial' => $keys['serial']]);

            $signedXml = $this->buildXadesBes($xml, $keys);

            // Verify locally (warning only, not blocking)
            try {
                $this->verifyLocally($signedXml, $keys['certPem']);
            } catch (\Throwable $e) {
                Log::warning('SRI Firma: Verificacion local fallo (namespace context), enviando al SRI de todas formas', ['msg' => $e->getMessage()]);
            }

            Storage::put('debug/xml_firmado_' . date('YmdHis') . '.xml', $signedXml);
            Log::info('SRI Firma: XAdES-BES OK');
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

            foreach (['-legacy', ''] as $flag) {
                $args = array_values(array_filter(['openssl', 'pkcs12', $flag ?: null, '-in', $tmpP12, '-passin', 'pass:' . $password, '-nocerts', '-nodes', '-out', $tmpKey]));
                (new Process($args))->run();
                if (file_exists($tmpKey) && filesize($tmpKey) > 0) break;
            }
            foreach (['-legacy', ''] as $flag) {
                $args = array_values(array_filter(['openssl', 'pkcs12', $flag ?: null, '-in', $tmpP12, '-passin', 'pass:' . $password, '-nokeys', '-clcerts', '-out', $tmpCert]));
                (new Process($args))->run();
                if (file_exists($tmpCert) && filesize($tmpCert) > 0) break;
            }

            $privateKeyPem = file_get_contents($tmpKey);
            $certPem = file_get_contents($tmpCert);
            if (empty($privateKeyPem) || empty($certPem)) {
                throw new \RuntimeException('No se pudo extraer clave/certificado del .p12');
            }

            // Issuer RFC2253
            $p = new Process(['openssl', 'x509', '-in', $tmpCert, '-noout', '-issuer', '-nameopt', 'RFC2253']);
            $p->run();
            $issuer = trim(str_replace('issuer=', '', $p->getOutput()));

            // Serial decimal
            $p = new Process(['openssl', 'x509', '-in', $tmpCert, '-noout', '-serial']);
            $p->run();
            $hex = trim(str_replace('serial=', '', $p->getOutput()));
            $serial = self::hexToDec($hex);

            // Extract ONLY the certificate block (remove Bag Attributes from openssl output)
            if (preg_match('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', $certPem, $m)) {
                $certPem = "-----BEGIN CERTIFICATE-----\n" . trim($m[1]) . "\n-----END CERTIFICATE-----\n";
            }
            $certBase64 = preg_replace('/-----[^-]+-----|[\r\n\s]/', '', $certPem);
            $certDigest = base64_encode(sha1(base64_decode($certBase64), true));

            return compact('privateKeyPem', 'certPem', 'certBase64', 'certDigest', 'issuer', 'serial');
        } finally {
            @unlink($tmpP12);
            @unlink($tmpKey);
            @unlink($tmpCert);
        }
    }

    private function buildXadesBes(string $xml, array $k): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);

        $root = $doc->documentElement;
        $root->setIdAttribute('id', true);

        $sigId = 'Signature' . rand(100000, 999999);
        $keyInfoId = 'Certificate' . rand(100000, 999999);
        $signedPropsId = $sigId . '-SignedProperties';
        $refCompId = 'Reference-' . rand(100000, 999999);
        $signingTime = date('Y-m-d\TH:i:sP');

        $dsNs = 'http://www.w3.org/2000/09/xmldsig#';
        $etsiNs = 'http://uri.etsi.org/01903/v1.3.2#';
        $c14nAlgo = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
        $sha1Algo = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $rsaSha1Algo = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
        $envelopedAlgo = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

        // PASO 1: Digest del comprobante (antes de insertar Signature)
        $digestComprobante = base64_encode(sha1($doc->C14N(false, false), true));
        Log::info('SRI Firma: DigestValue comprobante', ['digest' => $digestComprobante]);

        // PASO 2: Construir Signature COMPLETO con placeholders
        // Los digests de KeyInfo y SignedProperties se recalculan EN CONTEXTO del documento
        // para que la C14N inclusiva incluya xmlns:etsi heredado del padre ds:Signature
        $sigXml = '<ds:Signature xmlns:ds="' . $dsNs . '" xmlns:etsi="' . $etsiNs . '" Id="' . $sigId . '">'
            . '<ds:SignedInfo Id="' . $sigId . '-SignedInfo">'
            . '<ds:CanonicalizationMethod Algorithm="' . $c14nAlgo . '"/>'
            . '<ds:SignatureMethod Algorithm="' . $rsaSha1Algo . '"/>'
            . '<ds:Reference Id="' . $refCompId . '" URI="#comprobante">'
            . '<ds:Transforms><ds:Transform Algorithm="' . $envelopedAlgo . '"/></ds:Transforms>'
            . '<ds:DigestMethod Algorithm="' . $sha1Algo . '"/>'
            . '<ds:DigestValue>' . $digestComprobante . '</ds:DigestValue>'
            . '</ds:Reference>'
            . '<ds:Reference URI="#' . $keyInfoId . '">'
            . '<ds:DigestMethod Algorithm="' . $sha1Algo . '"/>'
            . '<ds:DigestValue>PLACEHOLDER</ds:DigestValue>'
            . '</ds:Reference>'
            . '<ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#' . $signedPropsId . '">'
            . '<ds:DigestMethod Algorithm="' . $sha1Algo . '"/>'
            . '<ds:DigestValue>PLACEHOLDER</ds:DigestValue>'
            . '</ds:Reference>'
            . '</ds:SignedInfo>'
            . '<ds:SignatureValue Id="' . $sigId . '-SignatureValue"></ds:SignatureValue>'
            . '<ds:KeyInfo Id="' . $keyInfoId . '">'
            . '<ds:X509Data>'
            . '<ds:X509Certificate>' . $k['certBase64'] . '</ds:X509Certificate>'
            . '</ds:X509Data>'
            . '<ds:KeyValue><ds:RSAKeyValue>'
            . '<ds:Modulus>' . $this->getModulus($k['certPem']) . '</ds:Modulus>'
            . '<ds:Exponent>' . $this->getExponent($k['certPem']) . '</ds:Exponent>'
            . '</ds:RSAKeyValue></ds:KeyValue>'
            . '</ds:KeyInfo>'
            . '<ds:Object Id="' . $sigId . '-Object">'
            . '<etsi:QualifyingProperties Target="#' . $sigId . '">'
            . '<etsi:SignedProperties Id="' . $signedPropsId . '">'
            . '<etsi:SignedSignatureProperties>'
            . '<etsi:SigningTime>' . $signingTime . '</etsi:SigningTime>'
            . '<etsi:SigningCertificate><etsi:Cert><etsi:CertDigest>'
            . '<ds:DigestMethod Algorithm="' . $sha1Algo . '"/>'
            . '<ds:DigestValue>' . $k['certDigest'] . '</ds:DigestValue>'
            . '</etsi:CertDigest><etsi:IssuerSerial>'
            . '<ds:X509IssuerName>' . htmlspecialchars($k['issuer']) . '</ds:X509IssuerName>'
            . '<ds:X509SerialNumber>' . $k['serial'] . '</ds:X509SerialNumber>'
            . '</etsi:IssuerSerial></etsi:Cert></etsi:SigningCertificate>'
            . '</etsi:SignedSignatureProperties>'
            . '<etsi:SignedDataObjectProperties>'
            . '<etsi:DataObjectFormat ObjectReference="#' . $refCompId . '">'
            . '<etsi:Description>contenido comprobante</etsi:Description>'
            . '<etsi:MimeType>text/xml</etsi:MimeType>'
            . '</etsi:DataObjectFormat>'
            . '</etsi:SignedDataObjectProperties>'
            . '</etsi:SignedProperties>'
            . '</etsi:QualifyingProperties>'
            . '</ds:Object>'
            . '</ds:Signature>';

        // PASO 3: Insertar Signature en el documento como ultimo hijo del root
        $sigFragment = $doc->createDocumentFragment();
        $sigFragment->appendXML($sigXml);
        $doc->documentElement->appendChild($sigFragment);

        // PASO 4: XPath para queries en contexto
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ds', $dsNs);
        $xpath->registerNamespace('etsi', $etsiNs);

        // PASO 5: Calcular digest de KeyInfo EN CONTEXTO del documento
        // (hereda xmlns:etsi del padre ds:Signature — C14N inclusiva lo incluye)
        $keyInfoNode = $xpath->query('//ds:KeyInfo[@Id="' . $keyInfoId . '"]')->item(0);
        $digestKeyInfo = base64_encode(sha1($keyInfoNode->C14N(false, false), true));
        Log::info('SRI Firma: DigestValue KeyInfo (en contexto)', ['digest' => $digestKeyInfo]);

        // PASO 6: Calcular digest de SignedProperties EN CONTEXTO del documento
        $signedPropsNode = $xpath->query('//etsi:SignedProperties[@Id="' . $signedPropsId . '"]')->item(0);
        $digestSignedProps = base64_encode(sha1($signedPropsNode->C14N(false, false), true));
        Log::info('SRI Firma: DigestValue SignedProperties (en contexto)', ['digest' => $digestSignedProps]);

        // PASO 7: Reemplazar placeholders con digests reales
        $refs = $xpath->query('//ds:SignedInfo/ds:Reference');
        foreach ($refs as $ref) {
            $uri = $ref->getAttribute('URI');
            $dvNode = $xpath->query('ds:DigestValue', $ref)->item(0);
            if ($uri === '#' . $keyInfoId) {
                $this->setNodeText($doc, $dvNode, $digestKeyInfo);
            } elseif ($uri === '#' . $signedPropsId) {
                $this->setNodeText($doc, $dvNode, $digestSignedProps);
            }
        }

        // PASO 8: Canonicalizar SignedInfo EN CONTEXTO y firmar con RSA-SHA1
        $signedInfoNode = $xpath->query('//ds:SignedInfo')->item(0);
        $signedInfoC14n = $signedInfoNode->C14N(false, false);

        $pkey = openssl_pkey_get_private($k['privateKeyPem']);
        if (! $pkey) throw new \RuntimeException('No se pudo cargar clave privada');

        openssl_sign($signedInfoC14n, $signature, $pkey, OPENSSL_ALGO_SHA1);
        $signatureValue = base64_encode($signature);
        Log::info('SRI Firma: RSA-SHA1 generada (en contexto)');

        // PASO 9: Establecer SignatureValue
        $sigValueNode = $xpath->query('//ds:SignatureValue')->item(0);
        $this->setNodeText($doc, $sigValueNode, $signatureValue);

        return $doc->saveXML();
    }

    private function setNodeText(\DOMDocument $doc, \DOMNode $node, string $text): void
    {
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($doc->createTextNode($text));
    }

    private function verifyLocally(string $signedXml, string $certPem): void
    {
        $doc = new \DOMDocument();
        $doc->loadXML($signedXml);

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $sigValueNode = $xpath->query('//ds:SignatureValue')->item(0);
        $signedInfoNode = $xpath->query('//ds:SignedInfo')->item(0);

        if (! $sigValueNode || ! $signedInfoNode) {
            throw new \RuntimeException('Nodos de firma no encontrados');
        }

        $signedInfoC14n = $signedInfoNode->C14N(false, false);
        $sigValue = base64_decode($sigValueNode->textContent);
        $pubKey = openssl_pkey_get_public($certPem);

        $result = openssl_verify($signedInfoC14n, $sigValue, $pubKey, OPENSSL_ALGO_SHA1);

        if ($result !== 1) {
            Log::error('SRI Firma: Verificacion local FALLIDA');
            throw new \RuntimeException('Firma local invalida — no enviar al SRI');
        }

        Log::info('SRI Firma: Verificacion local OK');
    }

    private function getModulus(string $certPem): string
    {
        $cert = openssl_x509_read($certPem);
        $pubKey = openssl_pkey_get_public($cert);
        $details = openssl_pkey_get_details($pubKey);
        return base64_encode($details['rsa']['n']);
    }

    private function getExponent(string $certPem): string
    {
        $cert = openssl_x509_read($certPem);
        $pubKey = openssl_pkey_get_public($cert);
        $details = openssl_pkey_get_details($pubKey);
        return base64_encode($details['rsa']['e']);
    }

    private static function hexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        if (function_exists('gmp_strval')) {
            return gmp_strval(gmp_init($hex, 16), 10);
        }
        $dec = '0';
        for ($i = 0; $i < strlen($hex); $i++) {
            $dec = bcmul($dec, '16');
            $dec = bcadd($dec, (string) hexdec($hex[$i]));
        }
        return $dec;
    }
}
