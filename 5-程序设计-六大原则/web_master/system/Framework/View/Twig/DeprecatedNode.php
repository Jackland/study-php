<?php

namespace Framework\View\Twig;

use Twig_Compiler as Compiler;
use Twig_Node as Node;
use Twig_Node_Expression as AbstractExpression;

class DeprecatedNode extends Node
{
    public function __construct(AbstractExpression $expr, int $lineno = 0, string $tag = null)
    {
        parent::__construct(['expr' => $expr], [], $lineno, $tag);
    }

    public function compile(Compiler $compiler)
    {
        // 无需输出
    }
}
