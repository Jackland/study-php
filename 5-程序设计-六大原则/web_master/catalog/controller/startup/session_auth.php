<?php

use Illuminate\Support\Str;

/**
 * session 登录的信息变化检查
 * 防止同一浏览器切换登录信息后，原停留在上一个页面的，存在表单或提交信息的，提交后将数据更新到了当前登录的用户信息上
 */
class ControllerStartupSessionAuth extends Controller
{
    public function index()
    {
        // 未登录不检查
        if (!customer() || !customer()->isLogged() || !customer()->getId()) {
            return;
        }
        // 跳过部分路由（已知的对外的接口）
        $route = request('route', 'common/home');
        if (Str::startsWith(ltrim($route, '/'), 'api')) {
            return;
        }
        if (in_array($route, ['message/seller/sendMessageBatch', 'message/seller/download'])) {
            // 此处见：catalog/controller/message/seller.php::__construct
            return;
        }
        // 跳过无 referer 的（为了兼容系统中存在的不知道的对外开放的路由接口的情况，已知的补充在上方）
        // 会导致无法检测到直接访问的页面
        if (!request()->getReferer()) {
            return;
        }

        $name = 'X-Session-Auth-ID';
        $value = md5(session()->getId() . customer()->getId());
        if (request()->isMethod('POST')) {
            $post = request()->post($name);
            if (!$post) {
                $post = request()->header($name);
            }
            if ($post && $post !== $value) {
                // 登录信息变化
                $redirectUrl = request()->getReferer() ?: url('common/home');
                if (request()->isAjax()) {
                    return response()->json([
                        'redirect' => $redirectUrl,
                    ], 302);
                }
                return response()->redirectTo($redirectUrl);
            }
        }

        view()->js(aliases('@publicUrl/js/common/session-auth.js'));
        view()->script("window.sessionAuth.init('{$name}', '{$value}')");
    }
}
