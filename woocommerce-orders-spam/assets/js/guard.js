(function ($) {
	function fillProof() {
		document.querySelectorAll(".wact-js-proof").forEach(function (el) {
			el.value = "1";
		});
	}

	var widgetId = null;

	function whenTurnstileReady(cb) {
		if (typeof turnstile !== "undefined") {
			cb();
			return;
		}

		var tries = 0;
		var timer = setInterval(function () {
			tries += 1;
			if (typeof turnstile !== "undefined") {
				clearInterval(timer);
				cb();
			} else if (tries > 50) {
				clearInterval(timer);
			}
		}, 100);
	}

	function renderTurnstile() {
		if (!window.wactGuard || !wactGuard.turnstileSiteKey) {
			return;
		}

		whenTurnstileReady(function () {
			var el = document.getElementById("wact-turnstile-widget");
			if (!el || typeof turnstile === "undefined") {
				return;
			}

			try {
				if (widgetId !== null) {
					turnstile.remove(widgetId);
				}
			} catch (e) {}

			el.innerHTML = "";
			widgetId = turnstile.render(el, {
				sitekey: wactGuard.turnstileSiteKey,
				appearance: "always",
				callback: function (token) {
					var input = document.getElementById("wact-turnstile-token");
					if (input) {
						input.value = token;
					}
				},
				"expired-callback": function () {
					var input = document.getElementById("wact-turnstile-token");
					if (input) {
						input.value = "";
					}
				},
				"error-callback": function () {
					var input = document.getElementById("wact-turnstile-token");
					if (input) {
						input.value = "";
					}
				},
			});
		});
	}

	function bootSession() {
		if (!window.wactGuard || !wactGuard.isCheckout || !wactGuard.ajaxUrl) {
			return;
		}

		$.post(wactGuard.ajaxUrl, {
			action: "wact_boot",
			nonce: wactGuard.nonce,
		});
	}

	$(function () {
		fillProof();
		bootSession();
		renderTurnstile();
	});

	$(document.body).on("updated_checkout checkout_error", function () {
		fillProof();
		renderTurnstile();
	});
})(jQuery);
