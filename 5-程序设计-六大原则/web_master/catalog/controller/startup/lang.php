<?php

use App\Enums\Common\LangLocaleEnum;
use App\Helper\ModuleHelper;
use Carbon\Carbon;

class ControllerStartupLang extends Controller
{
    public function index()
    {
        $lang = request('lang');
        if (!$lang) {
            $lang = session('lang');
        }
        if (!$lang) {
            $lang = request()->cookieBag->get('lang');
        }
        if ($lang) {
            if (!in_array($lang, LangLocaleEnum::getValues())) {
                $lang = LangLocaleEnum::getDefault();
            }
            session()->set('lang', $lang);
            if ($this->needTrans(request('route', 'common/home'))) {
                trans()->setLocale($lang);
                Carbon::setLocale(LangLocaleEnum::getCarbonLocale($lang));
            }
        }
    }

    /**
     * 是否需要翻译
     * @param string $route
     * @return bool
     */
    private function needTrans(string $route): bool
    {
        $hasTranslatedRoutes = require __DIR__ . '/../../../config/__has_translated_routes.php';
        if (ModuleHelper::isInCatalog()) {
            return array_key_exists($route, $hasTranslatedRoutes['catalog']);
        }
        if (ModuleHelper::isInAdmin()) {
            return array_key_exists($route, $hasTranslatedRoutes['admin']);
        }
        return false;
    }
}
