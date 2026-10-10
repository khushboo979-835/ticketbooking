/**
 * assets/js/scanner.js - QR Entry Scanner with html5-qrcode
 * Handles camera stream, audio beeps, backend check, and instant color-coded modal
 */

let html5QrCode = null;
let isScanning = false;
let isProcessing = false;

// Audio Synthesizer Beeps using Web Audio API
function playBeep(type) {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);

        if (type === 'success') {
            // High double cheerful chime
            osc.frequency.setValueAtTime(880, audioCtx.currentTime); // A5
            osc.frequency.setValueAtTime(1320, audioCtx.currentTime + 0.1); // E6
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.35);
        } else if (type === 'warning') {
            // Mid double alert
            osc.frequency.setValueAtTime(520, audioCtx.currentTime);
            osc.frequency.setValueAtTime(440, audioCtx.currentTime + 0.12);
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.35);
        } else {
            // Low buzz
            osc.frequency.setValueAtTime(220, audioCtx.currentTime);
            osc.type = 'sawtooth';
            gain.gain.setValueAtTime(0.4, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.4);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.4);
        }
    } catch (e) {
        console.warn('AudioContext not allowed or not supported yet', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const readerDiv = document.getElementById('qr-reader');
    if (!readerDiv) return;

    html5QrCode = new Html5Qrcode("qr-reader");

    const startBtn = document.getElementById('btn-start-scanner');
    const stopBtn = document.getElementById('btn-stop-scanner');
    const manualBtn = document.getElementById('btn-manual-verify');
    const manualInput = document.getElementById('manual-token-input');

    if (startBtn) startBtn.addEventListener('click', startScanner);
    if (stopBtn) stopBtn.addEventListener('click', stopScanner);

    if (manualBtn && manualInput) {
        manualBtn.addEventListener('click', () => {
            const val = manualInput.value.trim();
            if (val) verifyScannedPayload(val);
        });
        manualInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = manualInput.value.trim();
                if (val) verifyScannedPayload(val);
            }
        });
    }

    // Auto-start scanner on camera-capable devices
    if (startBtn) {
        startScanner();
    }

    // Battery & Memory optimization: Stop camera tracks when navigating away or switching tabs
    window.addEventListener('beforeunload', () => {
        if (isScanning && html5QrCode) {
            html5QrCode.stop().catch(() => {});
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && isScanning) {
            stopScanner();
        }
    });
});

function startScanner() {
    if (isScanning || !html5QrCode) return;

    const qrConfig = {
        fps: 10,
        qrbox: { width: 260, height: 260 },
        aspectRatio: 1.0
    };

    html5QrCode.start(
        { facingMode: "environment" },
        qrConfig,
        (decodedText) => {
            if (!isProcessing) {
                isProcessing = true;
                verifyScannedPayload(decodedText);
            }
        },
        (errorMessage) => {
            // QR detection attempts (silent)
        }
    ).then(() => {
        isScanning = true;
        document.getElementById('scanner-status').innerText = 'Camera Active: Point at Ticket QR Code';
        document.getElementById('scanner-status').style.color = '#34d399';
        document.getElementById('btn-start-scanner').style.display = 'none';
        document.getElementById('btn-stop-scanner').style.display = 'inline-flex';
    }).catch(err => {
        console.error("Camera startup error:", err);
        document.getElementById('scanner-status').innerText = 'Camera error or permission denied. You can still use manual entry below.';
        document.getElementById('scanner-status').style.color = '#f87171';
    });
}

function stopScanner() {
    if (!isScanning || !html5QrCode) return;

    html5QrCode.stop().then(() => {
        isScanning = false;
        document.getElementById('scanner-status').innerText = 'Scanner Stopped';
        document.getElementById('scanner-status').style.color = '#9aa1b8';
        document.getElementById('btn-start-scanner').style.display = 'inline-flex';
        document.getElementById('btn-stop-scanner').style.display = 'none';
    }).catch(err => console.error("Error stopping scanner:", err));
}

function verifyScannedPayload(rawText) {
    let token = rawText;
    let uid = '';

    // Check if payload is JSON: {"uid":"MIG...", "token":"..."}
    try {
        const parsed = JSON.parse(rawText);
        if (parsed.token) token = parsed.token;
        if (parsed.uid) uid = parsed.uid;
    } catch (e) {
        // Raw string token
    }

    fetch('../api/check-ticket.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ token: token, uid: uid })
    })
    .then(response => response.json())
    .then(data => {
        showVerificationResult(data);
    })
    .catch(err => {
        console.error("Verification error:", err);
        showVerificationResult({
            status: 'error',
            message: 'Network error or server unavailable. Please try again.'
        });
    });
}

