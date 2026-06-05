<?php
require_once 'includes/db.php';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Officer Help Room (Prototype)</title>
  <link rel="stylesheet" href="css/tactical-theme.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body{background:#0b1220;color:#e5e7eb;}
    .container{max-width:980px;margin:24px auto;padding:0 16px;}
    .card{background:#111827;border:1px solid rgba(255,255,255,.08);border-radius:12px;box-shadow:0 12px 28px rgba(0,0,0,.4);}
    .card-header{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between}
    .card-body{padding:16px}
    #remoteVideo{width:100%;max-height:60vh;background:#0b1220;border:1px solid rgba(255,255,255,.08);border-radius:8px}
    .chat{margin-top:12px}
    .chat-box{height:180px;overflow:auto;background:#0e1526;border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:8px}
    .controls{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.5rem}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem"><i class="fas fa-door-open"></i> Officer Help Room</h2>
        <div id="roomStatus" class="text-muted" style="font-size:.9rem">Initializing...</div>
      </div>
      <div class="card-body">
        <video id="remoteVideo" autoplay playsinline controls muted></video>
        <div class="controls">
          <button id="shareScreenBtn" class="btn btn-primary">Share Screen</button>
          <button id="micToggle" class="btn">Toggle Mic</button>
          <button id="hangupBtn" class="btn">Hang Up</button>
        </div>
        <div class="chat">
          <div style="display:flex; align-items:center; justify-content:space-between; margin:.5rem 0;">
            <strong><i class="fas fa-comments"></i> Chat</strong>
            <small class="text-muted">Prototype</small>
          </div>
          <div id="chatMessagesOff" class="chat-box"></div>
          <div style="display:flex; gap:.5rem; margin-top:.5rem;">
            <input id="chatInputOff" type="text" maxlength="500" class="form-control" placeholder="Type a message" />
            <button id="chatSendOff" type="button" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    // Expose sid/token from query string for the room script
    (function(){
      const params = new URLSearchParams(window.location.search);
      window.__HELP_ROOM__ = {
        sid: parseInt(params.get('sid')||'0',10) || 0,
        token: params.get('token') || ''
      };
    })();
  </script>
  <script src="js/help-officer-room.js?v=<?php echo time(); ?>"></script>
</body>
</html>
