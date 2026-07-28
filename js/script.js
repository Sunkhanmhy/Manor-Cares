/* ==========================================================================
   Manor Cares — shared JS (theme toggle, nav, reveal, faq, calculator, forms)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {

  /* ---------- Theme toggle (persisted) ---------- */
  const root = document.documentElement;
  const themeBtn = document.getElementById('themeToggle');
  const savedTheme = localStorage.getItem('manor-theme') || 'light';
  root.setAttribute('data-theme', savedTheme);
  updateThemeIcon(savedTheme);

  if(themeBtn){
    themeBtn.addEventListener('click', () => {
      const current = root.getAttribute('data-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      localStorage.setItem('manor-theme', next);
      updateThemeIcon(next);
    });
  }
  function updateThemeIcon(theme){
    if(!themeBtn) return;
    themeBtn.innerHTML = theme === 'dark' ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
  }

  /* ---------- Mobile nav toggle ---------- */
  const hamburger = document.getElementById('hamburger');
  const navLinks = document.getElementById('navLinks');
  if(hamburger && navLinks){
    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('open');
      navLinks.classList.toggle('open');
    });
    navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      hamburger.classList.remove('open');
      navLinks.classList.remove('open');
    }));
  }

  /* ---------- Sticky navbar shadow on scroll + scroll-top button ---------- */
  const navbar = document.getElementById('navbar');
  const scrollTopBtn = document.getElementById('scrollTop');
  window.addEventListener('scroll', () => {
    if(navbar){
      if(window.scrollY > 12) navbar.classList.add('scrolled');
      else navbar.classList.remove('scrolled');
    }
    if(scrollTopBtn){
      if(window.scrollY > 500) scrollTopBtn.classList.add('show');
      else scrollTopBtn.classList.remove('show');
    }
  });
  if(scrollTopBtn){
    scrollTopBtn.addEventListener('click', () => window.scrollTo({top:0, behavior:'smooth'}));
  }

  /* ---------- Reveal on scroll ---------- */
  const revealEls = document.querySelectorAll('.reveal');
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if(entry.isIntersecting){
        entry.target.classList.add('in');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });
  revealEls.forEach(el => io.observe(el));

  /* ---------- Animate growth chart bars ---------- */
  const bars = document.querySelectorAll('.chart-bars .bar');
  const barIo = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if(entry.isIntersecting){
        const bar = entry.target;
        bar.style.height = bar.dataset.height;
        barIo.unobserve(bar);
      }
    });
  }, { threshold: 0.3 });
  bars.forEach(bar => { bar.style.height = '0%'; barIo.observe(bar); });

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll('.faq-item').forEach(item => {
    const q = item.querySelector('.faq-q');
    if(!q) return;
    q.addEventListener('click', () => {
      const wasOpen = item.classList.contains('open');
      item.closest('.faq-list')?.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      if(!wasOpen) item.classList.add('open');
    });
  });

  /* ---------- Generic form success handler (demo, no backend) ---------- */
  document.querySelectorAll('form[data-demo-form]').forEach(form => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const successEl = form.querySelector('.form-success');
      if(successEl){
        successEl.classList.add('show');
        setTimeout(() => successEl.classList.remove('show'), 6000);
      }
      form.reset();
    });
  });

  /* ---------- Contact form (real submission via PHP + PHPMailer) ---------- */
  const contactForm = document.getElementById('contactForm');
  if(contactForm){
    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const successEl = contactForm.querySelector('.form-success');
      const submitBtn = contactForm.querySelector('button[type="submit"]');
      const originalLabel = submitBtn ? submitBtn.textContent : '';
      if(submitBtn){ submitBtn.disabled = true; submitBtn.textContent = 'Sending...'; }

      try{
        const res = await fetch(contactForm.action, {
          method: 'POST',
          headers: { 'Accept': 'application/json' },
          body: new FormData(contactForm)
        });
        const data = await res.json();
        if(successEl){
          successEl.textContent = data.message || (data.success ? 'Message sent!' : 'Something went wrong. Please try again.');
          successEl.classList.toggle('form-error', !data.success);
          successEl.classList.add('show');
          setTimeout(() => successEl.classList.remove('show'), 8000);
        }
        if(data.success) contactForm.reset();
      } catch(err){
        if(successEl){
          successEl.textContent = 'Network error — please try again later.';
          successEl.classList.add('show', 'form-error');
          setTimeout(() => successEl.classList.remove('show'), 8000);
        }
      } finally {
        if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = originalLabel; }
      }
    });
  }

  /* ---------- Pill option groups (single-select) ---------- */
  document.querySelectorAll('.pill-options').forEach(group => {
    group.addEventListener('click', (e) => {
      const pill = e.target.closest('.pill');
      if(!pill) return;
      group.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      if(typeof window.recalcSubscription === 'function') window.recalcSubscription();
    });
  });

  /* ---------- Range inputs live value ---------- */
  document.querySelectorAll('input[type="range"]').forEach(range => {
    const out = document.querySelector(`[data-range-out="${range.id}"]`);
    const sync = () => { if(out) out.textContent = range.value; };
    sync();
    range.addEventListener('input', () => {
      sync();
      if(typeof window.recalcSubscription === 'function') window.recalcSubscription();
    });
  });

  /* ---------- Billing toggle label sync ---------- */
  const billingSwitch = document.getElementById('billingSwitch');
  if(billingSwitch){
    billingSwitch.addEventListener('change', () => {
      if(typeof window.recalcSubscription === 'function') window.recalcSubscription();
    });
  }

  /* ---------- Init subscription calculator if present on page ---------- */
  if(document.getElementById('calcSummary')){
    initSubscriptionCalculator();
  }
});

