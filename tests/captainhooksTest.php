<?php
use PHPUnit\Framework\TestCase;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class CaptainhooksTest extends TestCase {

	public function testPrepareCode() {
		// sample file path from mocks dir
		$sample_file = __DIR__ . '/mocks/sample.php';
		$ch = new \CAPTAINHOOKS\Captainhooks();
		$prepared = $ch->prepare_code( $sample_file );

		// compare the prepared code with the expected code
		$expected = file_get_contents( __DIR__ . '/mocks/prepared.txt' );

		$this->assertEquals( $expected, $prepared );
	}

	public function testGetFnHooks() {
		$tests = [
			[
				'input' => "*13*apply_filters( 'filter_2_' . \$plugin_dir_name . '/captainhooks.php', array( \$this, 'add_settings_link' ) );",
				'expected' => [[
					'hook' => 'filter_2_ . $plugin_dir_name . /captainhooks.php',
					'line' => 13,
					'code' => "apply_filters( 'filter_2_' . \$plugin_dir_name . '/captainhooks.php', array( \$this, 'add_settings_link' ) )"
				]]
			],
			[
				'input' => "*5*apply_filters('filter_1',\$a,\$b,\$c); echo 'hello';",
				'expected' => [[
					'hook' => 'filter_1',
					'line' => 5,
					'code' => "apply_filters( 'filter_1', \$a, \$b, \$c )"
				]]
			],
			[
				'input' => "*7*apply_filters('filter_1', 1, 2, 3);",
				'expected' => [[
					'hook' => 'filter_1',
					'line' => 7,
					'code' => "apply_filters( 'filter_1', 1, 2, 3 )"
				]]
			],
			[
				'input' => "*10*apply_filters( 'filter_1', 'abc' );",
				'expected' => [[
					'hook' => 'filter_1',
					'line' => 10,
					'code' => "apply_filters( 'filter_1', 'abc' )"
				]]
			],
			[
				'input' => "*153*apply_filters ( 'filter_4' );",
				'expected' => [[
					'hook' => 'filter_4',
					'line' => 153,
					'code' => "apply_filters( 'filter_4' )"
				]]
			],
			[
				'input' => "*1010*apply_filters( 'filter_1' , 1, 2, 3); echo 'a'; *1230*apply_filters ('filter_4');",
				'expected' => [[
					'hook' => 'filter_1',
					'line' => 1010,
					'code' => "apply_filters( 'filter_1', 1, 2, 3 )"
				],[
					'hook' => 'filter_4',
					'line' => 1230,
					'code' => "apply_filters( 'filter_4' )"
				]]
			],
			[
				'input' => "*2*apply_filters ( 'filter_4', [1, 2, 3] );",
				'expected' => [[
					'hook' => 'filter_4',
					'line' => 2,
					'code' => "apply_filters( 'filter_4', [ 1, 2, 3 ] )"
				]]
			],
			[
				'input' => "*6*apply_filters( 'rsssl_daily_cron'); }  add_action( 'rsssl_every_five_minutes_hook', 'rsssl_every_five_minutes_cron' );",
				'expected' => [[
					'hook' => 'rsssl_daily_cron',
					'line' => 6,
					'code' => "apply_filters( 'rsssl_daily_cron' )"
				]]
			]
		];

		$ch = new \CAPTAINHOOKS\Captainhooks();
		foreach( $tests as $test ) {
			$result = $ch->get_fn_hooks( 'apply_filters', $test['input'] );
			$this->assertEquals( $test['expected'], $result, $test['input'] );
		}
	}

	public function testGetHooks() {
		$sample_file = __DIR__ . '/mocks/sample.php';
		$ch = new \CAPTAINHOOKS\Captainhooks();
		$code = $ch->prepare_code( $sample_file );
		$hooks = [
			'actions' => $ch->get_actions( $code ),
			'filters' => $ch->get_filters( $code )
		];

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

	public function testCompareWithPhpParser() {
		$sample_file = __DIR__ . '/mocks/sample.php';
		$ch = new \CAPTAINHOOKS\Captainhooks();
		$code = $ch->prepare_code( $sample_file );
		$hooks = [
			'actions' => $ch->get_actions( $code ),
			'filters' => $ch->get_filters( $code )
		];

		$expected = $this->parse( __DIR__ . '/mocks/sample.php' );

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
			[ 'full_path' => $path . '/includes/autoload.php', 'relative_path' => '/includes/autoload.php' ],
			[ 'full_path' => $path . '/includes/captainhooks.php', 'relative_path' => '/includes/captainhooks.php' ],
			[ 'full_path' => $path . '/templates/page.php', 'relative_path' => '/templates/page.php' ]
		];

		$ch = new \CAPTAINHOOKS\Captainhooks();
		$result = $ch->get_folder_phps( $path );

		$this->assertEquals( $expected, $result );
	}

	public function testGetPathHooks() {
		$ch = new \CAPTAINHOOKS\Captainhooks();
		$path = ABSPATH . 'wp-content/plugins/really-simple-ssl';
		$files = $ch->get_folder_phps( $path );

		// Expected
		$expected_actions = [];
		$expected_filters = [];
		foreach( $files as $file ) {
			$hooks = $this->parse( $file['full_path'] );
			$actions = array_map( function( $action ) use ( $file ) {
				$action['file'] = $file['relative_path'];
				return $action;
			}, $hooks['actions'] );
			$expected_actions = array_merge( $expected_actions, $actions );
			$filters = array_map( function( $filter ) use ( $file ) {
				$filter['file'] = $file['relative_path'];
				return $filter;
			}, $hooks['filters'] );
			$expected_filters = array_merge( $expected_filters, $filters );
		}
		$expected_actions = $ch->reduce_and_sort( $expected_actions );
		$expected_filters = $ch->reduce_and_sort( $expected_filters );
		$expected = [
			'actions' => $expected_actions,
			'filters' => $expected_filters
		];

		// actual
		$result = $ch->get_path_hooks( $path );

		$this->assertEquals( $expected, $result );
	}

	private function parse( $path ) {
		require_once __DIR__ . '/captainhooksVisitor.php';

		$code = file_get_contents( $path );
		$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );
		$stmts = $parser->parse( $code );
		$visitor = new CaptainhooksVisitor;
		$traverser = new NodeTraverser;
		$traverser->addVisitor( $visitor );
		$traverser->traverse( $stmts );

		return [
			'actions' => $visitor->actions,
			'filters' => $visitor->filters
		];
	}

}
