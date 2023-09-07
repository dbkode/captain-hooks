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
    public $shortcodes = [];

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

        // Check for shortcodes
        if ( $node instanceof Node\Expr\FuncCall &&
            $node->name instanceof Node\Name &&
            $node->name->toString() === 'add_shortcode' ) {
            $hook = $this->get_hook( $node );
            $code = $this->get_pretty_code( $node );
            $params = $this->get_pretty_args( $node );
            $doc_block = $this->get_doc_block( $node );
            // find shortcode index with same hook
            $index = -1;
            foreach( $this->shortcodes as $key => $shortcode ) {
                if( $shortcode['hook'] === $hook ) {
                    $index = $key;
                    break;
                }
            }
            // if shortcode found, add line_start, line_end, code, doc_block
            if( $index > -1 ) {
                $this->shortcodes[ $index ]['line_start'] = $node->getStartLine();
                $this->shortcodes[ $index ]['line_end'] = $node->getEndLine();
                $this->shortcodes[ $index ]['code'] = htmlspecialchars( $code );
                $this->shortcodes[ $index ]['doc_block'] = htmlspecialchars( $doc_block );
            } else {
                $this->shortcodes[] = [
                    'hook' => $hook,
                    'type' => 'shortcode',
                    'line_start' => $node->getStartLine(),
                    'line_end' => $node->getEndLine(),
                    'code' => htmlspecialchars( $code ),
                    'doc_block' => htmlspecialchars( $doc_block ),
                    'params' => array()
                ];
            }
        }

        // Check for shortcode_atts
        if ( $node instanceof Node\Expr\FuncCall &&
            $node->name instanceof Node\Name &&
            $node->name->toString() === 'shortcode_atts' ) {
            $prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
            $hook = $this->get_hook( $node, 2 );
            $code = $this->get_pretty_code( $node );
            // get params from array of first argument
            $params = [];
            if( $node->args[0]->value instanceof Node\Expr\Array_ ) {
                foreach( $node->args[0]->value->items as $item ) {
                    $params[] = str_replace( "'", "", $prettyPrinter->prettyPrintExpr( $item->key ) );
                }
            }
            $doc_block = $this->get_doc_block( $node );
            if( ! empty( $hook ) ) {
                // find shortcode index with same hook
                $index = -1;
                foreach( $this->shortcodes as $key => $shortcode ) {
                    if( $shortcode['hook'] === $hook ) {
                        $index = $key;
                        break;
                    }
                }
                // if shortcode found, add params
                if( $index > -1 ) {
                    $this->shortcodes[ $index ]['params'] = array_merge( $this->shortcodes[ $index ]['params'], $params );
                } else {
                    $this->shortcodes[] = [
                        'hook' => $hook,
                        'type' => 'shortcode',
                        'line_start' => $node->getStartLine(),
                        'line_end' => $node->getEndLine(),
                        'code' => htmlspecialchars( $code ),
                        'doc_block' => htmlspecialchars( $doc_block ),
                        'params' => $params
                    ];
                }
            }
        }
    }

    /**
     * Get hook name
     *
     * @param Node $node Node
     *
     * @return string
     */
    private function get_hook( $node, $index = 0 ) {
        $prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
        $args = [];
        foreach ($node->args as $arg) {
            $args[] = $prettyPrinter->prettyPrintExpr( $arg->value );
        }
        $hook = trim( $args[$index] );
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
