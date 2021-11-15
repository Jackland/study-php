<?php

namespace App\Services\Maintain;

use App\Helper\ModuleHelper;
use Framework\Helper\StringHelper;

class MaintainService
{
    /**
     * 校验是否需要显示运维页面
     * @return bool
     */
    public function isMaintain(): bool
    {
        [
            'is_down' => $isDown,
            'down_token' => $downToken,
            'admin_whitelist' => $adminRoute,
            'catalog_whitelist' => $catalogRoute,
        ] = config('maintain');
        if (!$isDown || empty($downToken)) {
            return false;
        }
        // token
        $requestToken = request('_d_t', $_COOKIE['_d_t'] ?? false);
        if ($requestToken == $downToken) {
            setcookie('_d_t', $downToken, time() + 86400, '/');
            return false;
        } else {
            setcookie('_d_t', '', time() - 86400, '/'); // 清空cookie
        }
        // 白名单
        $route = request('route', '');
        if (ModuleHelper::isInCatalog() && !empty($catalogRoute)) {
            foreach ($catalogRoute as $routePattern) {
                if (StringHelper::matchWildcard($routePattern, $route)) {
                    return false;
                }
            }
        }
        if (ModuleHelper::isInAdmin() && !empty($adminRoute)) {
            foreach ($adminRoute as $routePattern) {
                if (StringHelper::matchWildcard($routePattern, $route)) {
                    return false;
                }
            }
        }
        return true;
    }
}
