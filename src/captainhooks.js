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

	showModal: false,
	modal: {
		type: '',
		title: '',
		tab: 'usages',
		hook: {},
		live: '',
		liveMode: false
	},
	showPreviewModal: false,
	preview: {
		title: '',
		code: ''
	},

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

	async showTab(tab) {
		if(tab === this.modal.tab) {
			return;
		}

		if('live' === tab) {
			this.modal.liveMode = false;
			this.modal.live = '';
		}

		this.modal.tab = tab;

		await this.$nextTick();

		// highlight code
		hljs.highlightAll();
		// add line numbers
		hljs.initLineNumbersOnLoad();
	},

	async openModal(type, hookIndex) {
		this.modal.type = 'actions' === type ? 'Action' : 'Filter';
		this.modal.title = this.hooks[type][hookIndex].hook;
		this.modal.tab = 'usages';
		this.modal.hook = this.hooks[type][hookIndex];
		this.showModal = true;

		await this.$nextTick();

		hljs.highlightAll();
	},

	toggleHook(type, hookIndex) {
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

		this.showPreviewModal = true;
		this.showModal = false;
		await this.$nextTick();
		document.getElementById('captainhooks-preview-code').innerHTML = responseJson.code;
		this.preview.title = file;
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

	toggleLiveMode() {
		this.modal.liveMode = ! this.modal.liveMode;
		if(this.modal.liveMode) {
			this.activateLiveMode(this.modal.hook);
		} else {
			clearInterval(this.liveModeInterval);
		}
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
		this.modal.live = "";

		clearInterval(this.liveModeInterval);
		let latestLog = await this.updateLatestLogs(hook, 0);
		const self = this;
		this.liveModeInterval = setInterval(async () => {
			latestLog = await self.updateLatestLogs(hook, latestLog);
		}, 5000);
	},

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
			responseJson.reverse().forEach(async log => {
				const text = `<strong>${log.date}</strong><br><div id="captainhooks-log-${log.id}"></div>`;
				this.modal.live = text + this.modal.live;
				await this.$nextTick();
				const ele = document.getElementById(`captainhooks-log-${log.id}`);
				jv(JSON.parse(log.log), ele, {expanded:false});
			});

			return latestDate;
		}
		return latestLog;
	},

	close() {
		this.showModal = false;
		this.modal.tab = '';
		this.modal.live = '';
		clearInterval(this.liveModeInterval);
	},

	closePreview() {
		this.showPreviewModal = false;
		this.showModal = true;
	}

}))

Alpine.start()