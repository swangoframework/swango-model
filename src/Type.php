<?php
namespace Swango\Model;
/**
 *
 * @method intoProfile($var)
 * @author fdream
 */
abstract class Type {
    private static Type\Integer $integer;
    public static function INTEGER(): Type\Integer {
        return self::$integer ??= new Type\Integer();
    }
    private static Type\Ip $ip;
    public static function IP(): Type\Ip {
        return self::$ip ??= new Type\Ip();
    }
    private static Type\Boolean $boolean;
    public static function BOOLEAN(): Type\Boolean {
        return self::$boolean ??= new Type\Boolean();
    }
    private static Type\Double $double;
    public static function DOUBLE(): Type\Double {
        return self::$double ??= new Type\Double();
    }
    private static Type\Varchar $varchar;
    public static function VARCHAR(): Type\Varchar {
        return self::$varchar ??= new Type\Varchar();
    }
    private static Type\JsonArray $json_array;
    public static function JSON_ARRAY(): Type\JsonArray {
        return self::$json_array ??= new Type\JsonArray();
    }
    private static Type\JsonObject $json_object;
    public static function JSON_OBJECT(): Type\JsonObject {
        return self::$json_object ??= new Type\JsonObject();
    }
    private static Type\Explode $explode;
    public static function EXPLODE(): Type\Explode {
        return self::$explode ??= new Type\Explode();
    }
    public static function ENUM_INT(string $enum_class): Type\IntEnum {
        return new Type\IntEnum($enum_class);
    }
    public static function ENUM_STRING(string $enum_class): Type\StringEnum {
        return new Type\StringEnum($enum_class);
    }
    public function intoDB($var) {
        return $var;
    }
}