document.addEventListener('DOMContentLoaded', () => {
	const de = document.documentElement;
	const variant = sessionStorage.getItem('current-theme-variant');

	if(variant)
	{
		de.classList.add(variant);
	}
	else if(window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches)
	{
		de.classList.add('light');
	}
	else
	{
		de.classList.add('dark');
	}

	document.addEventListener('click', ({ target }) => {
		if(target.hasAttribute('data-toggle-theme-variant'))
		{
			if(de.classList.contains('dark'))
			{
				de.classList.add('light');
				de.classList.remove('dark');
				sessionStorage.setItem('current-theme-variant', 'light');
			}
			else
			{
				de.classList.add('dark');
				de.classList.remove('light');
				sessionStorage.setItem('current-theme-variant', 'dark');
			}
		}
	});
});
