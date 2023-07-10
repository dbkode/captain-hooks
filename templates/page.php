<div class="wrap">
<h1>
	<?php esc_html_e( 'Captain Hooks', 'captainhooks' ); ?>
</h1>

<div id="captainhooks-wrapper" captainhooks-data="captainhooks">

	<!-- START PAGE -->
	<template captainhooks-if="'start' === page">
		<div>
			<p>To explore a comprehensive list of hooks associated with a specific item—whether it's a core feature, a theme, or a plugin—simply click on the item of your choice.</p>
			<h2>Core</h2>
			<table class="widefat">
				<tbody>
					<tr class="alternate">
						<td class="row-title">
							<a href="" captainhooks-on:click.prevent="loadHooks('core')">Core</a>
						</td>
					</tr>
				</tbody>
			</table>

			<h2>Themes</h2>
			<table class="widefat">
				<tbody>
					<?php $alternate = false; ?>
					<?php foreach( $themes as $theme ) : ?>
						<tr class="<?php echo $alternate ? 'alternate' : ''; ?>">
							<td class="row-title">
								<a href="" captainhooks-on:click.prevent="loadHooks('Themes', '<?php echo $theme->get( 'Name' ); ?>', '<?php echo $theme->get_stylesheet_directory(); ?>')">
									<?php echo $theme->get( 'Name' ); ?>
								</a>
							</td>
						</tr>
						<?php $alternate = ! $alternate; ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Plugins</h2>
			<table class="widefat">
				<tbody>
					<?php $alternate = false; ?>
					<?php foreach( $plugins as $plugin_path => $plugin ) : ?>
						<tr class="<?php echo $alternate ? 'alternate' : ''; ?>">
							<td class="row-title">
								<a href="" captainhooks-on:click.prevent="loadHooks('Plugins', '<?php echo $plugin['Name']; ?>', '<?php echo WP_PLUGIN_DIR . '/' . dirname( $plugin_path ); ?>')">
									<?php echo $plugin['Name']; ?>
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
				<a href="#" captainhooks-on:click.prevent="page = 'start'" style="text-decoration: none;">Home</a> &gt;
				<span captainhooks-text="type"></span> &gt; <span captainhooks-text="folder"></span>
				<a href="#" captainhooks-on:click.prevent="refreshHooks" style="text-decoration: none;" title="Reload hooks">
					<span class="dashicons dashicons-image-rotate"></span>
				</a>
			</h2>

			<!-- Tabs -->
			<div class="nav-tab-wrapper">
				<a href="" class="nav-tab" captainhooks-on:click.prevent="tab = 'actions'" captainhooks-bind:class="{'nav-tab-active': 'actions' === tab}">
					Actions (<span captainhooks-text="hooks.actions.length"></span>)
				</a>
				<a href="" class="nav-tab" captainhooks-on:click.prevent="tab = 'filters'" captainhooks-bind:class="{'nav-tab-active': 'filters' === tab}">
					Filters (<span captainhooks-text="hooks.filters.length"></span>)
				</a>
			</div>

			<!-- Loading Spinner -->
			<template captainhooks-if="'loading' === tab">
				<div class="wrap">
					<h3>Analysing <span captainhooks-text="type"></span> files...<span class="spinner is-active" style="float: none;"></span></h3>
				</div>
			</template>

			<!-- Actions -->
			<template captainhooks-if="'actions' === tab">
				<div class="wrap">
					<input type="text" captainhooks-model="actions_term" placeholder="Filter actions..." class="regular-text" />
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
					<input type="text" captainhooks-model="filters_term" placeholder="Filter filters..." class="regular-text" />
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

		</div>
	</template>

	<!-- MODAL -->
	<div id="captainhooks-modal" captainhooks-show="showModal">
		<button class="captainhooks-close" captainhooks-on:click.prevent="close">Close</button>
		<div class="captainhooks-inside">
			<button type="button" class="notice-dismiss" captainhooks-on:click.prevent="close"></button>
			<h2 class="title"><span captainhooks-text="modal.type"></span>: <span captainhooks-text="modal.title"></span></h2>
			<!-- Tabs -->
			<div class="nav-tab-wrapper">
				<a href="#" class="nav-tab" captainhooks-on:click.prevent="showTab('usages')" captainhooks-bind:class="{'nav-tab-active': 'usages' === modal.tab}">
					Usages
				</a>
				<a href="#" class="nav-tab" captainhooks-on:click.prevent="showTab('docblock')" captainhooks-bind:class="{'nav-tab-active': 'docblock' === modal.tab}">
					DocBlock
				</a>
				<a href="#" class="nav-tab" captainhooks-on:click.prevent="showTab('sample')" captainhooks-bind:class="{'nav-tab-active': 'sample' === modal.tab}">
					Sample
				</a>
				<a href="#" class="nav-tab" captainhooks-on:click.prevent="showTab('live')" captainhooks-bind:class="{'nav-tab-active': 'live' === modal.tab}">
					Live Mode
				</a>
			</div>

			<!-- Usages -->
			<template captainhooks-if="'usages' === modal.tab">
				<div class="wrap captainhooks-tab">
					<ul>
						<template captainhooks-for="usage in modal.hook.usages">
						<li>
							<span class="dashicons dashicons-arrow-right"></span> <span captainhooks-text="usage.file"></span>: <span captainhooks-text="usage.line_start"></span><br>
							<pre><code class="language-php" captainhooks-text="usage.code"></code></pre>
						</li>
						</template>
					</ul>
				</div>
			</template>

			<!-- DocBlock -->
			<template captainhooks-if="'docblock' === modal.tab">
				<div class="wrap captainhooks-tab">
					<pre><code class="language-php" captainhooks-text="modal.hook.doc_block ? modal.hook.doc_block : 'No DocBlock detected...'"></code></pre>
				</div>
			</template>

			<!-- Sample -->
			<template captainhooks-if="'sample' === modal.tab">
				<div class="wrap captainhooks-tab">
					<pre><code class="language-php" captainhooks-text="modal.hook.sample"></code></pre>
				</div>
			</template>

			<!-- Live -->
			<template captainhooks-if="'live' === modal.tab">
				<div class="wrap captainhooks-tab">
					<p>Turn Live Mode On to log all arguments of this hook when it's triggered.</p>
					<button captainhooks-on:click.prevent="toggleLiveMode" captainhooks-bind:class="{'button-primary': modal.liveMode, 'button-secondary': ! modal.liveMode}">
						<span captainhooks-text="modal.live ? 'Live Mode On' : 'Live Mode Off'"></span>
					</button>
					<br><br>
					<div id="captainhooks-live" class="captainhooks-live" captainhooks-html="modal.live"></div>
				</div>
			</template>

			<!-- <pre><code id="captainhooks-preview-code" class="language-php"></code></pre> -->
		</div>
	</div>

</div>
</div>