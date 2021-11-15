<?php

namespace Framework\Translation\Twig;

use Twig_Compiler as Compiler;
use Twig_Node as Node;
use Twig_Node_Expression as AbstractExpression;

/**
 * 参考
 * Symfony\Bridge\Twig\Node\TransDefaultDomainNode
 */
class TransDefaultCategoryNode extends Node
{
    public function __construct(AbstractExpression $expr, int $lineno = 0, string $tag = null)
    {
        parent::__construct(['expr' => $expr], [], $lineno, $tag);
    }

    public function compile(Compiler $compiler)
    {
        // noop as this node is just a marker for TranslationDefaultCategoryNodeVisitor
    }
}
