<?php

namespace Framework\Aliases;

use ArrayAccess;

/**
 * @link https://github.com/yiisoft/aliases
 */
final class Aliases implements ArrayAccess
{
    private $aliases = [];

    /**
     * @param array $config
     * @throws \InvalidArgumentException if $path is an invalid alias.
     * @see set()
     * @see get()
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $alias => $path) {
            $this->set($alias, $path);
        }
    }

    /**
     * Magic setter to enable simple aliases configuration.
     * @param string $name
     * @param string $value
     */
    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * Registers a path alias.
     *
     * A path alias is a short name representing a long path (a file path, a URL, etc.)
     *
     * For example, `@vendor` may store path to `vendor` directory.
     *
     * A path alias must start with the character '@' so that it can be easily differentiated
     * from non-alias paths.
     *
     * Note that this method does not check if the given path exists or not. All it does is
     * to associate the alias with the path.
     *
     * Any trailing '/' and '\' characters in the given path will be trimmed.
     *
     * @param string $alias the alias name (e.g. "@vendor"). It must start with a '@' character.
     * It may contain the forward slash '/' which serves as boundary character when performing
     * alias translation by {@see get()}.
     * @param string $path the path corresponding to the alias.
     * Trailing '/' and '\' characters will be trimmed. This can be
     *
     * - a directory or a file path (e.g. `/tmp`, `/tmp/main.txt`)
     * - a URL (e.g. `http://www.yiiframework.com`)
     * - a path alias (e.g. `@vendor/yiisoft`). It will be resolved on {@see get()} call.
     *
     * @see get()
     */
    public function set($alias, $path)
    {
        if (!$this->isAlias($alias)) {
            $alias = '@' . $alias;
        }
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        $path = rtrim($path, '\\/');
        if (!array_key_exists($root, $this->aliases)) {
            if ($pos === false) {
                $this->aliases[$root] = $path;
            } else {
                $this->aliases[$root] = [$alias => $path];
            }
        } elseif (\is_string($this->aliases[$root])) {
            if ($pos === false) {
                $this->aliases[$root] = $path;
            } else {
                $this->aliases[$root] = [
                    $alias => $path,
                    $root => $this->aliases[$root],
                ];
            }
        } else {
            $this->aliases[$root][$alias] = $path;
            krsort($this->aliases[$root]);
        }
    }

    /**
     * Remove alias.
     * @param string $alias Alias to be removed.
     */
    public function remove($alias)
    {
        if (!$this->isAlias($alias)) {
            $alias = '@' . $alias;
        }
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        if (array_key_exists($root, $this->aliases)) {
            if (\is_array($this->aliases[$root])) {
                unset($this->aliases[$root][$alias]);
            } elseif ($pos === false) {
                unset($this->aliases[$root]);
            }
        }
    }

    /**
     * Translates a path alias into an actual path.
     *
     * The translation is done according to the following procedure:
     *
     * 1. If the given alias does not start with '@', it is returned back without change;
     * 2. Otherwise, look for the longest registered alias that matches the beginning part
     *    of the given alias. If it exists, replace the matching part of the given alias with
     *    the corresponding registered path.
     * 3. Throw an exception if path alias cannot be resolved.
     *
     * For example, if '@vendor' is registered as the alias to the vendor directory,
     * say '/path/to/vendor'. The alias '@vendor/yiisoft' would then be translated into '/path/to/vendor/yiisoft'.
     *
     * If you have registered two aliases '@foo' and '@foo/bar'. Then translating '@foo/bar/config'
     * would replace the part '@foo/bar' (instead of '@foo') with the corresponding registered path.
     * This is because the longest alias takes precedence.
     *
     * However, if the alias to be translated is '@foo/barbar/config', then '@foo' will be replaced
     * instead of '@foo/bar', because '/' serves as the boundary character.
     *
     * Note, this method does not check if the returned path exists or not.
     *
     * @param string $alias the alias to be translated.
     * @return string the path corresponding to the alias.
     * @throws \InvalidArgumentException if the root alias is not previously registered.
     * @see setAlias()
     */
    public function get($alias)
    {
        if (!$this->isAlias($alias)) {
            return $alias;
        }

        $foundAlias = $this->findAlias($alias);

        if ($foundAlias === null) {
            throw new \InvalidArgumentException("Invalid path alias: $alias");
        }

        $foundSubAlias = $this->findAlias($foundAlias);
        if ($foundSubAlias === null) {
            return $foundAlias;
        }

        return $this->get($foundSubAlias);
    }

    private function findAlias($alias)
    {
        $pos = strpos($alias, '/');
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        if (array_key_exists($root, $this->aliases)) {
            if (\is_string($this->aliases[$root])) {
                return $pos === false ? $this->aliases[$root] : $this->aliases[$root] . substr($alias, $pos);
            }

            foreach ($this->aliases[$root] as $name => $path) {
                if (strpos($alias . '/', $name . '/') === 0) {
                    return $path . substr($alias, strlen($name));
                }
            }
        }

        return null;
    }

    /**
     * Returns all path aliases translated into an actual paths.
     * @return array Actual paths indexed by alias name.
     */
    public function getAll()
    {
        $result = [];
        foreach ($this->aliases as $name => $path) {
            $result[$name] = $this->get($path);
        }
        return $result;
    }

    private function isAlias($alias)
    {
        return !strncmp($alias, '@', 1);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset)
    {
        return isset($this->aliases[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
