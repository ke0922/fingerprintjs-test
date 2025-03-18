<?php
// 初始化指紋資料陣列
$fingerprint = array();

// 檢查是否有POST資料
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['fingerprint'])) {
    // 解碼JSON資料
    $fingerprint = json_decode($_POST['fingerprint'], true);
    
    // 可以在這裡將指紋資料儲存到資料庫或進行其他處理
    // 例如: saveToDatabase($fingerprint);
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <title>完整瀏覽器指紋收集展示 (含 Canvas 和 IP)</title>
  <style>
    body { font-family: sans-serif; padding: 20px; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; }
    .hidden { display: none; }
  </style>
</head>
<body>
  <h1>完整瀏覽器指紋蒐集展示</h1>
  
  <?php if (empty($fingerprint)): ?>
  <div id="loading">
    <p>正在收集瀏覽器指紋資料，請稍候...</p>
    <pre id="output">載入中...</pre>
  </div>
  <form id="fingerprintForm" method="post" action="" class="hidden">
    <input type="hidden" name="fingerprint" id="fingerprintData">
    <button type="submit">提交指紋資料</button>
  </form>
  <?php else: ?>
  <h2>收集到的指紋資料</h2>
  <pre><?php echo json_encode($fingerprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
  <p><a href="index.php">重新收集</a></p>
  <?php endif; ?>

  <?php if (empty($fingerprint)): ?>
  <script>
    async function collectFingerprint() {
      const output = {};

      // 基本資訊
      output.userAgent = navigator.userAgent;
      output.language = navigator.language;
      output.platform = navigator.platform;
      output.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
      output.colorDepth = screen.colorDepth;
      output.screenResolution = `${screen.width} x ${screen.height}`;
      output.hardwareConcurrency = navigator.hardwareConcurrency;
      output.deviceMemory = navigator.deviceMemory || "不支援";
      output.touchSupport = navigator.maxTouchPoints;
      output.doNotTrack = navigator.doNotTrack;

      // 字型偵測
      output.fonts = detectFonts();

      // WebGL
      output.webgl = getWebGLInfo();

      // AudioContext
      output.audioFingerprint = await getAudioFingerprint();

      // Plugins
      output.plugins = Array.from(navigator.plugins).map(p => p.name);

      // Canvas
      output.canvasFingerprint = getCanvasFingerprint();

      // WebRTC IP
      output.localIPs = await getLocalIPs();
      
      // 顯示結果
      document.getElementById('output').textContent = JSON.stringify(output, null, 2);
      
      // 設定表單資料並提交
      document.getElementById('fingerprintData').value = JSON.stringify(output);
      document.getElementById('loading').style.display = 'none';
      document.getElementById('fingerprintForm').classList.remove('hidden');
      document.getElementById('fingerprintForm').submit();
    }

    // 字型偵測
    function detectFonts() {
      const fonts = ["Arial", "Verdana", "Times New Roman", "Courier New", "Georgia"];
      const span = document.createElement('span');
      span.innerText = 'abcdefghiABCDEFGHI1234567890';
      span.style.fontSize = '72px';
      span.style.position = 'absolute';
      span.style.left = '-9999px';
      document.body.appendChild(span);

      const defaultWidth = span.offsetWidth;
      const detected = [];
      fonts.forEach(font => {
        span.style.fontFamily = font;
        if (span.offsetWidth !== defaultWidth) detected.push(font);
      });
      document.body.removeChild(span);
      return detected;
    }

    // WebGL
    function getWebGLInfo() {
      try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        return {
          vendor: gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL),
          renderer: gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL)
        };
      } catch {
        return "不支援";
      }
    }

    // Audio 指紋
    async function getAudioFingerprint() {
      try {
        const ctx = new (window.OfflineAudioContext || window.webkitOfflineAudioContext)(1, 44100, 44100);
        const oscillator = ctx.createOscillator();
        oscillator.type = 'triangle';
        oscillator.frequency.value = 10000;
        const compressor = ctx.createDynamicsCompressor();
        oscillator.connect(compressor);
        compressor.connect(ctx.destination);
        oscillator.start(0);
        const buffer = await ctx.startRendering();
        return Array.from(buffer.getChannelData(0).slice(0, 30)).map(x => x.toFixed(2));
      } catch {
        return "不支援";
      }
    }

    // Canvas 指紋
    function getCanvasFingerprint() {
      try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font = "14px 'Arial'";
        ctx.fillStyle = '#f60';
        ctx.fillRect(125, 1, 62, 20);
        ctx.fillStyle = '#069';
        ctx.fillText('Hello, fingerprint!', 2, 15);
        ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
        ctx.fillText('世界你好!', 4, 45);
        return canvas.toDataURL();
      } catch {
        return "不支援";
      }
    }

    // WebRTC 本地 IP 探測
    async function getLocalIPs() {
      return new Promise((resolve, reject) => {
        const ips = [];
        try {
          const rtc = new RTCPeerConnection({ iceServers: [] });
          rtc.createDataChannel('');
          rtc.createOffer().then(offer => rtc.setLocalDescription(offer));

          rtc.onicecandidate = (evt) => {
            if (evt.candidate) {
              const regex = /([0-9]{1,3}(\.[0-9]{1,3}){3})/;
              const ipMatch = evt.candidate.candidate.match(regex);
              if (ipMatch && !ips.includes(ipMatch[1])) ips.push(ipMatch[1]);
            } else {
              resolve(ips.length ? ips : '無法獲取');
            }
          };
          
          // 設定超時
          setTimeout(() => {
            resolve(ips.length ? ips : '無法獲取 (超時)');
          }, 5000);
        } catch (e) {
          resolve('不支援');
        }
      });
    }

    // 啟動收集
    collectFingerprint();
  </script>
  <?php endif; ?>
</body>
</html>
