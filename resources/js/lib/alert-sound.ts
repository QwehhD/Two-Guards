// A short attention beep for a new pending scan on the Approvals page
// (Tahap 8 — deferred from Tahap 6, since it only makes sense once new
// scans arrive via a live push instead of a polling tick).
//
// Synthesized with the Web Audio API instead of shipping an audio file:
// there's no existing sound asset in this project, and a couple of
// oscillator beeps avoid adding a binary file (and its licensing) just
// for this.
let sharedContext: AudioContext | null = null;

function getAudioContext(): AudioContext | null {
    if (typeof window === 'undefined' || !window.AudioContext) return null;

    return (sharedContext ??= new AudioContext());
}

export function playAlertSound(): void {
    const context = getAudioContext();
    if (!context) return;

    // Browsers suspend a fresh/backgrounded AudioContext until it's
    // resumed from a user gesture; a scan alert can legitimately fire
    // while the tab is merely open (not just-clicked), so this is a
    // best-effort resume rather than something we can guarantee works.
    void context.resume();

    const now = context.currentTime;

    [880, 1046.5].forEach((frequency, index) => {
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(frequency, now);

        const start = now + index * 0.15;
        const end = start + 0.12;

        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(0.2, start + 0.01);
        gain.gain.linearRampToValueAtTime(0, end);

        oscillator.connect(gain).connect(context.destination);
        oscillator.start(start);
        oscillator.stop(end);
    });
}
