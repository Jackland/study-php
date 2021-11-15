<?php

namespace Framework\Helper;

use JsonException;

/**
 * 后续可能会用该包代替
 * @link https://github.com/yiisoft/json
 */
class Json
{
    /**
     * encode UTF-8
     *
     * @param mixed $value
     * @param int $options {@see http://www.php.net/manual/en/function.json-encode.php}
     * @param int $depth
     * @return string
     * @throw JsonException
     */
    public static function encode(
        $value,
        int $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        int $depth = 512
    ): string
    {
        $expressions = [];
        $value = self::processData($value, $expressions, uniqid('', true));
        $json = json_encode($value, $options, $depth);
        if (($msg = json_last_error_msg()) !== 'No error') {
            throw new JsonException($msg);
        }
        return strtr($json, $expressions);
    }

    /**
     * encode Html 安全
     *
     * @param mixed $value
     * @return string
     * @throws JsonException
     */
    public static function htmlEncode($value): string
    {
        return self::encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
        );
    }

    /**
     * decode jsonData
     *
     * @param string $json
     * @param bool $asArray
     * @param int $depth
     * @param int $options
     * @return mixed
     * @throws JsonException
     */
    public static function decode(
        string $json,
        bool $asArray = true,
        int $depth = 512,
        int $options = 0
    )
    {
        if ($json === '') {
            return null;
        }
        $result = json_decode($json, $asArray, $depth, $options);
        if (($msg = json_last_error_msg()) !== 'No error') {
            throw new JsonException($msg);
        }
        return $result;
    }

    /**
     * Pre-processes the data before sending it to `json_encode()`.
     *
     * @param mixed $data The data to be processed.
     * @param array $expressions collection of JavaScript expressions
     * @param string $expPrefix a prefix internally used to handle JS expressions
     *
     * @return mixed The processed data.
     */
    private static function processData($data, &$expressions, $expPrefix)
    {
        if (\is_object($data)) {
            if ($data instanceof JsExpression) {
                $token = "!{[$expPrefix=" . count($expressions) . ']}!';
                $expressions['"' . $token . '"'] = $data->expression;

                return $token;
            }

            if ($data instanceof \JsonSerializable) {
                return self::processData($data->jsonSerialize(), $expressions, $expPrefix);
            }

            if ($data instanceof \DateTimeInterface) {
                return self::processData((array)$data, $expressions, $expPrefix);
            }

            if ($data instanceof \SimpleXMLElement) {
                $data = (array)$data;
            } else {
                $result = [];
                foreach ($data as $name => $value) {
                    $result[$name] = $value;
                }
                $data = $result;
            }
            if ($data === []) {
                return new \stdClass();
            }
        }
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                if (\is_array($value) || \is_object($value)) {
                    $data[$key] = self::processData($value, $expressions, $expPrefix);
                }
            }
        }
        return $data;
    }
}