function showVerificationResult(res) {
    const modal = document.getElementById('scan-result-modal');
    const titleEl = document.getElementById('modal-result-title');
    const badgeEl = document.getElementById('modal-status-badge');
    const detailsEl = document.getElementById('modal-result-details');
    const cardEl = document.getElementById('modal-result-card');

    modal.classList.add('active');

    if (res.status === 'valid') {
        playBeep('success');
        cardEl.style.border = '2px solid #10b981';
        badgeEl.className = 'badge badge-verified';
        badgeEl.innerText = 'ENTRY GRANTED';
        titleEl.innerText = '🟢 Ticket Verified Successfully';
        titleEl.style.color = '#10b981';

        detailsEl.innerHTML = `
            <div style="font-size:1.1rem; margin-bottom:12px;"><strong>Attendee:</strong> ${escapeHtml(res.customer_name)}</div>
            <div style="margin-bottom:8px;"><strong>Booking ID:</strong> <span style="font-family:monospace; color:#f5b722;">${escapeHtml(res.booking_uid)}</span></div>
            <div style="margin-bottom:8px;"><strong>Event:</strong> ${escapeHtml(res.event_title)}</div>
            <div style="margin-bottom:8px;"><strong>Tier / Passes:</strong> ${escapeHtml(res.tickets_summary)}</div>
            <div style="margin-bottom:8px; font-size:1.2rem; color:#ffd966;"><strong>Allowed Entry:</strong> <span style="background:#7a1244; padding:2px 10px; border-radius:6px;">${res.total_people} Persons</span></div>
            <div style="margin-top:14px; color:#10b981; font-weight:bold;">Status: Check-in recorded at ${new Date().toLocaleTimeString()}</div>
        `;
    } else if (res.status === 'already_used') {
        playBeep('warning');
        cardEl.style.border = '2px solid #f59e0b';
        badgeEl.className = 'badge badge-pending';
        badgeEl.innerText = 'ALREADY CHECKED IN';
        titleEl.innerText = '🟡 Duplicate Entry Warning';
        titleEl.style.color = '#f59e0b';

        detailsEl.innerHTML = `
            <div style="font-size:1.1rem; margin-bottom:12px;"><strong>Attendee:</strong> ${escapeHtml(res.customer_name || 'N/A')}</div>
            <div style="margin-bottom:8px;"><strong>Booking ID:</strong> <span style="font-family:monospace; color:#f5b722;">${escapeHtml(res.booking_uid || '')}</span></div>
            <div style="margin-bottom:8px;"><strong>Allowed Persons:</strong> ${res.total_people || 1} Persons</div>
            <div style="padding:10px; background:rgba(245,158,11,0.15); border:1px solid #f59e0b; border-radius:8px; margin-top:12px; color:#ffd966;">
                <strong>Previously Checked-in At:</strong><br>
                ${escapeHtml(res.checked_in_at || 'Earlier Today')}
            </div>
        `;
    } else {
        playBeep('error');
        cardEl.style.border = '2px solid #ef4444';
        badgeEl.className = 'badge badge-failed';
        badgeEl.innerText = 'ENTRY DENIED';
        titleEl.innerText = '🔴 Invalid or Unpaid Ticket';
        titleEl.style.color = '#ef4444';

        detailsEl.innerHTML = `
            <div style="padding:12px; background:rgba(239,68,68,0.15); border:1px solid #ef4444; border-radius:8px; color:#fca5a5; margin-top:10px;">
                <strong>Reason:</strong> ${escapeHtml(res.message || 'Invalid Ticket QR Token')}
            </div>
            ${res.payment_status ? `<div style="margin-top:10px; color:#ccc;">Payment Status: <strong>${escapeHtml(res.payment_status.toUpperCase())}</strong></div>` : ''}
        `;
    }
}

function dismissModal() {
    const modal = document.getElementById('scan-result-modal');
    modal.classList.remove('active');
    isProcessing = false;
    const manualInput = document.getElementById('manual-token-input');
    if (manualInput) manualInput.value = '';
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}
