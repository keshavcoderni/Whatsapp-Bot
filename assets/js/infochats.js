const config = window.chatConfig || {};
let currentPhone = config.currentPhone || '';
let lastMsgId = Number.isFinite(config.lastMsgId) ? config.lastMsgId : Number(config.lastMsgId) || 0;
let earliestMsgId = Number.isFinite(config.earliestMsgId) ? config.earliestMsgId : Number(config.earliestMsgId) || 0;
const projectRootUrl = config.projectRootUrl || '';
const chatBody = document.getElementById('chatBody');
const textarea = document.getElementById('adminMessage');
let pollingInterval = null;
let isTabActive = true;

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

const searchInput = document.getElementById('searchUser');
if(searchInput) {
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        document.querySelectorAll('.user').forEach(user => {
            const phone = user.querySelector('.user-phone')?.innerText.toLowerCase() || '';
            user.style.display = phone.includes(searchTerm) ? 'flex' : 'none';
        });
    });
}

if(textarea) {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    textarea.addEventListener('keydown', function(e) {
        if(e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendReply();
        }
    });
}

document.addEventListener('visibilitychange', () => {
    isTabActive = !document.hidden;
    adjustPollingSpeed();
});

function adjustPollingSpeed() {
    if(!currentPhone) return;
    clearInterval(pollingInterval);
    // Standardize 3500ms when active, drops down to 20 seconds when backgrounded to prevent memory leaks and lags
    const delay = isTabActive ? 3500 : 20000;
    pollingInterval = setInterval(fetchNewMessages, delay);
}

async function fetchNewMessages() {
    if(!isTabActive || !currentPhone) return;
    try {
        const response = await fetch(`get_new_messages.php?phone=${encodeURIComponent(currentPhone)}&last_id=${lastMsgId}`);
        const data = await response.json();
        if(data.success && data.messages?.length > 0) {
            const fragment = document.createDocumentFragment();
            data.messages.forEach(msg => {
                if(msg.id > lastMsgId) {
                    const el = createMessageDOMElement(msg);
                    fragment.appendChild(el);
                    lastMsgId = msg.id;
                }
            });
            if(fragment.children.length > 0) {
                chatBody.appendChild(fragment);
                chatBody.scrollTop = chatBody.scrollHeight;
            }
        }
    } catch(e) { console.error("Polling error:", e); }
}

async function sendReply() {
    if(!textarea || !textarea.value.trim()) return;
    const sendBtn = document.getElementById('sendBtn');
    const msg = textarea.value.trim();
    
    if(sendBtn) sendBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('phone', currentPhone);
    formData.append('message', msg);
    
    try {
        const response = await fetch('send_admin_message.php', { method: 'POST', body: formData });
        const result = await response.json();
        if(result.success) {
            textarea.value = '';
            textarea.style.height = '44px';
            const tempMsg = { id: result.message_id || Date.now(), message: msg, type: 'admin' };
            appendMessageToBottom(tempMsg);
            if(tempMsg.id > lastMsgId) lastMsgId = tempMsg.id;
        } else { alert('Error: ' + (result.error || 'Could not dispatch message')); }
    } catch(e) { console.error(e); }
    finally { if(sendBtn) sendBtn.disabled = false; }
}

async function loadOlderMessages() {
    const loadBtn = document.getElementById('loadMoreBtn');
    if(!loadBtn) return;
    loadBtn.disabled = true;
    
    try {
        const response = await fetch(`get_historical_messages.php?phone=${encodeURIComponent(currentPhone)}&before_id=${earliestMsgId}`);
        const data = await response.json();
        
        if(data.success && data.messages?.length > 0) {
            const previousHeight = chatBody.scrollHeight;
            const anchor = document.getElementById('messageAnchor');
            const fragment = document.createDocumentFragment();
            
            data.messages.forEach(msg => {
                const wrapper = createMessageDOMElement(msg);
                fragment.appendChild(wrapper);
            });
             
            anchor.parentNode.insertBefore(fragment, anchor.nextSibling);
            earliestMsgId = data.messages[0].id;
            if(chatBody) chatBody.scrollTop = chatBody.scrollHeight - previousHeight;
            if(!data.has_more) loadBtn.remove();
        } else { loadBtn.remove(); }
    } catch(e) { console.error(e); }
    finally { if(loadBtn) loadBtn.disabled = false; }
}

