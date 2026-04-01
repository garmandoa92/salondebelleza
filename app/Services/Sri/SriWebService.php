<?php

namespace App\Services\Sri;

class SriWebService
{
    private array $urls = [
        'test' => [
            'reception' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'authorization' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
        'production' => [
            'reception' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'authorization' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
    ];

    public function enviarComprobante(string $xmlFirmado, string $ambiente = 'test'): array
    {
        $env = $ambiente === 'production' ? 'production' : 'test';

        try {
            $client = new \SoapClient($this->urls[$env]['reception'], [
                'exceptions' => true,
                'connection_timeout' => 30,
                'trace' => true,
            ]);

            $response = $client->validarComprobante(['xml' => base64_encode($xmlFirmado)]);

            $estado = $response->RespuestaRecepcionComprobante->estado ?? 'DEVUELTA';
            $mensajes = [];

            if (isset($response->RespuestaRecepcionComprobante->comprobantes->comprobante)) {
                $comp = $response->RespuestaRecepcionComprobante->comprobantes->comprobante;
                if (isset($comp->mensajes->mensaje)) {
                    $msgs = is_array($comp->mensajes->mensaje) ? $comp->mensajes->mensaje : [$comp->mensajes->mensaje];
                    foreach ($msgs as $msg) {
                        $mensajes[] = [
                            'tipo' => $msg->tipo ?? '',
                            'identificador' => $msg->identificador ?? '',
                            'mensaje' => $msg->mensaje ?? '',
                            'informacionAdicional' => $msg->informacionAdicional ?? '',
                        ];
                    }
                }
            }

            return [
                'estado' => $estado,
                'mensajes' => $mensajes,
            ];
        } catch (\Exception $e) {
            return [
                'estado' => 'ERROR',
                'mensajes' => [['tipo' => 'ERROR', 'mensaje' => $e->getMessage()]],
            ];
        }
    }

    public function consultarAutorizacion(string $claveAcceso, string $ambiente = 'test'): array
    {
        $env = $ambiente === 'production' ? 'production' : 'test';

        try {
            $client = new \SoapClient($this->urls[$env]['authorization'], [
                'exceptions' => true,
                'connection_timeout' => 30,
                'trace' => true,
            ]);

            $response = $client->autorizacionComprobante(['claveAccesoComprobante' => $claveAcceso]);

            $auth = $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion ?? null;
            if (! $auth) {
                return ['estado' => 'NO AUTORIZADO', 'mensajes' => [['mensaje' => 'Sin respuesta de autorizacion']]];
            }

            return [
                'estado' => $auth->estado ?? 'NO AUTORIZADO',
                'numeroAutorizacion' => $auth->numeroAutorizacion ?? null,
                'fechaAutorizacion' => $auth->fechaAutorizacion ?? null,
                'mensajes' => isset($auth->mensajes) ? (array) $auth->mensajes : [],
            ];
        } catch (\Exception $e) {
            return [
                'estado' => 'ERROR',
                'mensajes' => [['mensaje' => $e->getMessage()]],
            ];
        }
    }
}
