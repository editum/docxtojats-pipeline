<?php

namespace App\Component\Data;

use InvalidArgumentException;

class PropertyAccess
{
    /**
     * Función que obtiene el valor asociado a una clave en un array|objeto.
     *
     * @param mixed $data array|objeto|...
     * @param string $key clave a buscar
     * @param mixed $default valor a devolver en caso de no existir
     * @return mixed
     */
    public static function getValue($data, string $key, $default = null)
    {
        if (is_array($data)) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        } elseif(is_object($data)) {
            if (property_exists($data, $key)) {
                return $data->$key;
            }
        } else {
            // REVIEW throw???
            return null;
        }

        return $default;
    }

    /**
     * Comprueba la existencia de una clave.
     * @param mixed $data array|objeto|...
     * @param string $key Lista de claves a comprobar
     * @return bool true si contiene la clave
     */
    public static function hasKey($data, string $key): bool
    {
        if (is_array($data)) {
            if (!array_key_exists($key, $data)) return false;
        } elseif (is_object($data)) {
            if (!property_exists($data, $key)) return false;
        } else {
            return false;
        }
        return true;
    }

    /**
     * Comprueba la existencia de una o más claves.
     * @param mixed $data array|objeto|...
     * @param string[] $keys Lista de claves a comprobar
     * @return bool true si contine todas las claves
     */
    public static function hasKeys($data, string ...$keys): bool {
        foreach ($keys as $key) {
            if (!self::hasKey($data, $key)) {
                return false;
            }
        }
        return true;
    }


    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Devuelve un accessor a una propiedad.
     * 
     * @param string $key
     * @return ?self
     * 
     */
    public function accessor(string $key, bool $throw = false): ?self
    {
        if ($this->has($key)) {
            return new self($this->get($key));
        }
        if ($throw) {
            throw new InvalidArgumentException('Key not found: ' . $key,);
        }
        return null;
    }

    /**
     * Devuelve un valor dada una clave, si no existe se devuelve el valor por
     * defecto.
     *
     * @param ?string $key clave a buscar
     * @param mixed $default valor a devolver en caso de no existir
     * @return mixed si no se pasa key se devolverán los datos
     */
    public function get(?string $key = null, $default = null)
    {
        return $key === null
            ? $this->data
            : self::getValue($this->data, $key, $default);
    }

    /**
     * Comprueba la existencia de una o más claves.
     *
     * @param string $keys claves a comprobar
     * @return bool true si se encuentran todas las claves
     */
    public function has(string ...$keys): bool
    {
        return self::hasKeys($this->data, ...$keys);
    }
}
