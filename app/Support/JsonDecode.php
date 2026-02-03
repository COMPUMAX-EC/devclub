<?php
namespace App\Support;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Traversable;
use function str_starts_with;

final class JsonDecode implements ArrayAccess, IteratorAggregate
{
    private array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    // Acceso como objeto: $obj->prop
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    // Acceso como array: $obj['prop']
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string)$offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[(string)$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[(string)$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[(string)$offset]);
    }

    // foreach ($obj as $k => $v)
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * - Si $json es null / vacío / solo espacios => retorna []
     * - Normaliza entrada: trim, remueve BOM UTF-8 si existe.
     *
     * $first_level_associative:
     * - true  => el primer nivel se mantiene como array (sin envolver) y solo se convierten sus hijos
     * - false => el primer nivel se convierte igual que el resto (objetos asociativos => JsonDecode; listas => array)
     *
     * Nota: si $first_level_associative === false y el JSON root es un objeto (asociativo),
     * el retorno real será JsonDecode, pero se castea a array para respetar la firma.
     */
    public static function get(?string $json, bool $first_level_associative = false): array
    {
        if ($json === null) {
            return [];
        }

        // Normalización básica (usuario puede escribir cualquier cosa)
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        // Remover BOM UTF-8 si viene pegado al inicio
        if (str_starts_with($json, "\xEF\xBB\xBF")) {
            $json = substr($json, 3);
            $json = ltrim($json);
            if ($json === '') {
                return [];
            }
        }

        // Decodificar (si no es JSON válido, lanzará JsonException)
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // Si el root no es array (p.ej. un número/string), normalizar a []
        if (!is_array($decoded)) {
            return [];
        }

        $toDual = function ($value) use (&$toDual) {
            if (!is_array($value)) {
                return $value;
            }

            $isAssoc = array_keys($value) !== range(0, count($value) - 1);

            if ($isAssoc) {
                $converted = [];
                foreach ($value as $k => $v) {
                    $converted[$k] = $toDual($v);
                }
                return new self($converted);
            }

            return array_map($toDual, $value); // listas quedan como array
        };

        if ($first_level_associative) {
            // Mantener primer nivel como array; convertir hacia abajo
            foreach ($decoded as $k => $v) {
                $decoded[$k] = $toDual($v);
            }
            return $decoded;
        }

        // Convertir el primer nivel igual que los demás niveles
        $convertedRoot = $toDual($decoded);

        // La firma exige array; si root es objeto asociativo, $convertedRoot será JsonDecode.
        // En ese caso lo convertimos a array para respetar el tipo de retorno.
        if ($convertedRoot instanceof self) {
            return $convertedRoot->toArray();
        }

        // Si era lista, seguirá siendo array
        return $convertedRoot;
    }
}
