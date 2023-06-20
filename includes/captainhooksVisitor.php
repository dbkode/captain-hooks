<?php
namespace CAPTAINHOOKS;

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class CaptainhooksVisitor extends NodeVisitorAbstract
{
    public $actions = [];
    public $filters = [];

    public function enterNode( Node $node ) {
        // Check for add_action
        if ( $node instanceof Node\Expr\FuncCall &&
            $node->name instanceof Node\Name &&
            $node->name->toString() === 'do_action' ) {
            $hook = $this->get_hook( $node );
            $code = $this->get_pretty_code( $node );
            $this->actions[] = [
                'hook' => $hook,
                'line' => $node->getStartLine(),
                'code' => $code
            ];
        }

        // Check for apply_filters
        if ( $node instanceof Node\Expr\FuncCall &&
            $node->name instanceof Node\Name &&
            $node->name->toString() === 'apply_filters' ) {
            $hook = $this->get_hook( $node );
            $code = $this->get_pretty_code( $node );
            $this->filters[] = [
                'hook' => $hook,
                'line' => $node->getStartLine(),
                'code' => $code,
            ];
        }
    }

    private function get_hook( $node ) {
        $prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
        $args = [];
        foreach ($node->args as $arg) {
            $args[] = $prettyPrinter->prettyPrintExpr( $arg->value );
        }
        $hook = trim( $args[0] );
        $hook = str_replace( [ '"', "'" ], '', $hook );

        return $hook;
    }

    private function get_pretty_code( $node ) {
        $prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
        $code = $prettyPrinter->prettyPrintExpr( $node );
        $code = preg_replace('/,(?!\s)/', ', ', $code );
        $code = preg_replace('/\((?!\s)/', '( ', $code );
        $code = preg_replace('/\[(?!\s)/', '[ ', $code );
        $code = preg_replace('/(?<=\S)\)/', ' )', $code );
        $code = preg_replace('/(?<=\S)\]/', ' ]', $code );
        $code = preg_replace('/\s+/', ' ', $code );

        return $code;
    }
}
