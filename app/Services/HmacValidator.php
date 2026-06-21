<?php

namespace App\Services;

class HmacValidator
{
    private int $ventanaSegundos;

    public function __construct()
    {
        $this->ventanaSegundos = (int) config('monitor.hmac_ventana');
    }

    public function calcularFirma(string $secreto, string $serverId, string $timestampIso): string
    {
        return hash_hmac('sha256', "{$serverId}:{$timestampIso}", $secreto);
    }

    public function validarTimestamp(string $timestampIso): bool //rechaza mensajes cuyo timestamp excede la ventana antireplay
    {
        try {
            $momento = new \DateTimeImmutable($timestampIso);
            $ahora   = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            return abs($ahora->getTimestamp() - $momento->getTimestamp()) <= $this->ventanaSegundos;
        } catch (\Exception) {
            return false;
        }
    }

    public function validarFirma(string $secreto, string $serverId, string $timestampIso, string $firmaRecibida): bool
    {
        return hash_equals( //hash_equals previene timing attacks en la comparacion
            $this->calcularFirma($secreto, $serverId, $timestampIso),
            $firmaRecibida
        );
    }
}
