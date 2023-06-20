<?php

do_action( 'action_1', 'arg1' );
//apply_filters( 'filter_commented', 1, 10 );
do_action ( 'action_2', 1, 2, 3 );
do_action( 
	'action_3',
	[1, 2, 3]
);
/*
do_action( 'action_commented', 'a', 'b', 'c' );
*/
apply_filters('filter_1',$a,$b,$c);
		
$plugin_dir_name = basename( CAPTAINHOOKS_PLUGIN_DIR );
apply_filters( 'filter_2_' . $plugin_dir_name . '/captainhooks.php', array( $this, 'add_settings_link' ) );

$a = "I like to use do_action() and apply_filters() in my code.";
