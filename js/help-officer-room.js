// Officer Help Room (Prototype) - dedicated per-session page
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const params = window.__HELP_ROOM__ || { sid:0, token:'' };
    const remoteVideo = document.getElementById('remoteVideo');
    const roomStatus = document.getElementById('roomStatus');
    const shareScreenBtn = document.getElementById('shareScreenBtn');
    const micToggle = document.getElementById('micToggle');
    const hangupBtn = document.getElementById('hangupBtn');
    const chatMessagesOff = document.getElementById('chatMessagesOff');
    const chatInputOff = document.getElementById('chatInputOff');
    const chatSendOff = document.getElementById('chatSendOff');

    const rtcConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
    let session = { id: params.sid, token: params.token, since_id: 0 };
    let pc = null;
    let micStream = null;
    let screenStream = null;
    let pollAbort = false;

    function setStatus(msg){ if (roomStatus) roomStatus.textContent = msg; }
    if (!session.id || !session.token) { setStatus('Missing session token'); return; }

    async function startOfficerRTC(){
      pc = new RTCPeerConnection(rtcConfig);
      // Pre-create receiving transceivers improves iOS mobile compatibility
      try { pc.addTransceiver('video', {direction:'recvonly'}); } catch {}
      try { pc.addTransceiver('audio', {direction:'recvonly'}); } catch {}
      pc.onicecandidate = async (ev) => {
        if (ev.candidate && session) {
          await sendSignal('candidate', { candidate: ev.candidate });
        }
      };
      pc.ontrack = (ev) => {
        try {
          if (remoteVideo && ev.streams && ev.streams[0]) {
            remoteVideo.srcObject = ev.streams[0];
            remoteVideo.setAttribute('playsinline','');
            remoteVideo.setAttribute('webkit-playsinline','');
            remoteVideo.muted = true; // allow autoplay; officer can unmute
            remoteVideo.play().catch(()=>{});
          }
        } catch {}
      };
      pc.onconnectionstatechange = () => setStatus('Connection: ' + pc.connectionState);

      try {
        micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        micStream.getTracks().forEach(t => pc.addTrack(t, micStream));
      } catch {}
    }

    async function sendSignal(type, payload){
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
        await fetch('api/help/abandon_session.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ session_id: session.id, role: 'officer', token: session.token })
        });
      } catch {}
      finally {
        if (pc) { try { pc.getSenders().forEach(s=>{try{s.track && s.track.stop();}catch{}}); pc.close(); } catch {} pc = null; }
        if (micStream) { try { micStream.getTracks().forEach(t=>t.stop()); } catch {} micStream = null; }
        if (screenStream) { try { screenStream.getTracks().forEach(t=>t.stop()); } catch {} screenStream = null; }
        session = null;
        setStatus('Ended');
        if (remoteVideo) remoteVideo.srcObject = null;
        window.close();
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
        screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
        screenStream.getTracks().forEach(t => pc.addTrack(t, screenStream));
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await sendSignal('offer', { sdp: pc.localDescription });
        setStatus('Sharing screen to student...');
        screenStream.getVideoTracks().forEach(track => track.addEventListener('ended', async ()=>{
          try {
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

    // Start
    (async function(){
      setStatus('Waiting for offer...');
      await startOfficerRTC();
      startPoll();
    })();
  });
})();
