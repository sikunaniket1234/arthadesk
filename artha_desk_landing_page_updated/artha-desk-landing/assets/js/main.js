(() => {
  const menuToggle = document.getElementById("menuToggle");
  const nav = document.getElementById("mainNav");
  menuToggle?.addEventListener("click", () => {
    const isOpen = nav.classList.toggle("open");
    menuToggle.setAttribute("aria-expanded", String(isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "Close navigation" : "Open navigation");
  });
  nav?.querySelectorAll("a").forEach(link => link.addEventListener("click", () => {
    nav.classList.remove("open");
    menuToggle?.setAttribute("aria-expanded", "false");
  }));

  document.getElementById("year").textContent = new Date().getFullYear();

  const modal = document.getElementById("loginModal");
  const closeModal = () => { modal.classList.remove("open"); modal.setAttribute("aria-hidden", "true"); };
  document.getElementById("loginOpen")?.addEventListener("click", () => {
    modal.classList.add("open"); modal.setAttribute("aria-hidden", "false");
    document.getElementById("shopSlug")?.focus();
  });
  document.getElementById("modalClose")?.addEventListener("click", closeModal);
  modal?.addEventListener("click", e => { if (e.target === modal) closeModal(); });
  document.addEventListener("keydown", e => { if (e.key === "Escape") closeModal(); });

  const slug = document.getElementById("shopSlug");
  const preview = document.getElementById("urlPreview");
  slug?.addEventListener("input", () => {
    const value = slug.value.toLowerCase().trim().replace(/[^a-z0-9-]/g, "-").replace(/-+/g, "-");
    preview.textContent = `${value || "yourshop"}.arthadesk.in`;
  });
  document.getElementById("loginForm")?.addEventListener("submit", e => {
    e.preventDefault();
    const value = slug.value.toLowerCase().trim().replace(/[^a-z0-9-]/g, "-").replace(/-+/g, "-").replace(/^-|-$/g, "");
    if (!value) { slug.focus(); return; }
    // Update this route if your tenant login routing differs.
    window.location.href = `https://${value}.arthadesk.in/auth/login`;
  });

  // Keep the language control as a clearly labelled future integration point.
  document.getElementById("languageToggle")?.addEventListener("click", () => {
    alert("Odia interface preview is coming soon. The production language switch will be connected to the translation files.");
  });

  // The lead form posts to the PHP backend endpoint. This script does not fake a successful submission.
  const form = document.getElementById("leadForm");
  form?.addEventListener("submit", e => {
    const phone = form.querySelector('[name="phone"]').value.trim();
    const email = form.querySelector('[name="email"]').value.trim();
    const feedback = document.getElementById("formFeedback");
    if (!phone && !email) {
      e.preventDefault();
      feedback.textContent = "Please provide a phone number or email address so we can contact you.";
      return;
    }
    feedback.textContent = "Sending your enquiry…";
  });
})();