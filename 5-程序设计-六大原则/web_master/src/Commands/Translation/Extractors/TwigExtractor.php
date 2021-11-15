<?php

namespace App\Commands\Translation\Extractors;

use App\Components\TwigExtensions\TranslationExtension;
use Framework\Translation\Twig\TranslationNodeVisitor;
use Framework\View\TwigRenderer;
use Psr\Log\NullLogger;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

class TwigExtractor extends BaseExtractor
{
    private $environment;
    private $logger;

    public function __construct()
    {
        $this->environment = app(TwigRenderer::class)->getEnvironment();
        $this->logger = new NullLogger();
    }

    /**
     * @inheritDoc
     */
    public function extract(SplFileInfo $file): array
    {
        $content = file_get_contents($file->getRealPath());
        if (!$this->checkContentExistTransFn($content, ['__(', '__choice('])) {
            return [];
        }

        $env = $this->environment;
        /** @var TranslationExtension $ext */
        $ext = $env->getExtension(TranslationExtension::class);
        $visitor = $ext->getTranslationNodeVisitor();
        $visitor->enable();
        try {
            $env->parse($env->tokenize($content));
        } catch (Throwable $e) {
            $this->logger->warning(sprintf('ParseTwigError: %s, %s', $file->getRealPath(), $e->getMessage()));
            return [];
        }
        $messages = $visitor->getMessages();
        $messages = array_map(function ($item) {
            if ($item[1] === TranslationNodeVisitor::UNDEFINED_CATEGORY) {
                $item[1] = $this->getDefaultCategory();
            }
            return $item;
        }, $messages);
        $visitor->disable();
        return $messages;
    }
}
