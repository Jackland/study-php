<?php

namespace Framework\View\Twig;

use Twig_Token as Token;
use Twig_TokenParser as AbstractTokenParser;
use Twig_Node_Expression_TempName as TempNameExpression;
use Twig_node as Node;
use Twig_Node_Set as SetNode;
use Twig_Node_Print as PrintNode;

/**
 * 当前的版本不支持，参考官方的实现
 * 当升级twig版本后，需要删除该扩展
 * @link https://twig.symfony.com/doc/1.x/tags/apply.html
 */
final class ApplyTokenParser extends AbstractTokenParser
{
    /**
     * @inheritDoc
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $name = $this->parser->getVarName();

        $ref = new TempNameExpression($name, $lineno);
        $ref->setAttribute('always_defined', true);

        $filter = $this->parser->getExpressionParser()->parseFilterExpressionRaw($ref, $this->getTag());

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse([$this, 'decideApplyEnd'], true);
        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new Node([
            new SetNode(true, $ref, $body, $lineno, $this->getTag()),
            new PrintNode($filter, $lineno, $this->getTag()),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function getTag()
    {
        return 'apply';
    }

    public function decideApplyEnd(Token $token)
    {
        return $token->test('endapply');
    }
}
