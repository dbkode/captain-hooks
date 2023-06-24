<?php
use PHPUnit\Framework\TestCase;

class CaptainhooksTest extends TestCase {

	public function testGetHooks() {
		$sample_file = __DIR__ . '/mocks/sample.php';
		$ch = new \CAPTAINHOOKS\Captainhooks();
		$code = file_get_contents( $sample_file );
		$hooks = $ch->get_hooks( $code );

		// compare the hooks with the expected hooks
		$expected = [
			'actions' => [
				0 => [
					'hook' => 'action_1',
					'line' => 3,
					'code' => "do_action( 'action_1', 'arg1' )"
				],
				1 => [
					'hook' => 'action_2',
					'line' => 5,
					'code' => "do_action( 'action_2', 1, 2, 3 )"
				],
				2 => [
					'hook' => 'action_3',
					'line' => 6,
					'code' => "do_action( 'action_3', [ 1, 2, 3 ] )"
				]
			],
			'filters' => [
				0 => [
					'hook' => 'filter_1',
					'line' => 13,
					'code' => "apply_filters( 'filter_1', \$a, \$b, \$c )"
				],
				1 => [
					'hook' => "filter_2_ . \$plugin_dir_name . /captainhooks.php",
					'line' => 16,
					'code' => "apply_filters( 'filter_2_' . \$plugin_dir_name . '/captainhooks.php', array( \$this, 'add_settings_link' ) )"
				]
			]
		];

		$this->assertEquals( $expected, $hooks );
	}

	public function testReduceAndSortHooks() {
		$input = [
			[ 'hook' => 'hook_5', 'line' => 1, 'code' => "do_action( 'hook_5', 'param5_1' )", 'file' => 'file5_1.php' ],
			[ 'hook' => 'hook_5', 'line' => 2, 'code' => "do_action( 'hook_5', 'param5_2' )", 'file' => 'file5_2.php' ],
			[ 'hook' => 'hook_1', 'line' => 3, 'code' => "do_action( 'hook_1', 'param1_1' )", 'file' => 'file1_1.php' ],
			[ 'hook' => 'hook_3', 'line' => 4, 'code' => "do_action( 'hook_3', 'param3_1' )", 'file' => 'file3_1.php' ],
			[ 'hook' => 'hook_3', 'line' => 5, 'code' => "do_action( 'hook_3', 'param3_2' )", 'file' => 'file3_2.php' ],
			[ 'hook' => 'hook_3', 'line' => 6, 'code' => "do_action( 'hook_3', 'param3_3' )", 'file' => 'file3_3.php' ]
		];

		$expected = [
			[
				'hook' => 'hook_1',
				'usages' => [
					[ 'hook' => 'hook_1', 'line' => 3, 'code' => "do_action( 'hook_1', 'param1_1' )", 'file' => 'file1_1.php' ]
				],
				'expand' => false
			],
			[
				'hook' => 'hook_3',
				'usages' => [
					[ 'hook' => 'hook_3', 'line' => 4, 'code' => "do_action( 'hook_3', 'param3_1' )", 'file' => 'file3_1.php' ],
					[ 'hook' => 'hook_3', 'line' => 5, 'code' => "do_action( 'hook_3', 'param3_2' )", 'file' => 'file3_2.php' ],
					[ 'hook' => 'hook_3', 'line' => 6, 'code' => "do_action( 'hook_3', 'param3_3' )", 'file' => 'file3_3.php' ]
				],
				'expand' => false
			],
			[
				'hook' => 'hook_5',
				'usages' => [
					[ 'hook' => 'hook_5', 'line' => 1, 'code' => "do_action( 'hook_5', 'param5_1' )", 'file' => 'file5_1.php' ],
					[ 'hook' => 'hook_5', 'line' => 2, 'code' => "do_action( 'hook_5', 'param5_2' )", 'file' => 'file5_2.php' ]
				],
				'expand' => false
			]
		];

		$ch = new \CAPTAINHOOKS\Captainhooks();
		$result = $ch->reduce_and_sort( $input );

		$this->assertEquals( $expected, $result );
	}

	public function testGetFolderPHPs() {
		$path = ABSPATH . 'wp-content/plugins/captain-hooks';

		$expected = [
			[ 'full_path' => $path . '/index.php', 'relative_path' => '/index.php' ],
			[ 'full_path' => $path . '/captainhooks.php', 'relative_path' => '/captainhooks.php' ],
			[ 'full_path' => $path . '/includes/captainhooks.php', 'relative_path' => '/includes/captainhooks.php' ],
			[ 'full_path' => $path . '/includes/captainhooksVisitor.php', 'relative_path' => '/includes/captainhooksVisitor.php' ],
			[ 'full_path' => $path . '/templates/page.php', 'relative_path' => '/templates/page.php' ]
		];

		$ch = new \CAPTAINHOOKS\Captainhooks();
		$result = $ch->get_folder_phps( $path );

		$this->assertEquals( $expected, $result );
	}

	public function testGetParams() {

	}

	public function testGuessParamName() {
		
	}

}
