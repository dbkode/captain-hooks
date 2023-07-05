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

window.hljs = hljs;
require('highlightjs-line-numbers.js');

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

	showPreview: false,

	liveModeInterval: null,

	get actions_filtered() {
		return this.hooks.actions.map(action => {
			action.visible = !this.actions_term || action.hook.indexOf(this.actions_term) !== -1;
			return action;
		});
	},

	get filters_filtered() {
		return this.hooks.filters.map(filter => {
			filter.visible = !this.filters_term || filter.hook.indexOf(this.filters_term) !== -1;
			return filter;
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
		console.log(hookIndex);
		this.hooks[type][hookIndex].expand = ! this.hooks[type][hookIndex].expand;
	},

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

		this.showPreview = true;
		document.getElementById('captainhooks-preview-code').textContent = responseJson.code;
		this.highlightCode(line_start, line_end);
	},

	viewSample(sample) {
		this.showPreview = true;
		document.getElementById('captainhooks-preview-code').textContent = sample;
		this.highlightCode(false, false);
	},

	viewDocBlock(docBlock) {
		this.showPreview = true;
		document.getElementById('captainhooks-preview-code').textContent = docBlock;
		this.highlightCode(false, false);
	},

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
		document.getElementById('captainhooks-preview-code').textContent = "";

		clearInterval(this.liveModeInterval);
		let latestLog = 0;
		this.liveModeInterval = setInterval(async () => {
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
				latestLog = responseJson[0].date;

				let text = '';
				responseJson.forEach(log => {
					text += `> ${log.date}\n`;
					text += JSON.stringify(log.log, null, 2);
					text += "\n\n";
				});
				document.getElementById('captainhooks-preview-code').textContent = text + document.getElementById('captainhooks-preview-code').textContent;
			}
			console.log(responseJson);
		}, 5000);
	},

	close() {
		this.showPreview = false;
		clearInterval(this.liveModeInterval);
	}

}))

Alpine.start()