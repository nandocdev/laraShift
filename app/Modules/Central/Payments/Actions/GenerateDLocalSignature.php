<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Actions;

/**
 * [REALIZACIÓN DE CASO DE USO - RUP]
 * Caso de Uso: Generar Firma de Seguridad dLocal V2.1
 * 
 * Este Action implementa el algoritmo de firma HMAC-SHA256 requerido por dLocal
 * para garantizar la integridad y autenticidad de las peticiones.
 */
final class GenerateDLocalSignature
{
    /**
     * Ejecuta la generación de la firma.
     * 
     * Mensaje: X-Login + X-Date + RequestBody
     * Algoritmo: HMAC-SHA256
     */
    public function execute(string $xLogin, string $xDate, string $secretKey, ?string $body = null): string
    {
        $message = $xLogin . $xDate . ($body ?? '');
        
        $hash = hash_hmac('sha256', $message, $secretKey);
        
        return "V2-HMAC-SHA256, Signature: {$hash}";
    }
}
