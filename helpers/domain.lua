local image_count = 0

-- Convert a local link ending in an index.html path segment to its directory URL.
local function clean_directory_url(url)
	local path, suffix = url:match("^([^?#]*)(.*)$")
	if path == "index.html" then
		return "./" .. suffix
	end
	if path:match("/index%.html$") then
		return path:sub(1, -string.len("index.html") - 1) .. suffix
	end
	return url
end

-- Preserve external targets while publishing local index pages as directory routes.
function Link(el)
	local is_external = el.target:match("^[%a][%w+.-]*:") or el.target:match("^//")
	if not is_external and not el.target:match("^#") then
		el.target = clean_directory_url(el.target)
	end
	return el
end

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
