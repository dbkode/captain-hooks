<?php
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class CaptainhooksVisitor extends NodeVisitorAbstract
{
    public $actions = [];
    public $filters = [];

    public function enterNode( Node $node ) {
        $prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
        // Check for add_action
        if ( $node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'do_action' ) {
            $args = [];
            foreach ($node->args as $arg) {
                $args[] = $prettyPrinter->prettyPrintExpr( $arg->value );
            }
            // remove ' and "" from first arg
            $hook = trim( $args[0] );
            $hook = str_replace( [ '"', "'" ], '', $hook );
            $code = $prettyPrinter->prettyPrintExpr( $node );
            $code = preg_replace('/,(?!\s)/', ', ', $code );
            $code = preg_replace('/\((?!\s)/', '( ', $code );
            $code = preg_replace('/\[(?!\s)/', '[ ', $code );
            $code = preg_replace('/(?<=\S)\)/', ' )', $code );
            $code = preg_replace('/(?<=\S)\]/', ' ]', $code );
            $code = preg_replace('/\s+/', ' ', $code );
            $this->actions[] = [
                'hook' => $hook,
                'line' => $node->getStartLine(),
                'code' => $code
            ];
        }

        // Check for apply_filters
        if ( $node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'apply_filters' ) {
            $args = [];
            foreach ($node->args as $arg) {
                $args[] = $prettyPrinter->prettyPrintExpr( $arg->value );
            }
            $hook = trim( $args[0] );
            $hook = str_replace( [ '"', "'" ], '', $hook );
            $code = $prettyPrinter->prettyPrintExpr( $node );
            $code = preg_replace('/,(?!\s)/', ', ', $code );
            $code = preg_replace('/\((?!\s)/', '( ', $code );
            $code = preg_replace('/\[(?!\s)/', '[ ', $code );
            $code = preg_replace('/(?<=\S)\)/', ' )', $code );
            $code = preg_replace('/(?<=\S)\]/', ' ]', $code );
            $code = preg_replace('/\s+/', ' ', $code );
            $this->filters[] = [
                'hook' => $hook,
                'line' => $node->getStartLine(),
                'code' => $code,
            ];
        }
    }
}
