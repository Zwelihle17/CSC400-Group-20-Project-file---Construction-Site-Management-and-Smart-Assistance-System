<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CSMS — Construction Site Management System</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Inter', sans-serif;
    height: 100vh;
    overflow: hidden;
  }

  /* ── FULL PAGE ROOT ── */
  #cp-root {
    width: 100%;
    height: 100vh;
    position: relative;
    overflow: hidden;
  }

  /* ── BACKGROUND using uploaded background.jpg ── */
  #cp-bg {
    position: absolute;
    inset: 0;
    background: url('images/background.jpg') center / cover no-repeat;
    z-index: 0;
  }
  /* Dark overlay over background */
  #cp-bg::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to right,
      rgba(0,0,0,0.88) 0%,
      rgba(0,0,0,0.52) 50%,
      rgba(0,0,0,0.72) 100%);
    z-index: 1;
  }
  /* Warm glow at bottom */
  #cp-bg::after {
    content: '';
    position: absolute;
    bottom: 0; left: 20%; width: 60%; height: 55%;
    background: radial-gradient(ellipse at 50% 100%, rgba(245,120,20,0.28) 0%, transparent 70%);
    z-index: 1;
    pointer-events: none;
  }

  /* ── TWO-COLUMN LAYOUT ── */
  #cp-layout {
    position: relative;
    z-index: 2;
    height: 100%;
    display: grid;
    grid-template-columns: 1fr 300px;
  }

  /* ── LEFT HERO ── */
  #cp-hero {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 36px 40px;
  }

  /* Spinning loader */
  .cp-spinner-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .cp-spinner-inner {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .cp-spinner-label {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    pointer-events: none;
  }
  .cp-spinner-label .cp-csms {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 22px;
    letter-spacing: 4px;
    color: #f0ece3;
    line-height: 1;
    display: block;
  }
  .cp-spinner-label .cp-sub {
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(245,158,11,0.7);
    display: block;
    margin-top: 4px;
    white-space: nowrap;
  }

  /* Tabler-style dual-ring spinner */
  .loader {
    width: 10em;
    height: 10em;
    font-size: 16px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .loader .face {
    position: absolute;
    border-radius: 50%;
    border-style: solid;
    animation: loaderSpin 3s linear infinite;
  }
  .loader .face:nth-child(1) {
    width: 100%; height: 100%;
    color: gold;
    border-color: currentColor transparent transparent currentColor;
    border-width: 0.2em 0.2em 0 0;
    --deg: -45deg;
    animation-direction: normal;
  }
  .loader .face:nth-child(2) {
    width: 70%; height: 70%;
    color: #ffe000;
    border-color: currentColor currentColor transparent transparent;
    border-width: 0.2em 0 0 0.2em;
    --deg: -135deg;
    animation-direction: reverse;
  }
  .loader .face .circle {
    position: absolute;
    width: 50%; height: 0.1em;
    top: 50%; left: 50%;
    background: transparent;
    transform: rotate(var(--deg));
    transform-origin: left;
  }
  .loader .face .circle::before {
    position: absolute;
    top: -0.5em; right: -0.5em;
    content: '';
    width: 1em; height: 1em;
    background-color: currentColor;
    border-radius: 50%;
    box-shadow: 0 0 2em, 0 0 4em, 0 0 6em, 0 0 8em, 0 0 10em,
                0 0 0 0.5em rgba(255,255,0,0.1);
  }
  .loader .face:nth-child(2) .circle::before {
    background-color: lime;
    box-shadow: 0 0 2em lime, 0 0 4em lime, 0 0 6em lime, 0 0 8em lime,
                0 0 10em lime, 0 0 0 0.5em rgba(0,255,0,0.15);
  }
  @keyframes loaderSpin { to { transform: rotate(1turn); } }

  /* Bottom hero text */
  .cp-hero-bottom { flex-shrink: 0; }
  .cp-desc {
    font-size: 12px;
    font-weight: 300;
    color: rgba(240,236,227,0.48);
    line-height: 1.75;
    max-width: 320px;
  }
  .cp-pills { display: flex; gap: 7px; margin-top: 20px; flex-wrap: wrap; }
  .cp-pill {
    padding: 4px 11px;
    border: 1px solid rgba(245,158,11,0.30);
    border-radius: 2px;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(245,158,11,0.65);
  }

  /* ── RIGHT LOGIN PANEL ── */
  #cp-panel {
    background: rgba(6,6,6,0.82);
    backdrop-filter: blur(20px);
    border-left: 1px solid rgba(245,158,11,0.20);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 32px 28px;
    overflow-y: auto;
  }

  .cp-logo {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 24px;
    letter-spacing: 5px;
    font-weight: 900;
    color: #f59e0b;
    line-height: 1;
  }
  .cp-tagline {
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: #4a4035;
    margin-top: 3px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
  }

  /* Error box — shown by PHP on bad credentials */
  .cp-err {
    background: rgba(239,68,68,0.10);
    border: 1px solid rgba(239,68,68,0.35);
    color: #f87171;
    font-size: 11px;
    padding: 8px 12px;
    border-radius: 3px;
    margin-bottom: 14px;
    display: none;
  }
  .cp-err.show { display: block; }

  .cp-label {
    display: block;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: #4a4035;
    margin-bottom: 5px;
  }
  .cp-input {
    width: 100%;
    padding: 10px 13px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 3px;
    color: #f0ece3;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    outline: none;
    transition: border-color .2s, background .2s;
    margin-bottom: 14px;
  }
  .cp-input:focus {
    border-color: #f59e0b;
    background: rgba(245,158,11,0.04);
  }
  .cp-input::placeholder { color: rgba(255,255,255,0.18); }

  .cp-btn {
    width: 100%;
    padding: 12px;
    background: #f59e0b;
    color: #0c0c0c;
    border: none;
    border-radius: 3px;
    font-family: 'Bebas Neue', sans-serif;
    font-size: 16px;
    letter-spacing: 4px;
    cursor: pointer;
    transition: background .2s, transform .1s;
    margin-top: 4px;
  }
  .cp-btn:hover  { background: #fcd34d; }
  .cp-btn:active { transform: scale(.99); }

  /* ── FOOTER ── */
  .cp-footer {
    position: fixed;
    bottom: 12px; left: 50%;
    transform: translateX(-50%);
    font-size: 9px;
    color: rgba(255,255,255,0.13);
    letter-spacing: 2px;
    text-transform: uppercase;
    white-space: nowrap;
    z-index: 3;
  }

  /* ── RESPONSIVE ── */
  @media (max-width: 680px) {
    #cp-layout { grid-template-columns: 1fr; }
    #cp-hero    { display: none; }
    #cp-panel   { padding: 36px 28px; }
  }
</style>
</head>
<body>

<div id="cp-root">
  <div id="cp-bg"></div>

  <div id="cp-layout">

    <!-- LEFT: animated spinner + description -->
    <div id="cp-hero">
      <div class="cp-spinner-wrap">
        <div class="cp-spinner-inner">
          <div class="loader">
            <div class="face"><div class="circle"></div></div>
            <div class="face"><div class="circle"></div></div>
          </div>
          <div class="cp-spinner-label">
            <span class="cp-csms">CSMS</span>
            <span class="cp-sub">Eswatini Construction</span>
          </div>
        </div>
      </div>

      <div class="cp-hero-bottom">
        <div class="cp-desc">A centralized, role-based construction site management and smart assistance system designed for Eswatini's construction industry.</div>
        <div class="cp-pills">
          <span class="cp-pill">Workforce</span>
          <span class="cp-pill">Safety</span>
          <span class="cp-pill">Reports</span>
          <span class="cp-pill">Real-Time</span>
          <span class="cp-pill">Admin Control</span>
        </div>
      </div>
    </div>

    <!-- RIGHT: PHP login form -->
    <div id="cp-panel">
      <div class="cp-logo">CSMS</div>
      <div class="cp-tagline">Construction Management System v3</div>

      <!-- Error shown when PHP redirects back with ?error=1 -->
      <div class="cp-err <?php echo (isset($_GET['error']) && $_GET['error']==='1') ? 'show' : ''; ?>" id="cp-err-box">
        Invalid Employee ID or Password.
      </div>

      <!-- Form submits to PHP login handler -->
      <form action="php/login.php" method="POST">
        <label class="cp-label">Employee ID</label>
        <input class="cp-input" type="text" name="employee_id" placeholder="e.g. AD001" required autocomplete="off">

        <label class="cp-label">Password</label>
        <input class="cp-input" type="password" name="password" placeholder="••••••••" required>

        <button type="submit" class="cp-btn">Access System</button>
      </form>
    </div>

  </div><!-- /cp-layout -->

  <div class="cp-footer">CSMS v3 · Eswatini</div>
</div><!-- /cp-root -->

</body>
</html>
