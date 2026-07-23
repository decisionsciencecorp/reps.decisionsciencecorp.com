(() => {
  const timer = document.querySelector("[data-rec-timer]");
  if (timer) {
    const start = Date.now();
    const pad = (n) => String(n).padStart(2, "0");
    const tick = () => {
      const s = Math.floor((Date.now() - start) / 1000);
      const hh = pad(Math.floor(s / 3600));
      const mm = pad(Math.floor((s % 3600) / 60));
      const ss = pad(s % 60);
      timer.textContent = `${hh}:${mm}:${ss}`;
    };
    tick();
    window.setInterval(tick, 1000);
  }

  const params = new URLSearchParams(window.location.search);
  const panel = document.querySelector(".apply-panel");
  if (panel && params.get("ok") === "1") {
    const note = document.createElement("p");
    note.className = "apply-alt";
    note.textContent = "Got it — we’ll follow up soon.";
    note.style.color = "var(--lime)";
    panel.insertBefore(note, panel.querySelector("form"));
  }
  if (panel && params.get("err") === "1") {
    const note = document.createElement("p");
    note.className = "apply-alt";
    note.textContent = "Please fill name, phone, email, and path — then try again.";
    note.style.color = "var(--signal)";
    panel.insertBefore(note, panel.querySelector("form"));
  }
})();
