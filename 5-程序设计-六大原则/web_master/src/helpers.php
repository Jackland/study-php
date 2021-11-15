<?php

if (!function_exists('validator')) {
    /**
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return \Illuminate\Contracts\Validation\Validator|\Illuminate\Contracts\Validation\Factory
     */
    function validator(array $data = [], array $rules = [], array $messages = [], array $customAttributes = [])
    {
        $factory = app()->get('validator');

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($data, $rules, $messages, $customAttributes);
    }
}

if (!function_exists('customer')) {
    /**
     * 当前登录的 customer
     * @return \Cart\Customer
     */
    function customer()
    {
        return app()->ocRegistry->get('customer');
    }
}

if (!function_exists('__')) {
    /**
     * @param string $key
     * @param array $replace
     * @param string|null $category
     * @param null|string $locale
     * @return string
     */
    function __(string $key, array $replace = [], $category = null, $locale = null)
    {
        return trans()->t($key, $replace, $category, $locale);
    }
}

if (!function_exists('__choice')) {
    /**
     * @param string $key
     * @param int $number
     * @param array $replace
     * @param string|null $category
     * @param null|string $locale
     * @return string
     */
    function __choice(string $key, $number, array $replace = [], $category = null, $locale = null)
    {
        return trans()->tc($key, $number, $replace, $category, $locale);
    }
}

if (!function_exists('get_env')) {
    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get_env(string $key, $default = null)
    {
        return defined($key) ? constant($key) : $default;
    }
}