function createMessageDOMElement(msg) {
    const wrapper = document.createElement('div');
    const isTxtAdmin = msg.type === 'admin';
    wrapper.className = 'message-wrapper' + (isTxtAdmin ? ' admin-msg-wrapper' : '');
    wrapper.setAttribute('data-msg-id', msg.id);
    
    const cls = isTxtAdmin ? 'admin-msg' : (msg.type === 'user' ? 'user-msg' : 'bot-msg');
    let rawContent = msg.message.trim();
    let messageContentHtml = '';

    const isWhatsAppImage = rawContent.startsWith('IMAGE:');
    if (isWhatsAppImage) {
        rawContent = rawContent.replace('IMAGE:', '').trim();
    }

    const isImageFile = isWhatsAppImage || /\.(jpg|jpeg|png|gif|webp)$/i.test(rawContent) || rawContent.includes('uploads/') || rawContent.includes('attachments/');

    if (isImageFile) {
        let finalUrl = isWhatsAppImage ? `get_image.php?id=${encodeURIComponent(rawContent)}` : rawContent;
        if (!isWhatsAppImage && !rawContent.startsWith('http://') && !rawContent.startsWith('https://')) {
            finalUrl = projectRootUrl + '/' + rawContent.replace(/^\/+/, '');
        }

        messageContentHtml = `
            <a href="${escapeHtml(finalUrl)}" target="_blank">
                <img src="${escapeHtml(finalUrl)}" class="chat-media-attachment" alt="Screenshot" onerror="this.onerror=null; this.parentNode.innerHTML='<i class=\'fas fa-file-image\'></i> Failed to load media';">
            </a>`;
    } else {
        messageContentHtml = escapeHtml(msg.message).replace(/\n/g, '<br>');
    }

    wrapper.innerHTML = `
        <button class="msg-delete" onclick="deleteMessage(${msg.id})" title="Delete message"><i class="fas fa-trash-alt"></i></button>
        <div class="message ${cls}">
            <div class="msg-text">${messageContentHtml}</div>
        </div>`;
    return wrapper;
}

function appendMessageToBottom(msg) {
    if(!chatBody) return;
    const wrapper = createMessageDOMElement(msg);
    chatBody.appendChild(wrapper);
    chatBody.scrollTop = chatBody.scrollHeight;
}

async function deleteMessage(id) {
    if(!confirm('Delete this message permanently?')) return;
    const formData = new FormData();
    formData.append('id', id);
    try {
        const response = await fetch('delete_message.php', { method: 'POST', body: formData });
        const result = await response.json();
        if(result.success) { document.querySelector(`.message-wrapper[data-msg-id="${id}"]`)?.remove(); }
    } catch(e) { console.error(e); }
}

async function deleteChat() {
    if(!confirm('Clear conversation history? This cannot be undone!')) return;
    const formData = new FormData();
    formData.append('phone', currentPhone);
    try {
        const response = await fetch('delete_chat.php', { method: 'POST', body: formData });
        const result = await response.json();
        if(result.success) { window.location.reload(); }
    } catch(e) { console.error(e); }
}

async function deleteUser() {
    if(!confirm('Delete user and all matching log sets permanently?')) return;
    const formData = new FormData();
    formData.append('phone', currentPhone);
    try {
        const response = await fetch('delete_user.php', { method: 'POST', body: formData });
        const result = await response.json();
        if(result.success) { window.location.href = 'chats.php'; }
    } catch(e) { console.error(e); }
}

function escapeHtml(text) {
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

function toggleTheme() {
    const isLightNow = window.InfoTagTheme ? window.InfoTagTheme.toggle() : document.documentElement.classList.toggle('light-mode');
    updateThemeIcon(isLightNow);
}

function updateThemeIcon(isLight) {
    const themeIcon = document.querySelector('#themeToggleBtn i');
    if(themeIcon) {
        themeIcon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const isLight = document.documentElement.classList.contains('light-mode');
    updateThemeIcon(isLight);
    if(chatBody) chatBody.scrollTop = chatBody.scrollHeight;
    adjustPollingSpeed();
});
