const config = window.rawmarkEditor || {};

function request(path, options = {}) {
  const url = `${config.restUrl || ''}${path}`;

  return fetch(url, {
    ...options,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
      ...(options.headers || {}),
    },
  }).then(async (response) => {
    const body = await response.json().catch(() => null);

    if (!response.ok) {
      const message = body && body.message ? body.message : `Request failed (${response.status})`;
      throw new Error(message);
    }

    return body;
  });
}

export function getPage(id) {
  return request(`/pages/${id}`, { method: 'GET' });
}

export function savePage(id, data) {
  return request(`/pages/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

export function createSnippet(sourcePageId, name) {
  return request('/snippets', {
    method: 'POST',
    body: JSON.stringify({ source_page_id: sourcePageId, name }),
  });
}

export function listSnippets() {
  return request('/snippets', { method: 'GET' });
}
