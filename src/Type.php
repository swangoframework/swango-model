<?php
namespace Swango\Model;
/**
 *
 * @method intoProfile($var)
 * @author fdream
 */
abstract class Type {
    private static $integer;
    public static function INTEGER(): Type\Integer {
        if (! isset(self::$integer))
            self::$integer = new Type\Integer();
        return self::$integer;
    }
    private static $ip;
    public static function IP(): Type\Ip {
        if (! isset(self::$ip))
            self::$ip = new Type\Ip();
        return self::$ip;
    }
    private static $boolean;
    public static function BOOLEAN(): Type\Boolean {
        if (! isset(self::$boolean))
            self::$boolean = new Type\Boolean();
        return self::$boolean;
    }
    private static $double;
    public static function DOUBLE(): Type\Double {
        if (! isset(self::$double))
            self::$double = new Type\Double();
        return self::$double;
    }
    private static $varchar;
    public static function VARCHAR(): Type\Varchar {
        if (! isset(self::$varchar))
            self::$varchar = new Type\Varchar();
        return self::$varchar;
    }
    private static $json_array;
    public static function JSON_ARRAY(): Type\JsonArray {
        if (! isset(self::$json_array))
            self::$json_array = new Type\JsonArray();
        return self::$json_array;
    }
    private static $json_object;
    public static function JSON_OBJECT(): Type\JsonObject {
        if (! isset(self::$json_object))
            self::$json_object = new Type\JsonObject();
        return self::$json_object;
    }
    private static $explode;
    public static function EXPLODE(): Type\Explode {
        if (! isset(self::$explode))
            self::$explode = new Type\Explode();
        return self::$explode;
    }
    public function intoDB($var) {
        return $var;
    }
}