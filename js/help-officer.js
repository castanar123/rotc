// Officer Help Lounge (Prototype)
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const listEl = document.getElementById('pendingList');
    const remoteVideo = document.getElementById('remoteVideo');
    const viewerStatus = document.getElementById('viewerStatus');
    const micToggle = document.getElementById('micToggle');
    const hangupBtn = document.getElementById('hangupBtn');
    const shareScreenBtn = document.getElementById('shareScreenBtn');
    const chatMessagesOff = document.getElementById('chatMessagesOff');
    const chatInputOff = document.getElementById('chatInputOff');
    const chatSendOff = document.getElementById('chatSendOff');

    const rtcConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
    let session = null; // { id, token, since_id }
    let pc = null;
    let micStream = null;
    let screenStream = null;
    let pollAbort = false;

    function setStatus(msg){ if (viewerStatus) viewerStatus.textContent = msg; }

    async function load() {
      try {
        const res = await fetch('api/help/list_pending.php');
        const data = await res.json();
        if (!res.ok || !data || !data.success) return;
        listEl.innerHTML = '';
        (data.sessions || []).forEach(s => {
          const row = document.createElement('div');
          row.className = 'session-row';
          row.innerHTML = `
            <span class="code">${s.join_code}</span>
            <span class="time">${(s.created_at || '').replace('T',' ')}</span>
            <button class="btn btn-primary btn-sm" data-code="${s.join_code}"><i class="fas fa-sign-in-alt"></i> Join</button>
          `;
          listEl.appendChild(row);
        });
      } catch (e) {}
    }

    listEl && listEl.addEventListener('click', async (ev) => {
      const btn = ev.target.closest('button[data-code]');
      if (!btn) return;
      const join_code = btn.getAttribute('data-code');
      try {
        const res = await fetch('api/help/officer_accept.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ join_code })
        });
        const data = await res.json();
        if (res.ok && data && data.success) {
          // Open room in new tab so lounge can keep listing others
          const url = `officer_help_room.php?sid=${encodeURIComponent(data.session_id)}&token=${encodeURIComponent(data.officer_token)}`;
          window.open(url, '_blank');
        } else {
          alert((data && data.error) ? data.error : 'Failed to join session');
        }
      } catch (e) {
        alert('Network error while joining');
      }
    });

    async function startOfficerRTC(){
      pc = new RTCPeerConnection(rtcConfig);
      pc.onicecandidate = async (ev) => {
        if (ev.candidate && session) {
          await sendSignal('candidate', { candidate: ev.candidate });
        }
      };
      pc.ontrack = (ev) => {
        try {
          if (remoteVideo && ev.streams && ev.streams[0]) {
            remoteVideo.srcObject = ev.streams[0];
          }
        } catch {}
      };
      pc.onconnectionstatechange = () => setStatus('Connection: ' + pc.connectionState);

      // Two-way audio (optional): capture officer mic
      try {
        micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        micStream.getTracks().forEach(t => pc.addTrack(t, micStream));
      } catch {}
    }

    async function sendSignal(type, payload){
      if (!session) return;
      await fetch('api/help/send_signal.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: session.id, role: 'officer', token: session.token, type, payload })
      });
    }

    async function startPoll(){
      pollAbort = false;
      while (session && !pollAbort) {
        try {
          const res = await fetch('api/help/poll_signals.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: session.id, role: 'officer', token: session.token, since_id: session.since_id || 0 })
          });
          const data = await res.json();
          if (res.ok && data && data.success && Array.isArray(data.signals)) {
            for (const sig of data.signals) {
              session.since_id = Math.max(session.since_id || 0, sig.id);
              if (sig.type === 'offer' && sig.payload && sig.payload.sdp && pc) {
                await pc.setRemoteDescription(new RTCSessionDescription(sig.payload.sdp));
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await sendSignal('answer', { sdp: pc.localDescription });
                setStatus('Answer sent. Finalizing connection...');
              }
              if (sig.type === 'answer' && sig.payload && sig.payload.sdp && pc) {
                // Student answered our renegotiation (e.g., officer screen share)
                await pc.setRemoteDescription(new RTCSessionDescription(sig.payload.sdp));
                setStatus('Officer screen shared to student.');
              }
              if (sig.type === 'candidate' && pc && sig.payload && sig.payload.candidate) {
                try { await pc.addIceCandidate(new RTCIceCandidate(sig.payload.candidate)); } catch(e) { /* ignore */ }
              }
              if (sig.type === 'chat' && sig.payload && typeof sig.payload.text === 'string') {
                appendChat('Student', sig.payload.text);
              }
            }
          }
        } catch (e) {
          // ignore transient errors
        }
      }
    }

    async function hangup(){
      pollAbort = true;
      try {
        if (session) {
          await fetch('api/help/abandon_session.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: session.id, role: 'officer', token: session.token })
          });
        }
      } catch {}
      finally {
        if (pc) { try { pc.getSenders().forEach(s=>{try{s.track && s.track.stop();}catch{}}); pc.close(); } catch {} pc = null; }
        if (micStream) { try { micStream.getTracks().forEach(t=>t.stop()); } catch {} micStream = null; }
        if (screenStream) { try { screenStream.getTracks().forEach(t=>t.stop()); } catch {} screenStream = null; }
        session = null;
        setStatus('Idle');
        if (remoteVideo) remoteVideo.srcObject = null;
      }
    }

    function appendChat(sender, text){
      if (!chatMessagesOff) return;
      const line = document.createElement('div');
      line.innerHTML = `<strong>${escapeHtml(sender)}:</strong> ${escapeHtml(text)}`;
      chatMessagesOff.appendChild(line);
      chatMessagesOff.scrollTop = chatMessagesOff.scrollHeight;
    }
    function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c])); }
    async function sendChat(){
      if (!session || !chatInputOff) return;
      const txt = (chatInputOff.value||'').trim();
      if (!txt) return;
      chatInputOff.value = '';
      appendChat('You', txt);
      try { await sendSignal('chat', { text: txt, ts: Date.now() }); } catch {}
    }

    async function shareScreen(){
      try {
        if (!pc) return;
        // Capture officer screen (no audio here; mic is from micStream already)
        screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        screenStream.getTracks().forEach(t => pc.addTrack(t, screenStream));
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await sendSignal('offer', { sdp: pc.localDescription });
        setStatus('Sharing screen to student...');
        // When officer stops sharing, renegotiate to remove tracks
        screenStream.getVideoTracks().forEach(track => track.addEventListener('ended', async ()=>{
          try {
            // Remove senders that use ended track
            pc.getSenders().forEach(s=>{ if (s.track && s.track.kind==='video' && !s.track.enabled) { try{s.replaceTrack(null);}catch{} } });
            const off2 = await pc.createOffer();
            await pc.setLocalDescription(off2);
            await sendSignal('offer', { sdp: pc.localDescription });
            setStatus('Stopped sharing screen.');
          } catch {}
        }));
      } catch (e) {
        // ignore
      }
    }

    micToggle && micToggle.addEventListener('click', function(){
      try {
        if (!micStream) return;
        micStream.getAudioTracks().forEach(t => t.enabled = !t.enabled);
        this.textContent = (micStream.getAudioTracks()[0]?.enabled ? 'Mute Mic' : 'Unmute Mic');
      } catch {}
    });
    hangupBtn && hangupBtn.addEventListener('click', hangup);
    shareScreenBtn && shareScreenBtn.addEventListener('click', shareScreen);
    chatSendOff && chatSendOff.addEventListener('click', sendChat);
    chatInputOff && chatInputOff.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); sendChat(); }});

    load();
    setInterval(load, 5000);
  });
})();
