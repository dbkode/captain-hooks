<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<div class="wrap captainhooks-wrap">
<h1>
	<?php esc_html_e( 'Captain Hooks', 'captain-hooks' ); ?>
</h1>

<div captainhooks-data="captainhooks">

	<!-- START PAGE -->
	<template captainhooks-if="'start' === page">
		<div>
			<p>
				<?php esc_html_e( 'Captain Hooks is a plugin that allows you to explore all the hooks (actions, filters and shortcodes) that are available in your WordPress installation.', 'captain-hooks' ); ?>
			</p>

			<h2>
				<?php esc_html_e( 'Themes', 'captain-hooks' ); ?>
			</h2>
			<table class="widefat">
				<tbody>
					<?php $alternate = false; ?>
					<?php foreach( $themes as $theme ) : ?>
						<tr class="<?php echo $alternate ? 'alternate' : ''; ?>">
							<td class="row-title">
								<a href="" captainhooks-on:click.prevent="loadHooks('Themes', '<?php echo esc_html( $theme->get( 'Name' ) ); ?>', '<?php echo esc_url( $theme->get_stylesheet_directory() ); ?>')">
									<?php echo esc_html( $theme->get( 'Name' ) ); ?>
								</a>
							</td>
						</tr>
						<?php $alternate = ! $alternate; ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2>
				<?php esc_html_e( 'Plugins', 'captain-hooks' ); ?>
			</h2>
			<table class="widefat">
				<tbody>
					<?php $alternate = false; ?>
					<?php foreach( $plugins as $plugin_path => $plugin ) : ?>
						<tr class="<?php echo $alternate ? 'alternate' : ''; ?>">
							<td class="row-title">
								<a href="" captainhooks-on:click.prevent="loadHooks('Plugins', '<?php echo esc_html( $plugin['Name'] ); ?>', '<?php echo esc_url( WP_PLUGIN_DIR . '/' . dirname( $plugin_path ) ); ?>')">
									<?php echo esc_html( $plugin['Name'] ); ?>
								</a>
							</td>
						</tr>
						<?php $alternate = ! $alternate; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</template>

	<!-- HOOKS PAGE -->
	<template captainhooks-if="'hooks' === page">
		<div>

			<h2>
				<a href="#" captainhooks-on:click.prevent="page = 'start'" style="text-decoration: none;"><?php esc_html_e( 'Home', 'captain-hooks' ); ?></a> &gt;
				<span captainhooks-text="type"></span> &gt; <span captainhooks-text="folder"></span>
				<a href="#" captainhooks-on:click.prevent="refreshHooks" style="text-decoration: none;" title="Reload hooks">
					<span class="dashicons dashicons-image-rotate"></span>
				</a>
			</h2>

			<!-- Tabs -->
			<div class="nav-tab-wrapper">
				<a href="" class="nav-tab" captainhooks-on:click.prevent="tab = 'actions'" captainhooks-bind:class="{'nav-tab-active': 'actions' === tab}">
				<?php esc_html_e( 'Actions', 'captain-hooks' ); ?> (<span captainhooks-text="hooks.actions.length"></span>)
				</a>
				<a href="" class="nav-tab" captainhooks-on:click.prevent="tab = 'filters'" captainhooks-bind:class="{'nav-tab-active': 'filters' === tab}">
				<?php esc_html_e( 'Filters', 'captain-hooks' ); ?> (<span captainhooks-text="hooks.filters.length"></span>)
				</a>
				<a href="" class="nav-tab" captainhooks-on:click.prevent="tab = 'shortcodes'" captainhooks-bind:class="{'nav-tab-active': 'shortcodes' === tab}">
				<?php esc_html_e( 'Shortcodes', 'captain-hooks' ); ?> (<span captainhooks-text="hooks.shortcodes.length"></span>)
				</a>
			</div>

			<!-- Loading Spinner -->
			<template captainhooks-if="'loading' === tab">
				<div class="wrap">
					<h3><?php esc_html_e( 'Analysing theme/plugin files...', 'captain-hooks' ); ?><span class="spinner is-active" style="float: none;"></span></h3>
				</div>
			</template>

			<!-- Actions -->
			<template captainhooks-if="'actions' === tab">
				<div class="wrap">
					<input type="text" captainhooks-model="actions_term" placeholder="<?php esc_html_e( 'Filter actions...', 'captain-hooks' ); ?>" class="regular-text" />
					<br><br>
					<table class="widefat"><tbody>
						<template captainhooks-for="(action, actionIndex) in actions_filtered">
						<tr class="alternate" captainhooks-show="action.visible">
							<td class="row-title">
								<a href="#" captainhooks-on:click.prevent="openModal('actions', actionIndex)" captainhooks-text="action.hook"></a>
							</td>
						</tr>
						</template>
					</tbody></table>
				</div>
			</template>

			<!-- Filters -->
			<template captainhooks-if="'filters' === tab">
				<div class="wrap">
					<input type="text" captainhooks-model="filters_term" placeholder="<?php esc_html_e( 'Filter filters...', 'captain-hooks' ); ?>" class="regular-text" />
					<br><br>
					<table class="widefat"><tbody>
						<template captainhooks-for="(filter, filterIndex) in filters_filtered">
						<tr class="alternate" captainhooks-show="filter.visible">
							<td class="row-title">
								<a href="" captainhooks-on:click.prevent="openModal('filters', filterIndex)" captainhooks-text="filter.hook"></a>
							</td>
						</tr>
						</template>
					</tbody></table>
				</div>
			</template>

			<!-- Shortcodes -->
			<template captainhooks-if="'shortcodes' === tab">
				<div class="wrap">
					<input type="text" captainhooks-model="shortcodes_term" placeholder="<?php esc_html_e( 'Filter shortcodes...', 'captain-hooks' ); ?>" class="regular-text" />
					<br><br>
					<table class="widefat"><tbody>
						<template captainhooks-for="(shortcode, shortcodeIndex) in shortcodes_filtered">
						<tr class="alternate" captainhooks-show="shortcode.visible">
							<td class="row-title">
								[<a href="" captainhooks-on:click.prevent="openModal('shortcodes', shortcodeIndex)" captainhooks-text="shortcode.hook"></a>]
							</td>
						</tr>
						</template>
					</tbody></table>
				</div>
			</template>

		</div>
	</template>

	<!-- MODAL -->
	<template captainhooks-if="showModal">
		<div class="captainhooks-modal">
			<div class="captainhooks-inside">
				<button type="button" class="notice-dismiss" captainhooks-on:click.prevent="close"></button>
				<h2 class="title"><span captainhooks-text="modal.type"></span>: <span captainhooks-text="modal.title"></span></h2>
				<!-- Tabs -->
				<div class="nav-tab-wrapper">
					<a href="#" class="nav-tab" captainhooks-on:click.prevent="showTab('usages')" captainhooks-bind:class="{'nav-tab-active': 'usages' === modal.tab}">
						<?php esc_html_e( 'Usages', 'captain-hooks' ); ?>
					</a>
					<a href="#" class="nav-tab" captainhooks-show="'shortcodes' !== tab" captainhooks-on:click.prevent="showTab('sample')" captainhooks-bind:class="{'nav-tab-active': 'sample' === modal.tab}">
						<?php esc_html_e( 'Sample', 'captain-hooks' ); ?>
					</a>
					<a href="#" class="nav-tab" captainhooks-show="'shortcodes' !== tab" captainhooks-on:click.prevent="showTab('live')" captainhooks-bind:class="{'nav-tab-active': 'live' === modal.tab}">
						<?php esc_html_e( 'Live Mode', 'captain-hooks' ); ?>
					</a>
				</div>

				<!-- Usages -->
				<template captainhooks-if="'usages' === modal.tab">
					<div class="wrap captainhooks-tab">
						<ul>
							<template captainhooks-for="usage in modal.hook.usages">
							<li>
								<span class="dashicons dashicons-arrow-right"></span> <span captainhooks-text="usage.file"></span>: <span captainhooks-text="usage.line_start"></span>
								<a href="#" style="text-decoration: none;" captainhooks-on:click.prevent="preview(usage.file, usage.line_start, usage.line_end)"><span class="dashicons dashicons-media-code"></span></a>
								<br>
								<pre><code class="language-php" captainhooks-html="usage.doc_block ? usage.doc_block + '\n' + usage.code : usage.code"></code></pre>
								<!-- Show usage params -->
								<template captainhooks-if="'shortcodes' === tab && usage.params.length">
									<div>
										<strong><?php esc_html_e( 'Params:', 'captain-hooks' ); ?></strong>
										<ul>
											<template captainhooks-for="param in usage.params">
												<li captainhooks-text="'- ' + param"></li>
											</template>
										</ul>
									</div>
								</template>
							</li>
							</template>
						</ul>
					</div>
				</template>

				<!-- Sample -->
				<template captainhooks-if="'sample' === modal.tab">
					<div class="wrap captainhooks-tab">
						<pre><code class="language-php" captainhooks-html="modal.hook.sample"></code></pre>
					</div>
				</template>

				<!-- Live -->
				<div class="wrap captainhooks-tab" captainhooks-show="'live' === modal.tab">
					<p><?php esc_html_e( "Turn Live Mode On to log all arguments of this hook when it's triggered.", 'captain-hooks' ); ?></p>
					<button captainhooks-on:click.prevent="toggleLiveMode" captainhooks-bind:class="{'button-primary': modal.liveMode, 'button-secondary': ! modal.liveMode}">
						<span captainhooks-text="modal.liveMode ? '<?php esc_html_e( 'Turn Off', 'captain-hooks' ); ?>' : '<?php esc_html_e( 'Turn On', 'captain-hooks' ); ?>'"></span>
					</button>
					<br><br>
					<div id="captainhooks-live" class="captainhooks-live">
						<template captainhooks-for="log in modal.live" captainhooks-bind:key="log.id">
							<div captainhooks-html="log.html"></div>
						</template>
					</div>
				</div>

			</div>
		</div>
	</template>

	<!-- PREVIEW MODAL -->
	<template captainhooks-if="showPreviewModal">
		<div class="captainhooks-modal">
			<div class="captainhooks-inside" captainhooks-on:click.outside="closePreview">
				<button type="button" class="notice-dismiss" captainhooks-on:click.prevent="closePreview"></button>
				<h2 class="title"><?php esc_html_e( 'Preview:', 'captain-hooks' ); ?> <span captainhooks-text="preview.title"></span></h2>
				<pre><code id="captainhooks-preview-code" class="language-php" captainhooks-html="preview.code"></code></pre>
			</div>
		</div>
	</template>

</div>
</div>