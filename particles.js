// Monochrome waterfall of falling particles on a single Canvas 2D layer.
export function startParticles(canvas) {
  const ctx = canvas && canvas.getContext ? canvas.getContext('2d') : null;
  if (!ctx) return { destroy() {} };   // graceful fallback: the plain dark background remains

  const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
  const BUCKETS = 5;
  const rand = (a, b) => a + Math.random() * (b - a);
  let w = 0, h = 0, dpr = 1, parts = [], raf = 0, last = 0, running = false, resizeRaf = 0;

  const make = (initial) => ({
    x: rand(0, w), y: initial ? rand(0, h) : rand(-60, -4),
    r: rand(0.5, 1.6), v: rand(26, 120), a: rand(0.12, 0.62),
    drift: rand(-5, 5), ph: rand(0, 6.28), fq: rand(0.12, 0.45),
  });

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    w = window.innerWidth; h = window.innerHeight;
    canvas.width = Math.round(w * dpr); canvas.height = Math.round(h * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const target = Math.min(150, Math.round((w * h) / (w < 720 ? 12000 : 14000)));
    while (parts.length < target) parts.push(make(true));
    parts.length = Math.min(parts.length, target);
    for (const p of parts) if (p.x > w) p.x = rand(0, w);
    if (!running) draw();
  }

  function step(dt, t) {
    for (const p of parts) {
      p.y += p.v * dt;
      p.x += Math.sin(t * p.fq + p.ph) * p.drift * dt;
      if (p.y > h + 20) Object.assign(p, make(false));
    }
  }

  function draw() {
    ctx.clearRect(0, 0, w, h);
    for (let b = 0; b < BUCKETS; b++) {
      const gray = 170 + b * 21;
      ctx.fillStyle = `rgba(${gray},${gray},${gray},${(0.16 + b * 0.12).toFixed(2)})`;
      ctx.beginPath();
      for (const p of parts) {
        if (Math.min(BUCKETS - 1, Math.floor(p.a * BUCKETS / 0.65)) !== b) continue;
        const len = p.r * (1.5 + p.v / 28);
        ctx.rect(p.x, p.y, p.r * 0.9, len);
      }
      ctx.fill();
    }
  }

  function frame(ts) {
    if (!running) return;
    const dt = Math.min(0.05, (ts - last) / 1000 || 0.016);
    last = ts;
    step(dt, ts / 1000);
    draw();
    raf = requestAnimationFrame(frame);
  }
  const start = () => { if (running || mq.matches || document.hidden) return; running = true; last = performance.now(); raf = requestAnimationFrame(frame); };
  const stop = () => { running = false; cancelAnimationFrame(raf); };

  const onResize = () => { cancelAnimationFrame(resizeRaf); resizeRaf = requestAnimationFrame(resize); };
  const onVisibility = () => (document.hidden ? stop() : start());
  const onMotion = () => { if (mq.matches) { stop(); draw(); } else start(); };

  resize();
  window.addEventListener('resize', onResize);
  document.addEventListener('visibilitychange', onVisibility);
  mq.addEventListener('change', onMotion);
  start();

  return {
    destroy() {
      stop(); cancelAnimationFrame(resizeRaf);
      window.removeEventListener('resize', onResize);
      document.removeEventListener('visibilitychange', onVisibility);
      mq.removeEventListener('change', onMotion);
    },
  };
}
