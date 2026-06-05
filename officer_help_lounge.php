<?php
require_once 'includes/db.php';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Officer Help Lounge (Prototype)</title>
  <link rel="stylesheet" href="css/tactical-theme.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body{background:#0b1220;color:#e5e7eb;}
    .container{max-width:980px;margin:24px auto;padding:0 16px;}
    .card{background:#111827;border:1px solid rgba(255,255,255,.08);border-radius:12px;box-shadow:0 12px 28px rgba(0,0,0,.4);}
    .card-header{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-between}
    .card-body{padding:16px}
    .session-row{display:grid;grid-template-columns:140px 1fr 140px;align-items:center;gap:12px;padding:10px 0;border-bottom:1px dashed rgba(255,255,255,.08)}
    .session-row:last-child{border-bottom:0}
    .code{font-family:monospace;background:#1f2937;border:1px solid rgba(255,255,255,.08);padding:.35rem .6rem;border-radius:8px}
    .btn{padding:.45rem .8rem;border-radius:8px;border:1px solid rgba(255,255,255,.15);cursor:pointer}
    .btn-primary{background:linear-gradient(135deg,#00e673,#00c765);color:#0f1a12}
    .viewer{margin-top:16px}
    #remoteVideo{width:100%;max-height:48vh;background:#0b1220;border:1px solid rgba(255,255,255,.08);border-radius:8px}
    .chat{margin-top:12px}
    .chat-box{height:160px;overflow:auto;background:#0e1526;border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:8px}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h2 style="margin:0;font-size:1.1rem"><i class="fas fa-headset"></i> Officer Help Lounge (Prototype)</h2>
        <a href="register_help_prototype.php" class="btn">Open student prototype</a>
      </div>
      <div class="card-body">
        <p class="text-muted" style="margin-top:0">Waiting for students requesting help...</p>
        <div id="pendingList"></div>
      </div>
    </div>
    <div class="card viewer">
      <div class="card-header">
        <h3 style="margin:0;font-size:1rem"><i class="fas fa-display"></i> Remote Screen</h3>
        <div id="viewerStatus" class="text-muted" style="font-size:.9rem">Idle</div>
      </div>
      <div class="card-body">
        <video id="remoteVideo" autoplay playsinline controls muted></video>
        <div style="margin-top:.5rem;display:flex;gap:.5rem;flex-wrap:wrap">
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
  <script src="js/help-officer.js?v=<?php echo time(); ?>"></script>
</body>
</html>
