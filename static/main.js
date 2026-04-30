function resolveLines(...args)
{
	const lines = [];
	for(const arg of args)
	{
		if(typeof arg === 'number')
		{
			lines.push(arg);
		}

		if(typeof arg === 'string' && arg.match(/^\d+$/))
		{
			lines.push(arg);
		}

		if(typeof arg === 'string' && arg.match(/^\d+-\d+$/))
		{
			const [start, end] = arg.split('-').map(Number);
			const range = Array(1 + -start + end).fill(0).map((_, i) => i + start);
			lines.push(...range);
		}
	}

	return lines.map(x => String(-1 + Number(x)));
}

function flicker(element, timeout)
{
	if(element.classList.contains('flickering')) return;
	element.classList.add('flickering');
	setTimeout(() => element.classList.remove('flickering'), timeout);
}

document.addEventListener('click', event => {
	let target = event.target;
	let href;

	while(target && target.getAttribute && !href)
	{
		href = target.getAttribute('href');

		if(href)
		{
			const url = new URL(href, location);

			if(url.origin !== window.location.origin)
			{
				window.open(href, '_blank', 'noopener,noreferrer');
				event.preventDefault();
				return;
			}

			if(url.hash)
			{
				const element = document.getElementById(url.hash.substr(1));
				if(element) flicker(element, 200);
			}
		}

		target = target.parentNode;
	}
});

document.addEventListener('mouseover', event => {
	const href = event.target.getAttribute('href');

	if(!href)
	{
		return;
	}

	const url = new URL(href, location);

	if(url.pathname === location.pathname)
	{
		return;
	}

	if(url.origin !== location.origin)
	{
		return;
	}

	fetch(href);
});

document.addEventListener('mousedown', event => {
	if(event.buttons !== 0x1)
	{
		return;
	}

	let target = event.target;
	let href;

	while(target && target.getAttribute && !href)
	{
		href = target.getAttribute('href');

		if(href)
		{
			event.preventDefault();
			event.target.click();
			return;
		}

		target = target.parentNode;
	}
});

document.addEventListener('DOMContentLoaded', () => {
	const button = document.getElementById('burgerButton');

	button && button.addEventListener('click', () => {
		const newValue = button.getAttribute('data-open') === 'false' || button.getAttribute('data-open') === '';
		button.setAttribute('data-open', newValue);
		newValue
			? document.body.classList.add('menu-open')
			: document.body.classList.remove('menu-open');
	});

	const codeBlocks = document.querySelectorAll('div.sourceCode');

	for(const codeBlock of codeBlocks)
	{
		const lines = [...codeBlock.querySelectorAll('pre > code > span')];

		const start = codeBlock.getAttribute('data-startfrom') ?? codeBlock.getAttribute('start');
		if(start)
		{
			codeBlock.style.setProperty('--startFrom', start);
		}

		const highlight = codeBlock.getAttribute('data-highlight');
		if(highlight)
		{
			const numbers = resolveLines(...highlight.split(',').map(n => n.trim()));

			for(const number of numbers)
			{
				if(!lines[number])
				{
					continue;
				}

				lines[number].classList.add('highlight');
			}
		}

		const attributes = codeBlock.getAttributeNames();
		const prefix = 'data-highlight-';

		for(const attribute of attributes)
		{
			if(attribute.substring(0, prefix.length) === prefix)
			{
				const suffix = attribute.substring(prefix.length);
				const lineSelectors = codeBlock.getAttribute(attribute).split(',');

				if(!lineSelectors.length) continue;

				const numbers = resolveLines(...lineSelectors.map(n => n.trim()));

				for(const number of numbers)
				{
					if(!lines[number])
					{
						continue;
					}

					lines[number].classList.add(suffix);
				}
			}
		}
	}
});
