// Prototype Help (Student) - minimal signaling bootstrap
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const modal = document.getElementById('helpModal');
    const backdrop = document.getElementById('helpBackdrop');
    const btn = document.getElementById('helpBtn');
    const btnClose = document.getElementById('helpCloseBtn');
    const btnCopy = document.getElementById('copyJoinCodeBtn');
    const btnStart = document.getElementById('helpStartBtn');
    const btnEnd = document.getElementById('helpEndBtn');
    const joinCodeEl = document.getElementById('helpJoinCode');
    const statusEl = document.getElementById('helpStatus');
    const shareAudio = document.getElementById('shareAudio');
    const officerVideo = document.getElementById('officerVideo');
    const officerViewWrap = document.getElementById('officerViewWrap');
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const chatSend = document.getElementById('chatSend');
    const officerPlayBtn = document.getElementById('officerPlayBtn');
    const capabilityWarning = document.getElementById('capabilityWarning');
    const askOfficerShareBtn = document.getElementById('askOfficerShareBtn');

    const rtcConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
    let session = null; // { id, join_code, token, role, since_id }
    let pc = null;
    let screenStream = null;
    let micStream = null;
    let pollAbort = false;

    function openModal(){
      if(!modal||!backdrop) return;
      modal.style.display='flex'; backdrop.style.display='block';
      modal.setAttribute('aria-hidden','false');
    }
    function closeModal(){ // close and end session
      if(!modal||!backdrop) return;
      if(session && !confirm('End the current help session?')) return;
      endSession();
      modal.style.display='none'; backdrop.style.display='none';
      modal.setAttribute('aria-hidden','true');
    }
    function hideModalKeep(){ // close without ending
      if(!modal||!backdrop) return;
      modal.style.display='none'; backdrop.style.display='none';
      modal.setAttribute('aria-hidden','true');
    }

    async function createSession(){
      if (session) return;
      try {
        setStatus('Creating help session...');
        const res = await fetch('api/help/create_session.php', { method: 'POST' });
        const data = await res.json();
        if (!res.ok || !data || !data.success) {
          setStatus((data && data.error) ? data.error : 'Failed to create session');
          return;
        }

        // If we still don't have a screen stream on this device, show the capability banner
        if (!screenStream) { try { if (capabilityWarning) capabilityWarning.style.display=''; } catch {} }
        session = { id: data.session_id, join_code: data.join_code, token: data.student_token, role: 'student', since_id: 0 };
        if (joinCodeEl) joinCodeEl.textContent = data.join_code;
        btnStart.style.display = 'none';
        btnEnd.style.display = '';
        await startWebRTC();
        startPoll();
      } catch (e) {
        setStatus('Could not start session.');
      }
    }

    function setStatus(msg){ if (statusEl) statusEl.textContent = msg; }

    async function startWebRTC(){
      try {
        setStatus('Preparing connection...');
        // Try screen share if available (desktop). On mobile, this may be unavailable.
        if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
          try {
            screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: shareAudio && shareAudio.checked ? true : false });
          } catch(e) {
            try { screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true }); } catch(_) { /* no screen on this device */ }
          }
        } else {
          // No display capture support (common on mobile without HTTPS)
          try { if (capabilityWarning) capabilityWarning.style.display=''; } catch {}
          setStatus('Connecting without screen share. You can ask the officer to share their screen.');
        }
        // Optional mic if requested and screen had no audio
        if (shareAudio && shareAudio.checked && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
          const hasAudioTrack = !!(screenStream && screenStream.getAudioTracks().length);
          if (!hasAudioTrack) {
            try { micStream = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch(e) { /* mic not available or blocked */ }
          }
        }

        pc = new RTCPeerConnection(rtcConfig);
        // Pre-create receiving transceivers for better mobile/iOS compatibility
        try { pc.addTransceiver('video', {direction:'recvonly'}); } catch {}
        try { pc.addTransceiver('audio', {direction:'recvonly'}); } catch {}
        // Add tracks
        if (screenStream) screenStream.getTracks().forEach(t => pc.addTrack(t, screenStream));
        if (micStream) micStream.getTracks().forEach(t => pc.addTrack(t, micStream));

        pc.onicecandidate = async (ev) => {
          if (ev.candidate) {
            await sendSignal('candidate', { candidate: ev.candidate });
          }
        };
        pc.onconnectionstatechange = () => {
          setStatus('Connection: ' + pc.connectionState);
        };
        pc.ontrack = (ev) => {
          try {
            if (officerVideo && ev.streams && ev.streams[0]) {
              officerVideo.srcObject = ev.streams[0];
              officerVideo.setAttribute('playsinline','');
              officerVideo.setAttribute('webkit-playsinline','');
              officerVideo.muted = true; // allow autoplay; user can unmute
              officerVideo.play().then(()=>{ if(officerPlayBtn) officerPlayBtn.style.display='none'; }).catch(()=>{ if(officerPlayBtn) officerPlayBtn.style.display=''; });
              officerVideo.addEventListener('playing', ()=>{ if(officerPlayBtn) officerPlayBtn.style.display='none'; }, { once: true });
              if (officerViewWrap) officerViewWrap.style.display = '';
            }
          } catch {}
        };

        setStatus('Creating offer...');
        const offer = await pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
        await pc.setLocalDescription(offer);
        await sendSignal('offer', { sdp: pc.localDescription });
        setStatus('Waiting for officer to join...');

        // Handle screen end by user
        if (screenStream) { screenStream.getVideoTracks().forEach(track => track.addEventListener('ended', endSession)); }
      } catch (e) {
        setStatus('Failed to initialize WebRTC');
        console.error(e);
      }
    }

    async function sendSignal(type, payload){
      if (!session) return;
      await fetch('api/help/send_signal.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: session.id, role: 'student', token: session.token, type, payload })
      });
    }

    async function startPoll(){
      pollAbort = false;
      while (session && !pollAbort) {
        try {
          const res = await fetch('api/help/poll_signals.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: session.id, role: 'student', token: session.token, since_id: session.since_id || 0 })
          });
          const data = await res.json();
          if (res.ok && data && data.success && Array.isArray(data.signals)) {
            for (const sig of data.signals) {
              session.since_id = Math.max(session.since_id || 0, sig.id);
              if (sig.type === 'answer' && sig.payload && sig.payload.sdp && pc) {
                await pc.setRemoteDescription(new RTCSessionDescription(sig.payload.sdp));
                setStatus('Connected with officer.');
                // Hide modal so cadet focuses on registration form
                try {
                  hideModalKeep();
                  const formWrap = document.querySelector('.registration-form');
                  formWrap && formWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } catch {}
                // Hide help FAB while session is active
                try { const hb=document.getElementById('helpBtn'); if(hb) hb.style.display='none'; } catch {}
              }
              if (sig.type === 'offer' && sig.payload && sig.payload.sdp && pc) {
                // Officer initiated renegotiation (e.g., sharing their screen)
                await pc.setRemoteDescription(new RTCSessionDescription(sig.payload.sdp));
                const ans = await pc.createAnswer();
                await pc.setLocalDescription(ans);
                await sendSignal('answer', { sdp: pc.localDescription });
                setStatus('Officer shared screen.');
                // Show modal to display officer screen
                try { if (officerViewWrap) officerViewWrap.style.display=''; openModal(); } catch {}
              }
              if (sig.type === 'candidate' && pc && sig.payload && sig.payload.candidate) {
                try { await pc.addIceCandidate(new RTCIceCandidate(sig.payload.candidate)); } catch(e) { /* ignore */ }
              }
              if (sig.type === 'chat' && sig.payload && typeof sig.payload.text === 'string') {
                appendChat('Officer', sig.payload.text);
              }
            }
          }
        } catch (e) {
          // ignore transient errors
        }
      }
    }

    function appendChat(sender, text){
      if (!chatMessages) return;
      const line = document.createElement('div');
      line.innerHTML = `<strong>${escapeHtml(sender)}:</strong> ${escapeHtml(text)}`;
      chatMessages.appendChild(line);
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
    async function sendChat(){
      if (!session || !chatInput) return;
      const txt = (chatInput.value||'').trim();
      if (!txt) return;
      chatInput.value = '';
      appendChat('You', txt);
      try {
        await sendSignal('chat', { text: txt, ts: Date.now() });
      } catch {}
    }

    async function endSession(){
      pollAbort = true;
      try {
        if (session) {
          await fetch('api/help/abandon_session.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: session.id, role: 'student', token: session.token })
          });
        }
      } catch(e) {}
      finally {
        if (pc) { try { pc.getSenders().forEach(s=>{try{s.track && s.track.stop();}catch{}}); pc.close(); } catch {} pc = null; }
        if (screenStream) { try { screenStream.getTracks().forEach(t=>t.stop()); } catch {} screenStream = null; }
        if (micStream) { try { micStream.getTracks().forEach(t=>t.stop()); } catch {} micStream = null; }
        session = null;
        if (joinCodeEl) joinCodeEl.textContent = '\u2014 \u2014 \u2014';
        if (statusEl) statusEl.textContent = 'Not connected';
        btnStart.style.display = '';
        btnEnd.style.display = 'none';
        try { const hb=document.getElementById('helpBtn'); if(hb) hb.style.display=''; } catch {}
      }
    }

    btn && btn.addEventListener('click', openModal);
    btnClose && btnClose.addEventListener('click', hideModalKeep);
    backdrop && backdrop.addEventListener('click', hideModalKeep);
    btnCopy && btnCopy.addEventListener('click', function(){
      const code = (joinCodeEl && joinCodeEl.textContent || '').trim();
      if (code && code !== '\u2014 \u2014 \u2014' && navigator.clipboard) {
        navigator.clipboard.writeText(code);
      }
    });
    btnStart && btnStart.addEventListener('click', createSession);
    btnEnd && btnEnd.addEventListener('click', endSession);
    chatSend && chatSend.addEventListener('click', sendChat);
    chatInput && chatInput.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); sendChat(); }});
    officerPlayBtn && officerPlayBtn.addEventListener('click', function(){ try { officerVideo && officerVideo.play(); } catch{} });
    askOfficerShareBtn && askOfficerShareBtn.addEventListener('click', function(){ try { sendSignal('chat', { text: 'Please share your screen.' }); } catch {} });
  });
})();
