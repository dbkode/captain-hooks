/**
 * CaptainHooks JS.
 *
 * @since 1.0.0
 */

/**
 * Dependencies.
 */
import Alpine from 'alpinejs' 
import './captainhooks.scss'

Alpine.prefix('captainhooks-');

Alpine.data('captainhooks', () => ({
	page: 'start',

	type: '',

	folder: '',
	folderPath: '',

	hooks: {
		actions: [],
		filters: []
	},

	tab: 'actions',

	actions_term: '',
	filters_term: '',

	get actions_filtered() {
		return this.actions_term ? this.hooks.actions.filter(action => action.hook.indexOf(this.actions_term) !== -1) : this.hooks.actions;
	},

	get filters_filtered() {
		return this.filters_term ? this.hooks.filters.filter(filter => filter.hook.indexOf(this.filters_term) !== -1) : this.hooks.filters;
	},

	/**
	 * Init.
	 *
	 * Initializes the AlpineJS instance.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	init() {
		const self = this;
	},

	async loadHooks(type, folder, path) {
		this.type = type;
		this.folder = folder;
		this.folderPath = path;
		this.tab = 'loading';
		this.hooks = {
			actions: [],
			filters: []
		};
		this.page = 'hooks';
		// fetch hooks
		const response = await fetch(`${captainHooksData.rest}/hooks`, {
			method: "POST",
			headers: {
				"X-WP-Nonce": captainHooksData.nonce,
				"Content-Type": "application/json;charset=utf-8"
			},
			body: JSON.stringify({
				path
			})
		});
		const responseJson = await response.json();
		this.hooks = {
			actions: responseJson.actions,
			filters: responseJson.filters
		};
		this.tab = 'actions';
	},

	async refreshHooks() {
		this.tab = 'loading';
		// fetch hooks
		const response = await fetch(`${captainHooksData.rest}/refresh`, {
			method: "POST",
			headers: {
				"X-WP-Nonce": captainHooksData.nonce,
				"Content-Type": "application/json;charset=utf-8"
			},
			body: JSON.stringify({
				path: this.folderPath
			})
		});
		const responseJson = await response.json();
		this.hooks = {
			actions: responseJson.actions,
			filters: responseJson.filters
		};
		this.tab = 'actions';
	},

	toggleHook(type, hookIndex) {
		this.hooks[type][hookIndex].expand = ! this.hooks[type][hookIndex].expand;
	}

}))

Alpine.start()