<?php

declare(strict_types=1);

namespace Sharpfy\NextApi;

use Sharpfy\NextApi\Exceptions\NextApiValidationException;

/** Normalização e validação das entradas, antes de qualquer requisição. */
final class Input
{
    /** Tira pontuação e põe em maiúsculas. Aceita o CNPJ alfanumérico (12 alfanuméricos e 2 dígitos). */
    public static function cnpj(string $cnpj): string
    {
        $v = strtoupper((string) preg_replace('/[\s.\/-]/', '', $cnpj));
        if (!preg_match('/^[0-9A-Z]{12}[0-9]{2}$/D', $v)) {
            throw new NextApiValidationException("CNPJ inválido: \"{$cnpj}\". Informe 14 caracteres, com ou sem pontuação.");
        }
        return $v;
    }

    /** CEP com 8 dígitos (aceita "01001-000"). */
    public static function cep(string $cep): string
    {
        $v = (string) preg_replace('/[\s.-]/', '', $cep);
        if (!preg_match('/^[0-9]{8}$/D', $v)) {
            throw new NextApiValidationException("CEP inválido: \"{$cep}\". Informe 8 dígitos.");
        }
        return $v;
    }

    public static function hashSocio(string $hash): string
    {
        $v = trim($hash);
        if (!preg_match('/^[0-9a-fA-F]{20}$/D', $v)) {
            throw new NextApiValidationException('hashSocio inválido: são 20 caracteres hexadecimais.');
        }
        return strtolower($v);
    }

    public static function segment(string $value, string $name): string
    {
        $v = trim($value);
        if (!preg_match('/^[0-9A-Za-z_-]+$/D', $v)) {
            throw new NextApiValidationException("{$name} inválido: \"{$value}\".");
        }
        return rawurlencode($v);
    }

    public static function id(int $id, string $name): string
    {
        if ($id <= 0) {
            throw new NextApiValidationException("{$name} inválido: {$id}.");
        }
        return (string) $id;
    }

    public static function positive(float $v, string $name): void
    {
        if (!is_finite($v) || $v <= 0) {
            throw new NextApiValidationException("{$name} tem de ser maior que zero.");
        }
    }

    public static function range(float $v, float $min, float $max, string $name): void
    {
        if (!is_finite($v) || $v < $min || $v > $max) {
            throw new NextApiValidationException("{$name} tem de estar entre {$min} e {$max}.");
        }
    }

    /** @param list<string> $cnpjs @return list<string> */
    public static function batch(array $cnpjs): array
    {
        if ($cnpjs === []) {
            throw new NextApiValidationException('Informe ao menos um CNPJ.');
        }
        if (count($cnpjs) > 100) {
            throw new NextApiValidationException('O lote aceita até 100 CNPJs; vieram ' . count($cnpjs) . '.');
        }
        return array_map(static fn ($c) => self::cnpj((string) $c), array_values($cnpjs));
    }

    /** Query string sem os valores ausentes, na ordem informada; booleanos como true/false. */
    public static function query(array $params): string
    {
        $pairs = [];
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            } elseif ($v instanceof \DateTimeInterface) {
                $v = $v->format('Y-m-d');
            } elseif (is_float($v)) {
                $v = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
            }
            $pairs[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
        }
        return $pairs === [] ? '' : '?' . implode('&', $pairs);
    }
}
