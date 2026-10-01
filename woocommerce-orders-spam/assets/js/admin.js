(function () {
	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			var area = document.createElement("textarea");
			area.value = text;
			area.setAttribute("readonly", "");
			area.style.position = "fixed";
			area.style.left = "-9999px";
			document.body.appendChild(area);
			area.select();
			try {
				document.execCommand("copy");
				resolve();
			} catch (e) {
				reject(e);
			}
			document.body.removeChild(area);
		});
	}

	document.querySelectorAll(".wact-copy-btn").forEach(function (button) {
		button.addEventListener("click", function () {
			var targetId = button.getAttribute("data-target");
			var field = targetId ? document.getElementById(targetId) : null;
			if (!field) {
				return;
			}

			copyText(field.value).then(function () {
				var original = button.getAttribute("data-label") || button.textContent;
				button.setAttribute("data-label", original);
				button.textContent = button.getAttribute("data-copied") || "Copied";
				button.classList.add("is-copied");
				setTimeout(function () {
					button.textContent = original;
					button.classList.remove("is-copied");
				}, 1600);
			});
		});
	});
})();
