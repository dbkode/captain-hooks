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
import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css';
import jv from 'w-jsonview-tree';

window.hljs = hljs;
require('highlightjs-line-numbers.js');

Alpine.prefix('captainhooks-');

Alpine.data('captainhooks', () => ({
	/**
	 * Current page.
	 *
	 * @since  1.0.0
	 *
	 * @type string
	 */
	page: 'start',

	/**
	 * Current hook type.
	 *
	 * @since  1.0.0
	 *
	 * @type string
	 */
	type: '',

	/**
	 * Current folder.
	 *
	 * @since  1.0.0
	 *
	 * @type string
	 */
	folder: '',

	/**
	 * Current folder path.
	 *
	 * @since  1.0.0
	 *
	 * @type string
	 */
	folderPath: '',

	/**
	 * List of hooks.
	 *
	 * @since  1.0.0
	 *
	 * @type object
	 */
	hooks: {
		actions: [],
		filters: [],
		shortcodes: []
	},

	/**
	 * Current tab.
	 *
	 * @since  1.0.0
	 *
	 * @type string
	 */
	tab: 'actions',

	/**
	 * Search term for actions.
	 *
	 * @since  1.0.0
	 *
	 * @type string
	 */
	actions_term: '',

	/**
	 * Search term for filters.
	 *
	 * @since  1.0.0
	 *
	 * @type string
	 */
	filters_term: '',

	/**
	 * Search term for shortcodes.
	 *
	 * @since  1.0.0
	 *
	 * @type string
	 */
	shortcodes_term: '',

	/**
	 * Is modal visible.
	 *
	 * @since  1.0.0
	 *
	 * @type boolean
	 */
	showModal: false,

	/**
	 * Modal details.
	 *
	 * @since  1.0.0
	 *
	 * @type object
	 */
	modal: {
		type: '',
		title: '',
		tab: 'usages',
		hook: {},
		live: [],
		liveMode: false
	},

	/**
	 * Is preview modal visible.
	 *
	 * @since  1.0.0
	 *
	 * @type boolean
	 */
	showPreviewModal: false,

	/**
	 * Preview modal details.
	 *
	 * @since  1.0.0
	 *
	 * @type object
	 */
	preview: {
		title: '',
		code: ''
	},

	/**
	 * Live mode update interval.
	 *
	 * @since  1.0.0
	 *
	 * @type object
	 */
	liveModeInterval: null,

	/**
	 * Get filtered actions.
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 */
	get actions_filtered() {
		return this.hooks.actions.map(action => {
			action.visible = !this.actions_term || action.hook.indexOf(this.actions_term) !== -1;
			return action;
		});
	},

	/**
	 * Get filtered filters.
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 */
	get filters_filtered() {
		return this.hooks.filters.map(filter => {
			filter.visible = !this.filters_term || filter.hook.indexOf(this.filters_term) !== -1;
			return filter;
		});
	},

	/**
	 * Get filtered shortcodes.
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 */
	get shortcodes_filtered() {
		return this.hooks.shortcodes.map(shortcode => {
			shortcode.visible = !this.shortcodes_term || shortcode.hook.indexOf(this.shortcodes_term) !== -1;
			return shortcode;
		});
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

	/**
	 * Load hooks for a path.
	 *
	 * @since 1.0.0
	 *
	 * @param string type Hook type.
	 * @param string folder Folder name.
	 * @param string path Folder path.
	 * @return void
	 */
	async loadHooks(type, folder, path) {
		this.type = type;
		this.folder = folder;
		this.folderPath = path;
		this.tab = 'loading';
		this.hooks = {
			actions: [],
			filters: [],
			shortcodes: []
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
			filters: responseJson.filters,
			shortcodes: responseJson.shortcodes
		};
		this.tab = 'actions';
	},

	/**
	 * Refresh hooks - skip cache.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
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
			filters: responseJson.filters,
			shortcodes: responseJson.shortcodes
		};
		this.tab = 'actions';
	},

	/**
	 * Show active tab.
	 *
	 * @since 1.0.0
	 *
	 * @param string tab Tab name.
	 * @return void
	 */
	async showTab(tab) {
		if(tab === this.modal.tab) {
			return;
		}

		this.modal.tab = tab;

		await this.$nextTick();

		// highlight code
		hljs.highlightAll();
		// add line numbers
		hljs.initLineNumbersOnLoad();
	},

	/**
	 * Open modal for hook.
	 *
	 * @since 1.0.0
	 *
	 * @param string type Hook type.
	 * @param int hookIndex Hook index.
	 * @return void
	 */
	async openModal(type, hookIndex) {
		this.modal.type = 'actions' === type ? 'Action' : 'filters' === type ? 'Filter' : 'Shortcode';
		this.modal.title = this.hooks[type][hookIndex].hook;
		this.modal.tab = 'usages';
		this.modal.hook = this.hooks[type][hookIndex];
		this.showModal = true;

		await this.$nextTick();

		hljs.highlightAll();
	},

	/**
	 * Open preview modal for file.
	 *
	 * @since 1.0.0
	 *
	 * @param string file File path.
	 * @param int line_start Start line.
	 * @param int line_end End line.
	 * @return void
	 */
	async preview(file, line_start, line_end) {
		const response = await fetch(`${captainHooksData.rest}/preview`, {
			method: "POST",
			headers: {
				"X-WP-Nonce": captainHooksData.nonce,
				"Content-Type": "application/json;charset=utf-8"
			},
			body: JSON.stringify({
				path: this.folderPath,
				file
			})
		});
		const responseJson = await response.json();

		this.showPreviewModal = true;
		this.showModal = false;
		await this.$nextTick();
		document.getElementById('captainhooks-preview-code').innerHTML = responseJson.code;
		this.preview.title = file;
		this.highlightCode(line_start, line_end);
	},

	/**
	 * View code sample and highlight code.
	 *
	 * @since 1.0.0
	 *
	 * @param string sample Sample code.
	 * @return void
	 */
	viewSample(sample) {
		this.showPreview = true;
		document.getElementById('captainhooks-preview-code').textContent = sample;
		this.highlightCode(false, false);
	},

	/**
	 * View docblock and highlight code.
	 *
	 * @since 1.0.0
	 *
	 * @param string docBlock Docblock.
	 * @return void
	 */
	viewDocBlock(docBlock) {
		this.showPreview = true;
		document.getElementById('captainhooks-preview-code').textContent = docBlock;
		this.highlightCode(false, false);
	},

	/**
	 * Highlight code.
	 *
	 * @since 1.0.0
	 *
	 * @param int line_start Start line.
	 * @param int line_end End line.
	 * @return void
	 */
	async highlightCode(line_start, line_end) {
		// highlight code
		hljs.highlightAll();

		// add line numbers
		hljs.initLineNumbersOnLoad();
		
		if(! line_start ) {
			return;
		}
		await this.$nextTick();

		// highlight lines
		for(let l = line_start; l <= line_end; l++) {
			const linesEl = document.querySelectorAll(`.hljs-ln-line[data-line-number="${l}"]`);
			linesEl.forEach(lineEl => {
				lineEl.classList.add('captainhooks-highlight');
			});
		}

		// scroll to line
		const lineEl = document.querySelector(`.hljs-ln-line[data-line-number="${line_start}"]`);
		lineEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
	},

	/**
	 * Toggle live mode.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	toggleLiveMode() {
		this.modal.liveMode = ! this.modal.liveMode;
		if(this.modal.liveMode) {
			this.activateLiveMode(this.modal.hook);
		} else {
			clearInterval(this.liveModeInterval);
		}
	},

	/**
	 * Activate live mode.
	 *
	 * @since 1.0.0
	 *
	 * @param object hook Hook to activate live mode.
	 * @return void
	 */
	async activateLiveMode(hook) {
		await fetch(`${captainHooksData.rest}/livemode`, {
			method: "POST",
			headers: {
				"X-WP-Nonce": captainHooksData.nonce,
				"Content-Type": "application/json;charset=utf-8"
			},
			body: JSON.stringify({
				hook: hook.hook,
				type: hook.type,
				num_args: hook.num_args
			})
		});

		this.showPreview = true;
		this.modal.live = [];

		clearInterval(this.liveModeInterval);
		let latestLog = await this.updateLatestLogs(hook, 0);
		const self = this;
		this.liveModeInterval = setInterval(async () => {
			latestLog = await self.updateLatestLogs(hook, latestLog);
		}, 5000);
	},

	/**
	 * Update latest logs.
	 *
	 * @since 1.0.0
	 *
	 * @param object hook Hook to activate live mode.
	 * @param string latestLog Latest log date.
	 * @return void
	 */
	async updateLatestLogs(hook, latestLog) {
		const response = await fetch(`${captainHooksData.rest}/livemode/logs`, {
			method: "POST",
			headers: {
				"X-WP-Nonce": captainHooksData.nonce,
				"Content-Type": "application/json;charset=utf-8"
			},
			body: JSON.stringify({
				hook: hook.hook,
				type: hook.type,
				latest: latestLog
			})
		});
		const responseJson = await response.json();
		if(responseJson.length) {
			const latestDate = responseJson[0].date;
			const logs = responseJson.reverse();
			for(let i = 0; i < logs.length; i++) {
				let log = logs[i];
				const html = `<strong>${log.date}: ${log.hook}</strong><br><div id="captainhooks-log-${log.id}" class="captainhooks-live-log"></div>`;
				log.html = html;
				this.modal.live.unshift(log);
				await this.$nextTick();
				const ele = document.getElementById(`captainhooks-log-${log.id}`);
				jv(JSON.parse(log.log), ele, {expanded:false});
			};

			return latestDate;
		}
		return latestLog;
	},

	/**
	 * Close modal.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	close() {
		this.showModal = false;
		this.modal.tab = '';
		this.modal.live = [];
		clearInterval(this.liveModeInterval);
	},

	/**
	 * Close preview modal.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	closePreview() {
		this.showPreviewModal = false;
		this.showModal = true;
	}

}))

Alpine.start()