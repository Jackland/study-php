<?php

namespace Framework\Translation\Twig;

use Twig_Environment as Environment;
use Twig_Node_Expression_Function as FunctionExpression;
use Twig_Node as Node;
use Twig_BaseNodeVisitor as AbstractNodeVisitor;

/**
 * 参考
 * Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor
 */
class TranslationNodeVisitor extends AbstractNodeVisitor
{
    const UNDEFINED_CATEGORY = '____undefined';

    private $enabled = false;
    private $messages = [
        // [message, category]
    ];

    public function enable()
    {
        $this->enabled = true;
        $this->messages = [];
    }

    public function disable()
    {
        $this->enabled = false;
        $this->messages = [];
    }

    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * {@inheritdoc}
     */
    protected function doEnterNode(Node $node, Environment $env)
    {
        if (!$this->enabled) {
            return $node;
        }

        if (
            $node instanceof FunctionExpression
            && '__' === $node->getAttribute('name')
            && $node->hasNode('arguments')
        ) {
            $arguments = $node->getNode('arguments');
            if (!$arguments->hasNode(0)) {
                return $node;
            }
            $this->messages[] = [
                $arguments->getNode(0)->getAttribute('value'),
                !$arguments->hasNode(2) ? self::UNDEFINED_CATEGORY : $arguments->getNode(2)->getAttribute('value'),
            ];
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLeaveNode(Node $node, Environment $env)
    {
        return $node;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 0;
    }
}
