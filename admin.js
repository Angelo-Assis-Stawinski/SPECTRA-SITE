let csrf = '', maxUploadBytes = 0;
const $ = id => document.getElementById(id);
const writeOptions = body => ({method: 'POST', headers: {'X-CSRF-Token': csrf}, body});
function feedback(id, message, error = false) {
  const el = $(id); el.textContent = message;
  el.className = 'form-feedback ' + (error ? 'error' : 'success');
}
async function runAction(work, target = 'admin-status') {
  try { await work(); } catch (error) {
    if (target === 'admin-status') $(target).textContent = error.message;
    else feedback(target, error.message, true);
  }
}
function attachmentPreview() {
  const url = safeAttachmentUrl($('log-attachment').value);
  const file = $('log-file').files[0];
  $('attachment-preview').innerHTML = file ? 'Selecionado: ' + esc(file.name) :
    url ? `<a href="${esc(url)}">Baixar anexo salvo</a>` : 'Sem anexo.';
}
function resetLog() {
  $('log-form').reset(); $('log-id').value = ''; $('log-attachment').value = '';
  attachmentPreview();
}
function resetMessage() {
  $('message-form').reset(); $('message-id').value = ''; $('message-active').checked = true;
}
async function loadAdmin() {
  const me = await api('me');
  if (!me.user || me.user.role !== 'admin') throw new Error('Acesso restrito. Entre em conta.html.');
  csrf = me.csrf; $('admin-status').textContent = 'Administrador autenticado.';
  $('admin-ui').classList.remove('hidden');
  const limits = await api('admin_upload_limits'); maxUploadBytes = limits.max_bytes;
  $('upload-limit').textContent = 'Limite atual: ' + fmtBytes(maxUploadBytes) +
    ' (site: 25 MiB). Formatos: TXT, C, CSV, JSON, PDF, imagens, WAV, ZIP e FITS.';
  await Promise.all([loadLogs(), loadComments(), loadMessages()]); attachmentPreview();
}
async function loadLogs() {
  const data = await api('admin_logs'); const rows = data.logs || [];
  $('admin-logs').innerHTML = rows.map(p => `<div class="comment"><strong>${esc(p.title)}</strong>
    <span class="muted">${esc(p.source)}</span><div class="button-row">
    <button class="small-button" data-edit-log="${p.id}">Editar</button>
    <button class="danger-button" data-delete-log="${p.id}">Excluir</button></div></div>`).join('') || '<p>Nenhum post.</p>';
  document.querySelectorAll('[data-edit-log]').forEach(button => button.onclick = () => {
    const p = rows.find(row => +row.id === +button.dataset.editLog);
    $('log-id').value = p.id; $('log-title').value = p.title; $('log-body').value = p.body;
    $('log-attachment').value = p.attachment_url || ''; $('log-file').value = '';
    attachmentPreview(); feedback('log-feedback', 'Editando post ' + p.id + '.');
    $('log-form').scrollIntoView({block: 'center', behavior: 'smooth'});
  });
  document.querySelectorAll('[data-delete-log]').forEach(button => button.onclick = () => runAction(async () => {
    if (!confirm('Excluir este Log de Campo?')) return;
    await api('admin_log_delete', writeOptions({id: +button.dataset.deleteLog}));
    if (+$('log-id').value === +button.dataset.deleteLog) resetLog();
    await loadLogs(); feedback('log-feedback', 'Post excluído.');
  }, 'log-feedback'));
}
async function loadComments() {
  const data = await api('admin_comments');
  $('admin-comments').innerHTML = (data.comments || []).map(c => `<div class="comment"><strong>${esc(c.email)}</strong>
    • ${esc(c.category)} • ${esc(c.status)}<p>${esc(c.body)}</p><div class="button-row">
    <button class="small-button" data-comment="${c.id}" data-status="published">Publicar</button>
    <button class="small-button" data-comment="${c.id}" data-status="hidden">Ocultar</button></div></div>`).join('') || '<p>Nenhum comentário.</p>';
  document.querySelectorAll('[data-comment]').forEach(button => button.onclick = () => runAction(async () => {
    await api('admin_comment_status', writeOptions({id: +button.dataset.comment, status: button.dataset.status}));
    await loadComments();
  }));
}
async function loadMessages() {
  const data = await api('admin_messages'); const rows = data.messages || [];
  $('admin-messages').innerHTML = rows.map(m => `<div class="comment"><strong>${esc(m.title)}</strong>
    • ${+m.active ? 'ativa' : 'inativa'}<div class="blog-body">${esc(m.body)}</div><div class="button-row">
    <button class="small-button" data-edit-message="${m.id}">Editar</button>
    <button class="danger-button" data-delete-message="${m.id}">Excluir</button></div></div>`).join('') || '<p>Nenhuma mensagem.</p>';
  document.querySelectorAll('[data-edit-message]').forEach(button => button.onclick = () => {
    const m = rows.find(row => +row.id === +button.dataset.editMessage);
    $('message-id').value = m.id; $('message-title').value = m.title; $('message-body').value = m.body;
    $('message-active').checked = !!+m.active; feedback('message-feedback', 'Editando mensagem ' + m.id + '.');
  });
  document.querySelectorAll('[data-delete-message]').forEach(button => button.onclick = () => runAction(async () => {
    if (!confirm('Excluir esta mensagem do site?')) return;
    await api('admin_message_delete', writeOptions({id: +button.dataset.deleteMessage}));
    if (+$('message-id').value === +button.dataset.deleteMessage) resetMessage();
    await loadMessages(); feedback('message-feedback', 'Mensagem excluída.');
  }, 'message-feedback'));
}
document.addEventListener('DOMContentLoaded', () => {
  runAction(loadAdmin);
  $('log-new').onclick = () => { resetLog(); feedback('log-feedback', 'Novo post.'); };
  $('message-new').onclick = () => { resetMessage(); feedback('message-feedback', 'Nova mensagem.'); };
  $('log-file').onchange = attachmentPreview;
  $('remove-attachment').onclick = () => {
    $('log-attachment').value = ''; $('log-file').value = ''; attachmentPreview();
  };
  $('log-form').onsubmit = async event => {
    event.preventDefault(); const button = event.submitter || $('log-form').querySelector('[type=submit]');
    button.disabled = true;
    try {
      let attachment = $('log-attachment').value; const file = $('log-file').files[0];
      if (file) {
        if (maxUploadBytes && file.size > maxUploadBytes) throw new Error('Arquivo excede ' + fmtBytes(maxUploadBytes) + '. Ajuste os limites no PHP ou use um arquivo menor.');
        feedback('log-feedback', 'Enviando anexo…');
        const form = new FormData(); form.append('file', file);
        const uploaded = await api('admin_upload', writeOptions(form));
        attachment = uploaded.url;
        /* Mantém o anexo recebido caso o salvamento do post falhe. */
        $('log-attachment').value = attachment; $('log-file').value = ''; attachmentPreview();
      }
      await api('admin_log_save', writeOptions({id: +$('log-id').value || 0,
        title: $('log-title').value, body: $('log-body').value, attachment_url: attachment}));
      resetLog(); await loadLogs(); feedback('log-feedback', 'Post e anexo salvos.');
    } catch (error) { feedback('log-feedback', error.message, true); }
    finally { button.disabled = false; }
  };
  $('message-form').onsubmit = async event => {
    event.preventDefault(); const button = event.submitter || $('message-form').querySelector('[type=submit]');
    button.disabled = true;
    try {
      await api('admin_message_save', writeOptions({id: +$('message-id').value || 0,
        title: $('message-title').value, body: $('message-body').value, active: $('message-active').checked}));
      resetMessage(); await loadMessages(); feedback('message-feedback', 'Mensagem salva.');
    } catch (error) { feedback('message-feedback', error.message, true); }
    finally { button.disabled = false; }
  };
  $('show-bridge-token').onclick = () => runAction(async () => {
    const data = await api('admin_bridge_token'); $('bridge-token').textContent = data.token;
  });
  $('password-form').onsubmit = event => {
    event.preventDefault(); runAction(async () => {
      await api('admin_change_password', writeOptions({password: $('new-admin-password').value}));
      $('new-admin-password').value = ''; $('admin-status').textContent = 'Senha administrativa alterada.';
    });
  };
  $('admin-logout').onclick = () => runAction(async () => {
    await api('logout', writeOptions()); location.href = 'conta.html';
  });
});