/* ==========================================================================
   Subscription Pricing Calculator
   ========================================================================== */
function initSubscriptionCalculator(){
  const propertyRates = { apartment: 60, house: 85, villa: 140, office: 110, retail: 130 };
  const cleaningFactors = { standard: 1, deep: 1.45, moveinout: 1.6, postconstruction: 1.85, commercial: 1.3 };
  const frequencyDiscounts = { once: 1, weekly: 0.85, biweekly: 0.9, monthly: 0.95 };

  function getActivePill(groupId, fallback){
    const group = document.getElementById(groupId);
    if(!group) return fallback;
    const active = group.querySelector('.pill.active');
    return active ? active.dataset.value : fallback;
  }

  window.recalcSubscription = function(){
    const property = getActivePill('propertyTypeGroup', 'apartment');
    const cleaningType = getActivePill('cleaningTypeGroup', 'standard');
    const frequency = getActivePill('frequencyGroup', 'weekly');

    const rooms = parseInt(document.getElementById('roomsRange')?.value || 2, 10);
    const bathrooms = parseInt(document.getElementById('bathroomsRange')?.value || 1, 10);
    const hours = parseInt(document.getElementById('hoursRange')?.value || 3, 10);
    const hourlyRate = parseInt(document.getElementById('hourlyRateRange')?.value || 25, 10);

    const isAnnual = document.getElementById('billingSwitch')?.checked;

    const base = propertyRates[property] || 60;
    const roomCost = rooms * 14;
    const bathroomCost = bathrooms * 12;
    const hourCost = hours * hourlyRate;
    const factor = cleaningFactors[cleaningType] || 1;
    const freqDiscount = frequencyDiscounts[frequency] || 1;

    let subtotal = (base + roomCost + bathroomCost + hourCost) * factor * freqDiscount;
    let annualDiscountPct = 0;
    if(isAnnual){
      annualDiscountPct = 15;
      subtotal = subtotal * (1 - annualDiscountPct/100);
    }

    const total = Math.round(subtotal);

    setText('sumProperty', capitalize(property));
    setText('sumRooms', `${rooms} rooms / ${bathrooms} bathrooms`);
    setText('sumCleaning', capitalize(cleaningType.replace('moveinout','Move In/Out').replace('postconstruction','Post-Construction')));
    setText('sumFrequency', capitalize(frequency));
    setText('sumHours', `${hours} hrs @ $${hourlyRate}/hr`);
    setText('sumBilling', isAnnual ? 'Annually (15% off)' : 'Monthly');
    setText('calcTotal', `$${total}`);
    setText('calcTotalSuffix', isAnnual ? '/mo, billed yearly' : '/visit');

    const dateInput = document.getElementById('cleaningDate');
    if(dateInput && dateInput.value){
      setText('sumDate', new Date(dateInput.value).toLocaleDateString(undefined, {year:'numeric', month:'long', day:'numeric'}));
    }
  };

  function setText(id, val){
    const el = document.getElementById(id);
    if(el) el.textContent = val;
  }
  function capitalize(str){
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  document.getElementById('cleaningDate')?.addEventListener('change', window.recalcSubscription);

  window.recalcSubscription();
}
