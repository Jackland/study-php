<?php

namespace App\Components\TwigExtensions;

use Framework\Helper\JsExpression;
use Framework\Helper\Json;
use JsonException;

class JsVarExtension extends AbsTwigExtension
{
    protected $filters = [
        'js_var',
        'js_json',
        'json_encode',
        'json_decode',
        'js_expression'
    ];

    protected $functions = [
        'js_var',
        'js_json',
        'json_encode',
        'json_decode',
        'js_expression',
    ];

    public function js_var($value)
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_string($value)) {
            $value = strtr($value, [
                '\'' => '\\\'',
                "\n" => "\\\\n",
                "\r" => "\\\\r",
            ]);
            return "'{$value}'";
        }
        return $value;
    }

    public function js_json($value)
    {
        if (is_array($value)) {
            return Json::encode($value);
        }
        if (is_string($value)) {
            try {
                return $this->js_json($this->json_decode($value));
            } catch (JsonException $e) {
                return $this->js_var($value);
            }
        }
        return $this->js_var($value);
    }

    public function json_encode($value)
    {
        return strtr(Json::encode($value), [
            '\'' => '\\\'',
            '\n' => '\\\\n',
            '\r' => '\\\\r',
        ]);
    }

    public function json_decode($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        $value = strtr($value, [
            "\n" => '\n',
        ]);
        return Json::decode($value);
    }

    public function js_expression($value)
    {
        return new JsExpression($value);
    }
}
