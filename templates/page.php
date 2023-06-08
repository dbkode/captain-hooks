<h1>
	<?php esc_html_e( 'Captain Hooks', 'captainhooks' ); ?>
</h1>

<div id="captainhooks-wrapper" captainhooks-data="captainhooks">

	<!-- START PAGE -->
	<template captainhooks-if="'start' === page">
		<div>
			<h2>Core</h2>
			<ul>
				<li>
					<a href="" captainhooks-on:click.prevent="loadHooks('core')">
						Core
					</a>
				</li>
			</ul>
			<h2>Themes</h2>
			<ul>
			<?php foreach( $themes as $theme ) : ?>
				<li>
					<a href="" captainhooks-on:click.prevent="loadHooks('Themes', '<?php echo $theme->get( 'Name' ); ?>', '<?php echo $theme->get_stylesheet_directory(); ?>')">
						<?php echo $theme->get( 'Name' ); ?>
					</a>
				</li>
			<?php endforeach; ?>

			<h2>Plugins</h2>
			<ul>
			<?php foreach( $plugins as $plugin_path => $plugin ) : ?>
				<li>
					<a href="" captainhooks-on:click.prevent="loadHooks('Plugins', '<?php echo $plugin['Name']; ?>', '<?php echo WP_PLUGIN_DIR . '/' . dirname( $plugin_path ); ?>')">
						<?php echo $plugin['Name']; ?>
					</a>
				</li>
			<?php endforeach; ?>
			</ul>
		</div>
	</template>

	<!-- HOOKS PAGE -->
	<template captainhooks-if="'hooks' === page">
		<div>

			<h2>
				<span captainhooks-text="type"></span> &gt; <span captainhooks-text="folder"></span>
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

			<!-- Actions -->
			<template captainhooks-if="'actions' === tab">
				<div>
					<h3>Actions</h3>
					<input type="text" captainhooks-model="actions_term" placeholder="Filter actions..." />
					<ul>
						<template captainhooks-for="(action, actionIndex) in actions_filtered">
							<li>
								<a href="" captainhooks-on:click.prevent="toggleHook('actions', actionIndex)" captainhooks-text="action.hook"></a>
								<div captainhooks-show="action.show">
									<ul>
										<template captainhooks-for="usage in action.usages">
											<li>
												<span captainhooks-text="usage.file_path"></span>:<span captainhooks-text="usage.line_number"></span> <span captainhooks-text="usage.line"></span>
											</li>
										</template>
									</ul>
								</div>
							</li>
						</template>
					</ul>
				</div>
			</template>

			<!-- Filters -->
			<template captainhooks-if="'filters' === tab">
				<div>
					<h3>Filters</h3>
					<input type="text" captainhooks-model="filters_term" placeholder="Filter filters..." />
					<ul>
						<template captainhooks-for="(filter, filterIndex) in filters_filtered">
							<li>
							<a href="" captainhooks-on:click.prevent="toggleHook('filters', filterIndex)" captainhooks-text="filter.hook"></a>
								<div captainhooks-show="filter.show">
									<ul>
										<template captainhooks-for="usage in filter.usages">
											<li>
												<span captainhooks-text="usage.file_path"></span>: <span captainhooks-text="usage.line"></span>
											</li>
										</template>
									</ul>
								</div>
							</li>
						</template>
					</ul>
				</div>
			</template>

		</div>
	</template>

</div>