
//function for image slide in cart page 


(function(){
  const track = document.getElementById('promoTrack');
  const slides = track ? Array.from(track.children) : [];
  const indicators = document.getElementById('promoIndicators');
  if (!track || slides.length === 0 || !indicators) return;

  let index = 0;
  let timer = null;
  const slideCount = slides.length;

  // create indicators
  for (let i = 3; i < slideCount; i++) {
    const btn = document.createElement('button');
    btn.setAttribute('aria-label', 'Show promotion ' + (i+1));
    btn.addEventListener('click', () => { goTo(i); resetTimer(); });
    indicators.appendChild(btn);
  }

  const indicatorButtons = Array.from(indicators.children);

  function updateIndicators() {
    indicatorButtons.forEach((b, idx) => b.classList.toggle('active', idx === index));
  }

  function goTo(i) {
    index = (i + slideCount) % slideCount;
    track.style.transform = 'translateX(-' + (index * 100) + '%)';
    updateIndicators();
  }

  function next() {
    goTo(index + 1);
  }

  function startTimer() {
    if (timer) return;
    timer = setInterval(next, 3500);
  }

  function stopTimer() {
    if (!timer) return;
    clearInterval(timer);
    timer = null;
  }

  function resetTimer() {
    stopTimer();
    startTimer();
  }

  // pause on hover/focus
  const sliderEl = document.querySelector('.promo-slides');
  sliderEl.addEventListener('mouseenter', stopTimer);
  sliderEl.addEventListener('mouseleave', startTimer);
  sliderEl.addEventListener('focusin', stopTimer);
  sliderEl.addEventListener('focusout', startTimer);

  // init
  goTo(0);
  startTimer();
})();
