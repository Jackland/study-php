#!/usr/bin/env php
<?php
/**
 * 选择环境：php init.php
 * 直接设置环境：php init.php --env=dev
 * 直接设置环境并覆盖：php init.php --env=dev --overwrite=all
 * 直接设置环境并跳过：php init.php --env=dev --overwrite=skip
 */

// 处理 linux 环境下 mkdir(0775) 创建出目录权限为 0755 的情况
// https://stackoverflow.com/questions/36747257/mkdir-creating-0755-instead-of-0775
umask(002);

main();

function main()
{
    $params = getParams();
    $root = str_replace('\\', '/', __DIR__);
    $envs = require("$root/environments/index.php");
    $envNames = array_keys($envs);

    echo "Init Envs\n\n";

    $envName = null;
    if (empty($params['env']) || $params['env'] === '1') {
        echo "Which Env?\n\n";
        foreach ($envNames as $i => $name) {
            echo "  [$i] $name\n";
        }
        echo "\n  Choice [0-" . (count($envs) - 1) . ', or "q" to quit] ';
        $answer = trim(fgets(STDIN));

        if (!ctype_digit($answer) || !in_array($answer, range(0, count($envs) - 1))) {
            echo "\n  Quit.\n";
            exit(0);
        }

        if (isset($envNames[$answer])) {
            $envName = $envNames[$answer];
        }
    } else {
        $envName = $params['env'];
    }

    if (!in_array($envName, $envNames)) {
        $envsList = implode(', ', $envNames);
        echo "\n  $envName is not a valid environment. Try one of the following: $envsList. \n";
        exit(2);
    }

    $env = $envs[$envName];

    if (empty($params['env'])) {
        echo "\n  Init under '{$envName}' environment? [yes|no] ";
        $answer = trim(fgets(STDIN));
        if (strncasecmp($answer, 'y', 1)) {
            echo "\n  Quit.\n";
            exit(0);
        }
    }

    echo "\n  Start initialization ...\n\n";
    $files = getFileList("$root/environments/{$env['path']}");
    $all = false;
    foreach ($files as $file) {
        if (!copyFile($root, "environments/{$env['path']}/$file", $file, $all, $params)) {
            break;
        }
    }

    echo "\n  ... initialization completed.\n\n";
}

function getFileList($root, $basePath = '')
{
    $files = [];
    $handle = opendir($root);
    while (($path = readdir($handle)) !== false) {
        if ($path === '.git' || $path === '.svn' || $path === '.' || $path === '..') {
            continue;
        }
        $fullPath = "$root/$path";
        $relativePath = $basePath === '' ? $path : "$basePath/$path";
        if (is_dir($fullPath)) {
            $files = array_merge($files, getFileList($fullPath, $relativePath));
        } else {
            $files[] = $relativePath;
        }
    }
    closedir($handle);
    return $files;
}

function copyFile($root, $source, $target, &$all, $params)
{
    if (!is_file($root . '/' . $source)) {
        echo "       skip $target ($source not exist)\n";
        return true;
    }
    if (is_file($root . '/' . $target)) {
        if (file_get_contents($root . '/' . $source) === file_get_contents($root . '/' . $target)) {
            echo "  unchanged $target\n";
            return true;
        }
        if ($all) {
            echo "  overwrite $target\n";
        } else {
            echo "      exist $target\n";
            echo "            ...overwrite? [Yes|No|All|Quit] ";


            $answer = !empty($params['overwrite']) ? $params['overwrite'] : trim(fgets(STDIN));
            if (!strncasecmp($answer, 'q', 1)) {
                return false;
            } else {
                if (!strncasecmp($answer, 'y', 1)) {
                    echo "  overwrite $target\n";
                } else {
                    if (!strncasecmp($answer, 'a', 1)) {
                        echo "  overwrite $target\n";
                        $all = true;
                    } else {
                        echo "       skip $target\n";
                        return true;
                    }
                }
            }
        }
        file_put_contents($root . '/' . $target, file_get_contents($root . '/' . $source));
        return true;
    }
    echo "   generate $target\n";
    @mkdir(dirname($root . '/' . $target), 0775, true);
    file_put_contents($root . '/' . $target, file_get_contents($root . '/' . $source));
    return true;
}

function getParams()
{
    $rawParams = [];
    if (isset($_SERVER['argv'])) {
        $rawParams = $_SERVER['argv'];
        array_shift($rawParams);
    }

    $params = [];
    foreach ($rawParams as $param) {
        if (preg_match('/^--(\w+)(=(.*))?$/', $param, $matches)) {
            $name = $matches[1];
            $params[$name] = isset($matches[3]) ? $matches[3] : true;
        } else {
            $params[] = $param;
        }
    }
    return $params;
}

function printError($message)
{
    echo "\n  Error. $message \n";
}
