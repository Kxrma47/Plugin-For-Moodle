window.checkVoteStatus = function(pollId) {

    const pollItem = document.getElementById('poll_item_' + pollId);
    if (pollItem) {
        const votedElement = pollItem.querySelector('.poll_voted');
        if (votedElement) {
            return { hasVoted: true, element: votedElement };
        }
    }
    return { hasVoted: false, element: null };
}

window.showMoodleNotification = function(message, type = 'info') {

    const existingNotifications = document.querySelectorAll('.moodle-notification');
    existingNotifications.forEach(notification => notification.remove());

    const notification = document.createElement('div');
    notification.className = 'moodle-notification';

    let backgroundColor, borderColor, textColor, icon;
    switch (type) {
        case 'success':
            backgroundColor = '#d4edda';
            borderColor = '#c3e6cb';
            textColor = '#155724';
            icon = '✓';
            break;
        case 'error':
            backgroundColor = '#f8d7da';
            borderColor = '#f5c6cb';
            textColor = '#721c24';
            icon = '✗';
            break;
        case 'warning':
            backgroundColor = '#fff3cd';
            borderColor = '#ffeaa7';
            textColor = '#856404';
            icon = '⚠';
            break;
        default:
            backgroundColor = '#d1ecf1';
            borderColor = '#bee5eb';
            textColor = '#0c5460';
            icon = 'ℹ';
    }

    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${backgroundColor};
        border: 1px solid ${borderColor};
        color: ${textColor};
        padding: 15px 20px;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 1000;
        max-width: 350px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        font-size: 14px;
        line-height: 1.4;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideInRight 0.3s ease-out;
    `;

    notification.innerHTML = `
        <span style="font-size: 18px; font-weight: bold;">${icon}</span>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="
            background: none;
            border: none;
            color: ${textColor};
            font-size: 18px;
            cursor: pointer;
            margin-left: auto;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        ">×</button>
    `;

    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);

    document.body.appendChild(notification);

    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

window.togglePollContent = function(pollId) {
    const allPollContents = document.querySelectorAll(".poll_content");
    const allToggleIcons = document.querySelectorAll(".poll_toggle_icon");

    allPollContents.forEach((content, index) => {
        if (content.id !== "poll_content_" + pollId) {
            content.style.display = "none";
            allToggleIcons[index].textContent = "▼";
            allToggleIcons[index].style.transform = "rotate(0deg)";
        }
    });

    const currentContent = document.getElementById("poll_content_" + pollId);
    const currentIcon = document.getElementById("toggle_icon_" + pollId);

    if (currentContent.style.display === "none" || currentContent.style.display === "") {
        currentContent.style.display = "block";
        currentIcon.textContent = "▲";
        currentIcon.style.transform = "rotate(0deg)";
    } else {
        currentContent.style.display = "none";
        currentIcon.textContent = "▼";
        currentIcon.style.transform = "rotate(0deg)";
    }
}

window.confirmAndSubmitVote = function(pollId) {
    const isMultipleChoice = document.querySelectorAll(`input[name="option_id_${pollId}[]"]`).length > 0;
    let optionIds = [];
    let optionTexts = [];

    if (isMultipleChoice) {
        const selected = document.querySelectorAll(`input[name="option_id_${pollId}[]"]:checked`);
        if (!selected.length) { alert('Please select at least one option before voting'); return; }
        optionIds = Array.from(selected).map(o => o.value);
        optionTexts = Array.from(selected).map(o => (document.querySelector(`label[for="${o.id}"]`)?.textContent || o.value));
    } else {
        const selected = document.querySelector(`input[name="option_id_${pollId}"]:checked`);
        if (!selected) { alert('Please select an option before voting'); return; }
        optionIds = [selected.value];
        optionTexts = [(document.querySelector(`label[for="${selected.id}"]`)?.textContent || selected.value)];
    }

    const listHtml = '<ul style="margin:8px 0 0; padding-left:18px;">' + optionTexts.map(t => `<li>${t}</li>`).join('') + '</ul>';
    const modal = document.getElementById('pollModal');
    if (modal) {
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalIcon = document.getElementById('modalIcon');
        const modalBtn = document.getElementById('modalBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');

        modalTitle.textContent = 'Permanent Vote Confirmation';
        modalIcon.innerHTML = '<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>';
        modalIcon.className = 'modal-icon warning';
        modalMessage.innerHTML = '<div style="line-height:1.6">' +
            '<p>You are about to submit a <strong>PERMANENT</strong> vote for:</p>' + listHtml +
            '<p style="margin-top:8px">This vote <strong>CANNOT</strong> be changed or undone once submitted.</p>' +
            '<p>Are you absolutely sure you want to submit this vote?</p>' +
            '</div>';

        modalBtn.textContent = 'Submit Vote';
        modalBtn.style.background = '#dc3545';
        modalBtn.onclick = function() {
            closeModal();
            if (isMultipleChoice) {
                submitMultipleChoiceVote(pollId, optionIds, optionTexts);
            } else {
                submitVote(pollId, optionIds[0]);
            }
        };
        modalCancelBtn.style.display = 'inline-block';
        modalCancelBtn.onclick = function() { closeModal(); };

        modal.classList.add('show');
        return;
    }

    showVoteConfirmationModal(pollId, optionIds, optionTexts, isMultipleChoice);
}

window.submitVote = function(pollId, optionId) {

    const formData = new FormData();
    formData.append("action", "submit_vote");
    formData.append("poll_id", pollId);
    formData.append("option_id", optionId);
    if (window.M && M.cfg && M.cfg.sesskey) {
        formData.append('sesskey', M.cfg.sesskey);
    }

    const ajaxUrl = '/blocks/poll/ajax_handler.php';

    fetch(ajaxUrl, {
        method: "POST",
        body: formData
    })
    .then(response => {
        if (!response.ok) {

            const voteStatus = checkVoteStatus(pollId);
            if (voteStatus.hasVoted) {
                return { success: true, message: 'Vote submitted successfully' };
            } else {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
        }

        return response.json();
    })
    .then(data => {
        if (data.success) {

            showMoodleNotification('Vote submitted successfully! Your vote is now PERMANENT and cannot be changed.', 'success');

            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMoodleNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error("Vote submission error:", error);

        const voteStatus = checkVoteStatus(pollId);
        if (voteStatus.hasVoted) {
            showMoodleNotification('Vote submitted successfully! Reloading to show status...', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {

            showMoodleNotification('Vote submission had an error, but your vote may have been recorded. Checking status...', 'warning');
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
    });
}

window.submitMultipleChoiceVote = function(pollId, optionIds, optionTexts) {

    const formData = new FormData();
    formData.append("action", "submit_multiple_choice_vote");
    formData.append("poll_id", pollId);

    optionIds.forEach(optionId => {
        formData.append("option_ids[]", optionId);
    });
    if (window.M && M.cfg && M.cfg.sesskey) {
        formData.append('sesskey', M.cfg.sesskey);
    }

    const ajaxUrl = '/blocks/poll/ajax_handler.php';

    fetch(ajaxUrl, {
        method: "POST",
        body: formData
    })
    .then(response => {
        if (!response.ok) {

            const voteStatus = checkVoteStatus(pollId);
            if (voteStatus.hasVoted) {
                return { success: true, message: 'Vote submitted successfully' };
            } else {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
        }

        return response.json();
    })
    .then(data => {
        if (data.success) {

            const selectedText = optionTexts.join(', ');
            showMoodleNotification(`Multiple choice vote submitted successfully! You voted for: ${selectedText}`, 'success');

            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showMoodleNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error("Multiple choice vote submission error:", error);

        const voteStatus = checkVoteStatus(pollId);
        if (voteStatus.hasVoted) {
            showMoodleNotification('Multiple choice vote submitted successfully! Reloading to show status...', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {

            showMoodleNotification('Multiple choice vote submission had an error, but your vote may have been recorded. Checking status...', 'warning');
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
    });
}

window.closeModal = function() {
    const modal = document.getElementById('pollModal');
    if (modal) {
        modal.classList.remove('show');
    }
};

window.showModal = function(title, message, type = 'info', autoClose = false) {
    const modal = document.getElementById('pollModal');
    if (!modal) {
        console.error('Modal not found');
        return;
    }

    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalIcon = document.getElementById('modalIcon');
    const modalBtn = document.getElementById('modalBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');

    modalTitle.textContent = title;
    modalMessage.textContent = message;

    let iconClass, iconSvg;
    switch (type) {
        case 'success':
            iconClass = 'success';
            iconSvg = '<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
            break;
        case 'error':
            iconClass = 'error';
            iconSvg = '<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
            break;
        case 'warning':
            iconClass = 'warning';
            iconSvg = '<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>';
            break;
        default:
            iconClass = 'info';
            iconSvg = '<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
    }

    modalIcon.innerHTML = iconSvg;
    modalIcon.className = 'modal-icon ' + iconClass;

    modalBtn.textContent = autoClose ? 'Close' : 'OK';
    modalBtn.onclick = closeModal;

    modalCancelBtn.style.display = 'none';

    modal.classList.add('show');

    if (autoClose) {
        setTimeout(closeModal, 3000);
    }
};

window.showVoteConfirmationModal = function(pollId, optionIds, optionTexts, isMultipleChoice) {
    const modal = document.getElementById('pollModal');
    if (!modal) {
        console.error('Modal not found, falling back to browser confirm');
        const confirmed = confirm('PERMANENT VOTE CONFIRMATION\n\n' + optionTexts.join(', ') + '\n\nThis vote cannot be changed. Continue?');
        if (confirmed) {
            if (isMultipleChoice) {
                submitMultipleChoiceVote(pollId, optionIds, optionTexts);
            } else {
                submitVote(pollId, optionIds[0]);
            }
        }
        return;
    }

    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalIcon = document.getElementById('modalIcon');
    const modalBtn = document.getElementById('modalBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');

    modalTitle.textContent = 'PERMANENT VOTE CONFIRMATION';

    let messageHTML = '<div style="text-align: left; margin-bottom: 20px;">';
    messageHTML += '<p style="margin: 0 0 15px 0; font-weight: 600; color: #dc3545;">⚠️ This vote cannot be changed once submitted!</p>';
    messageHTML += '<p style="margin: 0 0 15px 0;">You are about to vote for:</p>';

    optionTexts.forEach(text => {
        messageHTML += '<div class="vote-confirmation-option" style="background: #f8f9fa; padding: 12px 16px; margin: 8px 0; border-radius: 6px; border-left: 4px solid #007bff; text-align: left; font-weight: 500; color: #333;">' + text + '</div>';
    });

    messageHTML += '<p style="margin: 15px 0 0 0; font-weight: 600; color: #495057;">Continue?</p>';
    messageHTML += '</div>';

    modalMessage.innerHTML = messageHTML;
    modalIcon.innerHTML = '<svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
    modalIcon.className = 'modal-icon success';

    modalBtn.textContent = 'OK';
    modalBtn.className = 'modal-btn';
    modalBtn.onclick = function() {
        closeModal();
        if (isMultipleChoice) {
            submitMultipleChoiceVote(pollId, optionIds, optionTexts);
        } else {
            submitVote(pollId, optionIds[0]);
        }
    };

    modalCancelBtn.textContent = 'Cancel';
    modalCancelBtn.style.display = 'inline-block';
    modalCancelBtn.onclick = closeModal;

    modal.classList.add('show');
};
