document.addEventListener('click', event => {
	let target = event.target;

	while(target && target.getAttribute)
	{
		if(target.tagName === 'UL')
		{
			event.stopPropagation();
			return;
		}

		const idPath = target.getAttribute('data-id-path');

		if(idPath)
		{
			const isOpen = !target.hasAttribute('open');
			localStorage.setItem('openMenu-' + idPath, JSON.stringify(isOpen));
			return;
		}

		target = target.parentNode;
	}
});

document.addEventListener('DOMContentLoaded', () => {
	const summaries = document.querySelectorAll('details[data-id-path]');

	for(const summary of summaries)
	{
		const idPath = summary.getAttribute('data-id-path');
		const isOpen = JSON.parse(localStorage.getItem('openMenu-' + idPath));

		if(isOpen)
		{
			summary.setAttribute('open', true);
		}
		else
		{
			summary.removeAttribute('open');
		}
	}
});
