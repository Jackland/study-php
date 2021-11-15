<?php

namespace Framework\Controller;

use Framework\Helper\Json;
use Framework\Helper\RegistryAnnotationTrait;
use Framework\View\Enums\ViewWebPosition;
use Registry;
use Symfony\Component\HttpFoundation\JsonResponse;

class Controller
{
    use RegistryAnnotationTrait;

    /**
     * @var Registry $registry
     */
    protected $registry;

    /**
     * Controller constructor.
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function __get($key)
    {
        return $this->registry->get($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->registry->set($key, $value);
    }

    /**
     * 重定向
     * @param $url
     * @param int $status
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect($url, $status = 302)
    {
        $url = $this->url->to($url);

        return $this->response->redirectTo($url, $status);
    }

    /**
     * 重定向到首页
     * seller 到 seller_center，其他到主页
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectHome()
    {
        $customer = customer();
        if ($customer->isLogged() && $customer->isPartner()) {
            return $this->redirect(['customerpartner/seller_center/index']);
        }
        return $this->redirect(['common/home']);
    }

    /**
     * 渲染视图，包含 layout
     * @param string $view 视图名
     * @param array $params 参数
     * @param string|array $layout 其他控制器
     * @return string
     */
    public function render($view, $params = [], $layout = '')
    {
        // oc-dd 直接将返回的信息输出，用于调试
        if (OC_DEBUG && $this->request->get('oc-dd') == 1) {
            dd($view, $params, $layout);
        }

        // oc-debug 模式下追加 header 和 footer，用于显示出 debugBar，在调试 load 的二级页面时非常有用
        $debugMode = OC_DEBUG && $this->request->get('oc-debug') == 1;
        if ($debugMode) {
            $layout = 'buyer';
        }

        if (is_array($layout)) {
            $this->renderControllers($layout, $params);
            $layout = '';
        }
        if (!$layout && $this->request->isAjax()) {
            $content = $this->renderAjax($view, $params);
        } else {
            $content = $this->renderContent($view, $params, $layout);
        }

        $this->response->setOutput($content);

        return $this->response->getContent();
    }

    /**
     * 渲染视图，包含 layout，作用于 ajax 渲染携带 js/css 等
     * @param $view
     * @param array $params
     * @return string
     */
    public function renderAjax($view, $params = [])
    {
        return $this->renderContent($view, $params, app()->config->get('controller.render_ajax_layout', 'ajax'));
    }

    /**
     * 渲染 yzc_front 下的页面
     * @param string $view
     * @param string $layout
     * @param array $sendToFrontJs
     * @param array $sendToTwig
     * @return string
     */
    public function renderFront(string $view, string $layout, $sendToFrontJs = [], $sendToTwig = [])
    {
        $global = app()->config->get('controller.render_front_global', []);
        if (is_callable($global)) {
            $global = call_user_func($global, app());
        }
        $global['page_only'] = $sendToFrontJs;

        // oc-dd 直接将返回的信息输出，用于调试
        if (OC_DEBUG && $this->request->get('oc-dd') == 1) {
            dd($view, $sendToTwig, $sendToFrontJs, $layout);
        }

        $global = Json::encode($global);
        view()->script("window.__APP_GLOBAL__ = {$global}", ViewWebPosition::BODY_BEGIN);

        return $this->renderContent($view, $sendToTwig, $layout);
    }

    /**
     * 渲染视图，不包含 layout
     * @param $view
     * @param array $params
     * @return string
     */
    public function renderPartial($view, $params = [])
    {
        return $this->renderContent($view, $params);
    }

    /**
     * 渲染内容
     * @param $view
     * @param array $params
     * @param string $layout
     * @return string
     */
    public function renderContent($view, $params = [], string $layout = '')
    {
        return $this->load->view($view, $params, $layout);
    }

    /**
     * @param $data
     * @param int $status
     * @param array $headers
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function json($data, $status = 200, $headers = [])
    {
        return $this->response->json($data, $status, $headers);
    }

    /**
     * @param array $data
     * @param string $msg
     * @param int $code
     * @return JsonResponse
     */
    public function jsonSuccess($data = [], $msg = 'Successfully.', $code = 200)
    {
        $json['code'] = $code;
        $json['msg'] = $msg;
        if ($data) {
            $json['data'] = $data;
        }
        return $this->json($json);
    }

    /**
     * @param string $msg
     * @param array $data
     * @param int $code
     * @return JsonResponse
     */
    public function jsonFailed($msg = 'Failed.', $data = [], $code = 0)
    {
        $json['code'] = $code;
        $json['msg'] = $msg;
        if ($data) {
            $json['data'] = $data;
        }
        return $this->json($json);
    }

    /**
     * 渲染控制器
     * @param $controller
     * @param array $params
     * @return string
     */
    public function renderController($controller, $params = [])
    {
        return $this->load->controller($controller, $params);
    }

    /**
     * 渲染一组控制器的数据加到 data 中
     * @param array $controllers
     * @param array $data
     */
    private function renderControllers(array $controllers, &$data)
    {
        foreach ($controllers as $key => $controller) {
            $data[$key] = $this->renderController($controller);
        }
    }
}
