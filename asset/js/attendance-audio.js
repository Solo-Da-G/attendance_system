/**
 * TDS ATTENDANCE AUDIO SYSTEM
 * Synthesizes loud, crystal-clear, professional chimes using the native Web Audio API
 * and Web Speech synthesis. 100% offline, zero latency, no external mp3 files.
 */
(function (global) {
  let audioCtx = null;
  let isUnlocked = false;

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

  // Force unlock audio hardware immediately upon user touch/click
  function unlock() {
    try {
      const ctx = getAudioContext();
      if (!ctx) return;
      if (ctx.state === 'suspended') {
        ctx.resume();
      }
      // Play a 1-sample silent sound synchronously to permanently unlock the audio session on mobile
      const buffer = ctx.createBuffer(1, 1, 22050);
      const source = ctx.createBufferSource();
      source.buffer = buffer;
      source.connect(ctx.destination);
      source.start(0);
      isUnlocked = true;
    } catch (_) {}
  }

  // Auto-listen to global gestures to unlock early
  ['click', 'touchstart', 'touchend', 'pointerdown'].forEach((evt) => {
    window.addEventListener(evt, () => unlock(), { once: true, passive: true });
  });

  // Speech helper for crystal clear audible confirmation
  function speakConfirmation(text) {
    try {
      if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        const utter = new SpeechSynthesisUtterance(text);
        utter.rate = 1.05;
        utter.pitch = 1.0;
        utter.volume = 1.0;
        window.speechSynthesis.speak(utter);
      }
    } catch (_) {}
  }

  const TDSAudio = {
    unlock,

    /**
     * Professional Loud Clock-In Chime
     * Crisp 4-note uplifting arpeggio: C5 -> E5 -> G5 -> C6 with harmonic overtones
     */
    playClockIn() {
      unlock();
      const ctx = getAudioContext();
      if (!ctx) return;
      const now = ctx.currentTime;

      // Master output gain
      const master = ctx.createGain();
      master.gain.setValueAtTime(0.85, now);
      master.connect(ctx.destination);

      // Uplifting arpeggio: C5 (523Hz) -> E5 (659Hz) -> G5 (784Hz) -> C6 (1046Hz)
      const notes = [
        { f: 523.25, time: 0.00, dur: 0.25 },
        { f: 659.25, time: 0.12, dur: 0.28 },
        { f: 783.99, time: 0.24, dur: 0.35 },
        { f: 1046.50, time: 0.38, dur: 0.75 }
      ];

      notes.forEach((n) => {
        // Fundamental oscillator (sine)
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(n.f, now + n.time);

        // Harmonic overtone (triangle) for richness on small phone speakers
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(n.f * 2, now + n.time);

        gain.gain.setValueAtTime(0.001, now + n.time);
        gain.gain.linearRampToValueAtTime(0.7, now + n.time + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, now + n.time + n.dur);

        gain2.gain.setValueAtTime(0.001, now + n.time);
        gain2.gain.linearRampToValueAtTime(0.25, now + n.time + 0.02);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + n.time + n.dur);

        osc.connect(gain);
        gain.connect(master);

        osc2.connect(gain2);
        gain2.connect(master);

        osc.start(now + n.time);
        osc.stop(now + n.time + n.dur);

        osc2.start(now + n.time);
        osc2.stop(now + n.time + n.dur);
      });

      // Voice confirmation after 400ms
      setTimeout(() => speakConfirmation('Clock in successful'), 400);
    },

    /**
     * Professional Loud Clock-Out Chime
     * Warm 3-stage resolution chime: G5 -> E5 -> C5 with harmonic warmth
     */
    playClockOut() {
      unlock();
      const ctx = getAudioContext();
      if (!ctx) return;
      const now = ctx.currentTime;

      const master = ctx.createGain();
      master.gain.setValueAtTime(0.85, now);
      master.connect(ctx.destination);

      // Resolution chime: G5 (784Hz) -> E5 (659Hz) -> C5 (523Hz)
      const notes = [
        { f: 783.99, time: 0.00, dur: 0.28 },
        { f: 659.25, time: 0.15, dur: 0.32 },
        { f: 523.25, time: 0.30, dur: 0.85 }
      ];

      notes.forEach((n) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(n.f, now + n.time);

        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(n.f * 1.5, now + n.time);

        gain.gain.setValueAtTime(0.001, now + n.time);
        gain.gain.linearRampToValueAtTime(0.65, now + n.time + 0.03);
        gain.gain.exponentialRampToValueAtTime(0.001, now + n.time + n.dur);

        gain2.gain.setValueAtTime(0.001, now + n.time);
        gain2.gain.linearRampToValueAtTime(0.25, now + n.time + 0.03);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + n.time + n.dur);

        osc.connect(gain);
        gain.connect(master);

        osc2.connect(gain2);
        gain2.connect(master);

        osc.start(now + n.time);
        osc.stop(now + n.time + n.dur);

        osc2.start(now + n.time);
        osc2.stop(now + n.time + n.dur);
      });

      // Voice confirmation after 400ms
      setTimeout(() => speakConfirmation('Clock out successful'), 400);
    },

    /**
     * Quick Success Chime (Login / Enrollment)
     */
    playSuccess() {
      unlock();
      const ctx = getAudioContext();
      if (!ctx) return;
      const now = ctx.currentTime;

      const master = ctx.createGain();
      master.gain.setValueAtTime(0.85, now);
      master.connect(ctx.destination);

      [
        { f: 587.33, time: 0.00, dur: 0.18 },
        { f: 880.00, time: 0.12, dur: 0.50 }
      ].forEach((n) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(n.f, now + n.time);
        gain.gain.setValueAtTime(0.001, now + n.time);
        gain.gain.linearRampToValueAtTime(0.6, now + n.time + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, now + n.time + n.dur);
        osc.connect(gain);
        gain.connect(master);
        osc.start(now + n.time);
        osc.stop(now + n.time + n.dur);
      });
    },

    /**
     * Subtle Error Sound
     */
    playError() {
      unlock();
      const ctx = getAudioContext();
      if (!ctx) return;
      const now = ctx.currentTime;

      const master = ctx.createGain();
      master.gain.setValueAtTime(0.7, now);
      master.connect(ctx.destination);

      [
        { f: 220, time: 0.00, dur: 0.15 },
        { f: 175, time: 0.16, dur: 0.30 }
      ].forEach((n) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(n.f, now + n.time);
        gain.gain.setValueAtTime(0.001, now + n.time);
        gain.gain.linearRampToValueAtTime(0.5, now + n.time + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, now + n.time + n.dur);
        osc.connect(gain);
        gain.connect(master);
        osc.start(now + n.time);
        osc.stop(now + n.time + n.dur);
      });
    }
  };

  global.TDSAudio = TDSAudio;
})(window);