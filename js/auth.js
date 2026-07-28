/* ==========================================================================
   Manor Cares — Create Account / Sign In page logic
   Talks to php/auth-signup.php and php/auth-login.php (Railway Postgres + JWT)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  const tabSignUp = document.getElementById('tabSignUp');
  const tabSignIn = document.getElementById('tabSignIn');
  const signUpPanel = document.getElementById('signUpPanel');
  const signInPanel = document.getElementById('signInPanel');

  function showPanel(which){
    const isSignUp = which === 'signup';
    tabSignUp.classList.toggle('active', isSignUp);
    tabSignIn.classList.toggle('active', !isSignUp);
    tabSignUp.setAttribute('aria-selected', String(isSignUp));
    tabSignIn.setAttribute('aria-selected', String(!isSignUp));
    signUpPanel.style.display = isSignUp ? '' : 'none';
    signInPanel.style.display = isSignUp ? 'none' : '';
  }

  if(tabSignUp && tabSignIn){
    tabSignUp.addEventListener('click', () => showPanel('signup'));
    tabSignIn.addEventListener('click', () => showPanel('signin'));
  }

  // Prefill the selected plan from ?plan= query string set by subscription.html
  const params = new URLSearchParams(window.location.search);
  const plan = (params.get('plan') || 'essential').toLowerCase();
  const allowedPlans = { essential: 'Essential', signature: 'Signature', estate: 'Estate', custom: 'Custom' };
  const planKey = allowedPlans[plan] ? plan : 'essential';

  const planInput = document.getElementById('signUpPlan');
  const planLabel = document.getElementById('selectedPlanLabel');
  if(planInput) planInput.value = planKey;
  if(planLabel) planLabel.textContent = allowedPlans[planKey];

  if(params.get('mode') === 'signin'){
    showPanel('signin');
  }

  function handleAuthForm(form, endpoint, onSuccessRedirect){
    if(!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const successEl = form.querySelector('.form-success');
      const submitBtn = form.querySelector('button[type="submit"]');
      const originalLabel = submitBtn ? submitBtn.textContent : '';
      if(submitBtn){ submitBtn.disabled = true; submitBtn.textContent = 'Please wait...'; }

      try{
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Accept': 'application/json' },
          body: new FormData(form)
        });
        const data = await res.json();

        if(successEl){
          successEl.textContent = data.message || (data.success ? 'Success!' : 'Something went wrong.');
          successEl.classList.toggle('form-error', !data.success);
          successEl.classList.add('show');
        }

        if(data.success){
          form.reset();
          setTimeout(() => { window.location.href = onSuccessRedirect; }, 1200);
        }
      } catch(err){
        if(successEl){
          successEl.textContent = 'Network error — please try again later.';
          successEl.classList.add('show', 'form-error');
        }
      } finally {
        if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = originalLabel; }
      }
    });
  }

  handleAuthForm(document.getElementById('signUpForm'), 'php/auth-signup.php', 'subscription.html');
  handleAuthForm(document.getElementById('signInForm'), 'php/auth-login.php', 'subscription.html');
});
