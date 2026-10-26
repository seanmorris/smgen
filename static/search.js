document.addEventListener('DOMContentLoaded', () => {
	const input = document.querySelector('input#search-query');
	if(!input) return;

	const resultSelector = input.getAttribute('data-search-results') || '#search-results';
	const resultsElement = document.querySelector(resultSelector);
	const indexUrl = input.getAttribute('data-search-index');
	const readerUrl = input.getAttribute('data-search-reader');
	const baseUrl = document.querySelector('meta[name="smgen-base-url"]')?.content.replace(/\/$/, '') || '';
	let readerPromise;

	/** Convert one extensionless search result path to its published page URL. */
	const resultUrl = resultPath => {
		const path = String(resultPath).replace(/^\//, '');
		if(path === 'index') return `${baseUrl}/`;
		if(path.endsWith('/index')) return `${baseUrl}/${path.slice(0, -'index'.length)}`;
		return `${baseUrl}/${path}.html`;
	};

	const clearResults = () => {
		resultsElement?.replaceChildren();
	};

	const showMessage = message => {
		if(!resultsElement) return;
		const item = document.createElement('li');
		const label = document.createElement('span');
		label.textContent = message;
		item.append(label);
		resultsElement.replaceChildren(item);
	};

	const loadReader = () => {
		if(!readerPromise)
		{
			readerPromise = Promise.all([
				import(readerUrl),
				fetch(indexUrl).then(response => {
					if(!response.ok) throw new Error(`Search index returned ${response.status}.`);
					return response.arrayBuffer();
				}),
			]).then(([module, buffer]) => new module.SearchReader(buffer));
		}
		return readerPromise;
	};

	const runSearch = async () => {
		const query = input.value.trim();
		if(query.length < 3)
		{
			clearResults();
			return;
		}

		try
		{
			const reader = await loadReader();
			const results = reader.search(query, 0.5);
			if(!results.length)
			{
				showMessage('No results.');
				return;
			}

			const items = results.map(([result]) => {
				const item = document.createElement('li');
				const link = document.createElement('a');
				link.textContent = result.title;
				link.href = resultUrl(result.path);
				item.append(link);
				return item;
			});
			resultsElement?.replaceChildren(...items);
		}
		catch(error)
		{
			console.error('Unable to load search.', error);
			showMessage('Search unavailable.');
		}
	};

	if(indexUrl && readerUrl)
	{
		input.addEventListener('focus', () => { loadReader().catch(() => {}); }, { once: true });
		input.addEventListener('input', runSearch);
	}

	const query = new URLSearchParams(location.search).get('q');
	if(query)
	{
		input.value = query;
		runSearch();
	}
});
