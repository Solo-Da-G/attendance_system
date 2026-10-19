/**
 * TDS ATTENDANCE AUDIO SYSTEM
 * Synthesizes crystal-clear, professional chimes using the native Web Audio API.
 * Requires 0 external audio files (no 404s, works offline in PWA, zero latency).
 */
(function (global) {
  let audioCtx = null;

  function getAudioContext() {
    if (!audioCtx) {
      const AudioContextClass = window.AudioContext || window.webkitAudioContext;
      if (AudioContextClass) {
        audioCtx = new AudioContextClass();
      }
    }
    if (audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume().catch(() => {});
    }
    return audioCtx;
  }

  // Pre-unlock audio context on any early user touch or click
  ['click', 'touchstart', 'keydown'].forEach((evt) => {
    window.addEventListener(evt, () => getAudioContext(), { once: true, passive: true });
  });

  const TDSAudio = {
    /**
     * Professional Clock-In Sound
     * Uplifting 3-note crystal chime: C5 (523Hz) -> E5 (659Hz) -> G5 (784Hz)
     */
    playClockIn() {
      const ctx = getAudioContext();
      if (!ctx) return;
      const now = ctx.currentTime;

      const notes = [
        { f: 523.25, time: 0.00, dur: 0.22, vol: 0.28 },
        { f: 659.25, time: 0.12, dur: 0.26, vol: 0.32 },
        { f: 783.99, time: 0.25, dur: 0.55, vol: 0.35 }
      ];

      notes.forEach((n) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(n.f, now + n.time);

        // Smooth natural attack and exponential decay
        gain.gain.setValueAtTime(0.0001, now + n.time);
        gain.gain.exponentialRampToValueAtTime(n.vol, now + n.time + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + n.time + n.dur);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now + n.time);
        osc.stop(now + n.time + n.dur);
      });
    },

    /**
     * Professional Clock-Out Sound
     * Warm, calming 2-tone resolution chime: G5 (784Hz) -> C5 (523Hz)
     */
    playClockOut() {
      const ctx = getAudioContext();
      if (!ctx) return;
      const now = ctx.currentTime;

      const notes = [
        { f: 783.99, time: 0.00, dur: 0.26, vol: 0.26, type: 'triangle' },
        { f: 523.25, time: 0.18, dur: 0.65, vol: 0.30, type: 'sine' }
      ];

      notes.forEach((n) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = n.type || 'sine';
        osc.frequency.setValueAtTime(n.f, now + n.time);

        gain.gain.setValueAtTime(0.0001, now + n.time);
        gain.gain.exponentialRampToValueAtTime(n.vol, now + n.time + 0.03);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + n.time + n.dur);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now + n.time);
        osc.stop(now + n.time + n.dur);
      });
    },

    /**
     * Quick Success Confirmation Chime (Login / Verified)
     */
    playSuccess() {
      const ctx = getAudioContext();
      if (!ctx) return;
      const now = ctx.currentTime;

      [
        { f: 587.33, time: 0.00, dur: 0.16, vol: 0.22 },
        { f: 880.00, time: 0.09, dur: 0.35, vol: 0.28 }
      ].forEach((n) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(n.f, now + n.time);
        gain.gain.setValueAtTime(0.0001, now + n.time);
        gain.gain.exponentialRampToValueAtTime(n.vol, now + n.time + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + n.time + n.dur);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now + n.time);
        osc.stop(now + n.time + n.dur);
      });
    },

    /**
     * Subtle Error Sound
     */
    playError() {
      const ctx = getAudioContext();
      if (!ctx) return;
      const now = ctx.currentTime;

      [
        { f: 220, time: 0.00, dur: 0.12, vol: 0.2 },
        { f: 180, time: 0.14, dur: 0.22, vol: 0.2 }
      ].forEach((n) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(n.f, now + n.time);
        gain.gain.setValueAtTime(0.0001, now + n.time);
        gain.gain.exponentialRampToValueAtTime(n.vol, now + n.time + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + n.time + n.dur);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now + n.time);
        osc.stop(now + n.time + n.dur);
      });
    }
  };

  global.TDSAudio = TDSAudio;
})(window);
