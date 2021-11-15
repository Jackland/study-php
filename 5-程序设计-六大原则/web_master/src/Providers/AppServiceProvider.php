<?php

namespace App\Providers;

use App\Components\View\LayoutFactory;
use App\Enums\Common\DatabaseConnection;
use App\Enums\FeeOrder\FeeOrderOrderType;
use App\Helper\ModuleHelper;
use App\Models\Order\Order;
use App\Models\Rma\YzcRmaOrder;
use App\Models\SalesOrder\CustomerSalesOrder;
use Framework\Debug\Collector\twig\TwigDataNotUse;
use Framework\DI\ServiceProvider;
use Framework\Foundation\Console\Kernel as FrameworkConsoleKernel;
use Framework\Model\Eloquent\Relations\JoinerFactory;
use Framework\Model\EloquentModel;
use Framework\Session\Session;
use Framework\View\TwigRenderer;
use Framework\View\ViewFactory;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\Eloquent\Relations\Relation;
use Sofa\Eloquence\Builder;
use Sofa\Eloquence\Searchable\ParserFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @inheritDoc
     */
    public function boot()
    {
        $this->bootEvents();
    }

    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->solvingSession();
        $this->solvingView();
        $this->solveEloquentModel();
        $this->solveConsole();
    }

    private function solvingSession()
    {
        $this->app->resolving('session', function ($session) {
            if (configDB('session_autostart')) {
                $sessionName = configDB('session_name');
                if (isset($_COOKIE[$sessionName])) {
                    $session_id = $_COOKIE[$sessionName];
                } else {
                    $session_id = '';
                }
                /** @var Session $session */
                $session->start($session_id);
            }
        });
    }

    private function solvingView()
    {
        $this->app->resolving('view', function (ViewFactory $view) {
            $view->share([
                'app_version' => APP_VERSION,
                'customer' => customer(),
            ]);

            if (ModuleHelper::isInCatalog()) {
                $view->setDefaultLayoutData(LayoutFactory::register());
            }
        });

        $this->app->resolving('view.renderer.twig', function (TwigRenderer $renderer) {
            if (OC_DEBUG && debugBar()->isEnabled()) {
                $environment = $renderer->getEnvironment();
                // 开启 profile 存在性能损耗
                $twigProfiler = new \Twig_Profiler_Profile();
                $environment->addExtension(new \Twig_Extension_Profiler($twigProfiler));
                // 收集 twig 中未使用的 data
                $twigDataNotUsed = new TwigDataNotUse();
                $renderer->setTwigDataNotUse($twigDataNotUsed);
                debugBar()->addTwigEnvCollector($twigProfiler, $twigDataNotUsed);
            }
        });
    }

    private function solveEloquentModel()
    {
        // Eloquence
        // @see Sofa\Eloquence\BaseServiceProvider
        Builder::setJoinerFactory(new JoinerFactory());
        Builder::setParserFactory(new ParserFactory());
        // morphMap
        Relation::morphMap([
            (FeeOrderOrderType::getViewItemsAlias())[FeeOrderOrderType::SALES] => CustomerSalesOrder::class,
            (FeeOrderOrderType::getViewItemsAlias())[FeeOrderOrderType::RMA] => YzcRmaOrder::class,
            (FeeOrderOrderType::getViewItemsAlias())[FeeOrderOrderType::ORDER] => Order::class,
        ]);
        // write/read
        EloquentModel::setReadWriteConnections(DatabaseConnection::READ, DatabaseConnection::WRITE);
    }

    private function solveConsole()
    {
        $this->app->alias(ConsoleKernelContract::class, 'console');
        $this->app->alias(ConsoleKernelContract::class, FrameworkConsoleKernel::class);
        $this->app->singleton('console.artisan', function ($app) {
            return $app->get(FrameworkConsoleKernel::class)->getArtisan();
        });
    }

    private function bootEvents()
    {
        /** @var DispatcherContract $dispatcher */
        $dispatcher = $this->app->get('events');
        foreach ($this->app->config->get('events.listen', []) as $event => $listeners) {
            foreach (array_unique($listeners) as $listener) {
                $dispatcher->listen($event, $listener);
            }
        }

        foreach ($this->app->config->get('events.subscribe', []) as $subscriber) {
            $dispatcher->subscribe($subscriber);
        }
    }
}
