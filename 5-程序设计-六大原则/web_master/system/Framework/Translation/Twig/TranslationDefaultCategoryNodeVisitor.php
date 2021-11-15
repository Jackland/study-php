<?php

namespace Framework\Translation\Twig;

use Twig_BaseNodeVisitor as AbstractNodeVisitor;
use Twig_Environment as Environment;
use Twig_Node as Node;
use Twig_Node_Block as BlockNode;
use Twig_Node_Expression_Constant as ConstantExpression;
use Twig_Node_Expression_Function as FunctionExpression;
use Twig_Node_Module as ModuleNode;

/**
 * 参考
 * Symfony\Bridge\Twig\NodeVisitor\TranslationDefaultDomainNodeVisitor
 */
class TranslationDefaultCategoryNodeVisitor extends AbstractNodeVisitor
{
    private $scope;

    public function __construct()
    {
        $this->scope = new NodeVisitorScope();
    }

    /**
     * {@inheritdoc}
     */
    protected function doEnterNode(Node $node, Environment $env)
    {
        if ($node instanceof BlockNode || $node instanceof ModuleNode) {
            $this->scope = $this->scope->enter();
        }

        if ($node instanceof TransDefaultCategoryNode) {
            if ($node->getNode('expr') instanceof ConstantExpression) {
                $this->scope->set('category', $node->getNode('expr'));

                return $node;
            }
        }

        if (!$this->scope->has('category')) {
            return $node;
        }

        if (
            $node instanceof FunctionExpression
            && '__' === $node->getAttribute('name')
        ) {
            $arguments = $node->getNode('arguments');
            if (!$arguments->hasNode(0)) {
                return $node;
            }
            if (!$arguments->hasNode(1)) {
                // 第二个参数为参数替换
                $arguments->setNode(1, new \Twig_Node_Expression_Array([], 0));
            }
            if (!$arguments->hasNode(2)) {
                // 第三个参数为 category
                $arguments->setNode(2, $this->scope->get('category'));
            }
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLeaveNode(Node $node, Environment $env)
    {
        if ($node instanceof TransDefaultCategoryNode) {
            return false;
        }

        if (($node instanceof BlockNode || $node instanceof ModuleNode) && $this->scope) {
            $this->scope = $this->scope->leave();
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return -10;
    }
}
