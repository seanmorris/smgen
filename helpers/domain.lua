local image_count = 0

function Image(el)
	local base_url = os.getenv("BASE_URL") or ""
	local is_external = el.src:match("^[%a][%w+.-]*:") or el.src:match("^//")

	if not is_external then
		base_url = base_url:gsub("/$", "")
		if el.src:match("^/") then
			el.src = base_url .. el.src
		elseif base_url ~= "" then
			el.src = base_url .. "/" .. el.src
		else
			el.src = "/" .. el.src
		end
	end

	image_count = image_count + 1
	if not el.attributes.loading then
		el.attributes.loading = image_count == 1 and "eager" or "lazy"
	end
	if image_count > 1 and not el.attributes.decoding then
		el.attributes.decoding = "async"
	end

	return el
end
