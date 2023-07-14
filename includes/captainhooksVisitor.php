<?php
/**
 * CaptainhooksVisitor
 *
 * @package Captainhooks
 *
 * @since 1.0.0
 */
namespace CAPTAINHOOKS;

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

/**
 * CaptainhooksVisitor class
 *
 * @since 1.0.0
 */
class CaptainhooksVisitor extends NodeVisitorAbstract
{
    public $actions = [];
    public $filters = [];

    /**
     * Run when entering a node
     *
     * @param Node $node Node
     *
     * @return void
     */
    public function enterNode( Node $node ) {
        // Check for add_action
        if ( $node instanceof Node\Expr\FuncCall &&
            $node->name instanceof Node\Name &&
            $node->name->toString() === 'do_action' ) {
            $hook = $this->get_hook( $node );
            $code = $this->get_pretty_code( $node );
            $params = $this->get_pretty_args( $node );
            $doc_block = $this->get_doc_block( $node );
            $this->actions[] = [
                'hook' => $hook,
                'type' => 'action',
                'line_start' => $node->getStartLine(),
                'line_end' => $node->getEndLine(),
                'code' => htmlspecialchars( $code ),
                'doc_block' => htmlspecialchars( $doc_block ),
                'params' => $params
            ];
        }

        // Check for apply_filters
        if ( $node instanceof Node\Expr\FuncCall &&
            $node->name instanceof Node\Name &&
            $node->name->toString() === 'apply_filters' ) {
            $hook = $this->get_hook( $node );
            $code = $this->get_pretty_code( $node );
            $params = $this->get_pretty_args( $node );
            $doc_block = $this->get_doc_block( $node );
            $this->filters[] = [
                'hook' => $hook,
                'type' => 'filter',
                'line_start' => $node->getStartLine(),
                'line_end' => $node->getEndLine(),
                'code' => htmlspecialchars( $code ),
                'doc_block' => htmlspecialchars( $doc_block ),
                'params' => $params
            ];
        }
    }

    /**
     * Get hook name
     *
     * @param Node $node Node
     *
     * @return string
     */
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

    /**
     * Get pretty version of the code
     *
     * @param Node $node Node
     *
     * @return string
     */
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

    /**
     * Get pretty version of the arguments
     *
     * @param Node $node Node
     *
     * @return array
     */
    private function get_pretty_args( $node ) {
        $prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
        $args = [];
        $count_other = 0;
        foreach ($node->args as $index => $arg) {
            if( 0 === $index ) {
                $args[] = $prettyPrinter->prettyPrintExpr($arg->value);
            } elseif ($arg->value instanceof Node\Expr\Variable) {
                $args[] = '$' . $arg->value->name;
            } elseif ($arg->value instanceof Node\Scalar && isset( $arg->value->value ) && strpos( $arg->value->value, ' ') === false) {
                $args[] = '$' . $arg->value->value;
            } elseif ($arg->value instanceof Node\Expr\Array_) {
                $args[] = '$items';
            } elseif ($arg->value instanceof Node\Expr\ArrayDimFetch) {
                if ($arg->value->dim instanceof Node\Expr\Variable) {
                    $args[] = '$' . $arg->value->dim->name;
                } elseif ($arg->value->dim instanceof Node\Scalar\String_) {
                    $args[] = '$' . $arg->value->dim->value;
                } else {
                    $count_other += 1;
                    $args[] = '$var' . $count_other;
                }
            } else {
                $count_other += 1;
                $args[] = '$var' . $count_other;
            }
        }

        return $args;
    }

    /**
     * Get doc block
     *
     * @param Node $node Node
     *
     * @return string
     */
    private function get_doc_block( $node ) {
        $doc_block = '';
        $doc_comment = $node->getDocComment();
        if ($doc_comment !== null) {
            $doc_block = $doc_comment->getText();
            $doc_block = preg_replace('/\h+/', ' ', $doc_block );
        }

        return $doc_block;
    }

}
