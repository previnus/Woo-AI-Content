(() => {
	const panel = document.getElementById('woo-ai-content');
	if (!panel) {
		return;
	}

	const generate = document.getElementById('woo-ai-generate');
	const status = document.getElementById('woo-ai-status');
	const results = document.getElementById('woo-ai-results');
	const preview = (name) => document.getElementById(`woo-ai-${name}`);

	function visualEditor(id) {
		const editor = window.tinymce?.get(id);
		return editor && !editor.isHidden() ? editor : null;
	}

	function readEditor(id) {
		return visualEditor(id)?.getContent() ?? document.getElementById(id)?.value ?? '';
	}

	function insertDescription(id, text) {
		const field = document.getElementById(id);
		if (!field) {
			status.textContent = panel.dataset.noEditor;
			return;
		}
		if (readEditor(id).trim() && !window.confirm(panel.dataset.confirm)) {
			return;
		}

		const encoder = document.createElement('div');
		encoder.textContent = text;
		const html = encoder.innerHTML;
		const editor = visualEditor(id);
		if (editor) {
			const paragraphs = html.split(/\n{2,}/).map((paragraph) => `<p>${paragraph.replace(/\n/g, '<br>')}</p>`);
			editor.undoManager.transact(() => editor.setContent(paragraphs.join('\n')));
			editor.save();
			editor.setDirty(true);
			editor.fire('change');
		} else {
			field.value = html;
		}
		field.dispatchEvent(new Event('input', { bubbles: true }));
		field.dispatchEvent(new Event('change', { bubbles: true }));
		status.textContent = panel.dataset.applied;
	}

	generate.addEventListener('click', async () => {
		const title = document.getElementById('title')?.value.trim();
		if (!title) {
			status.textContent = panel.dataset.noTitle;
			document.getElementById('title')?.focus();
			return;
		}

		const data = new URLSearchParams({
			action: 'woo_ai_content_generate',
			post_id: panel.dataset.postId,
			nonce: panel.dataset.nonce,
			title: title.slice(0, 250),
			description: readEditor('content').slice(0, 4000),
			short_description: readEditor('excerpt').slice(0, 1500),
			notes: preview('notes').value.slice(0, 1500),
		});
		const controller = new AbortController();
		const timeout = window.setTimeout(() => controller.abort(), 45000);
		generate.disabled = true;
		status.textContent = panel.dataset.generating;

		try {
			const response = await fetch(window.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data,
				signal: controller.signal,
			});
			const result = await response.json();
			if (!response.ok || !result.success) {
				status.textContent = result?.data?.message || panel.dataset.failed;
				return;
			}

			for (const field of ['description', 'short_description', 'seo_title', 'meta_description']) {
				preview(field).value = result.data[field];
			}
			preview('keywords').value = result.data.keywords.join(', ');
			results.hidden = false;
			status.textContent = panel.dataset.ready;
		} catch {
			status.textContent = panel.dataset.failed;
		} finally {
			window.clearTimeout(timeout);
			generate.disabled = false;
		}
	});

	results.addEventListener('click', async (event) => {
		const button = event.target.closest('button');
		if (button?.dataset.apply) {
			insertDescription(button.dataset.apply, preview(button.dataset.field).value);
		} else if (button?.dataset.copy) {
			const field = preview(button.dataset.copy);
			try {
				await navigator.clipboard.writeText(field.value);
				status.textContent = panel.dataset.copied;
			} catch {
				field.focus();
				field.select();
				status.textContent = panel.dataset.copyManually;
			}
		}
	});
})();
