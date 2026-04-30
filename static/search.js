const loadSearcher = import('https://cdn.jsdelivr.net/npm/smgen-search/SearchReader.mjs');

document.addEventListener('DOMContentLoaded', async () => {
	const buffers = {};
	const searchInput = document.querySelector('input#search-query');

	searchInput && searchInput.addEventListener('focus', event => {
		const indexUrl = event.target.getAttribute('data-search-index');
		if(!indexUrl)
		{
			return;
		}
		else if(!buffers[indexUrl])
		{
			buffers[indexUrl] = fetch(indexUrl).then(r => r.arrayBuffer());
		}
	});

	if(!searchInput)
	{
		return;
	}

	const baseUrl = document.querySelector('meta[name="smgen-base-url"]')?.getAttribute('content') ?? '';
	const params = new URLSearchParams(location.search);
	const query = params.get('q');
	const { SearchReader } = await loadSearcher;

	const onInput = async ({ target }) => {
		const indexUrl = target.getAttribute('data-search-index') ?? baseUrl + '/search.bin';
		const resultId = target.getAttribute('data-search-results') ?? '#search-results';
		const resultsTag = document.querySelector(resultId);

		if(!indexUrl)
		{
			return;
		}
		else if(!buffers[indexUrl])
		{
			buffers[indexUrl] = fetch(indexUrl).then(r => r.arrayBuffer());
		}

		const reader = new SearchReader(await buffers[indexUrl]);
		const results = reader.search(target.value, 0.5);

		if(!resultsTag)
		{
			throw new Error(`Results tag with selector "${resultId}" not found.`);
		}

		while(resultsTag.firstChild)
		{
			resultsTag.firstChild.remove();
		}

		if(target.value.length < 3)
		{
			return;
		}

		if(!results.length)
		{
			const li = document.createElement('li');
			const a = document.createElement('a');

			a.innerText = 'No results.';

			li.append(a);
			resultsTag.append(li);

			return;
		}

		for(const [result] of results)
		{
			const li = document.createElement('li');
			const a = document.createElement('a');

			a.innerText = result.title;
			a.href = baseUrl + '/' + result.path + '.html';

			li.append(a);
			resultsTag.append(li);
		}
	};

	searchInput.addEventListener('input', onInput);

	if(query)
	{
		searchInput.value = query;
		onInput({ target: searchInput });
	}
});
